<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Domain\Install\DatabaseProbe;
use App\Domain\Install\EasyPayConfigurationValidator;
use App\Domain\Install\InstallAccessService;
use App\Domain\Install\InstallationService;
use App\Domain\Install\InstallSessionStore;
use App\Domain\Install\InstallState;
use App\Domain\Install\SystemRequirementChecker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class InstallController extends Controller
{
    public function __construct(
        private readonly InstallState $state,
        private readonly InstallAccessService $access,
        private readonly InstallSessionStore $sessions,
        private readonly SystemRequirementChecker $requirements,
        private readonly DatabaseProbe $databaseProbe,
        private readonly EasyPayConfigurationValidator $easyPayValidator,
        private readonly InstallationService $installer,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        if ($this->access->accessContext($request) !== null) {
            return redirect()->route('install.requirements');
        }

        return view('install.access', [
            'tokenAvailable' => $this->state->hasAccessToken(),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse|Response
    {
        $clientIdentifier = (string) ($request->ip() ?? 'unknown');

        if ($this->state->tooManyAuthenticationAttempts($clientIdentifier)) {
            return response()->view('install.access', [
                'tokenAvailable' => $this->state->hasAccessToken(),
                'formErrors' => ['install_token' => ['验证失败次数过多，请十分钟后再试或在服务器终端轮换令牌。']],
            ], 429);
        }

        $token = trim((string) $request->input('install_token', ''));

        if (! $this->state->verifyAccessToken($token)) {
            $this->state->recordAuthenticationFailure($clientIdentifier);
            usleep(350000);

            return response()->view('install.access', [
                'tokenAvailable' => $this->state->hasAccessToken(),
                'formErrors' => ['install_token' => ['安装令牌无效。']],
            ], 422);
        }

        $this->state->clearAuthenticationFailures($clientIdentifier);

        return redirect()->route('install.requirements')->withCookie($this->access->issueCookie($request));
    }

    public function requirements(Request $request): View
    {
        $checks = $this->requirements->checks();

        return view('install.requirements', [
            'checks' => $checks,
            'passes' => ! in_array(false, array_column($checks, 'passed'), true),
            'csrf' => $this->context($request)['csrf'],
        ]);
    }

    public function database(Request $request): View
    {
        $draft = $this->draft($request);

        return view('install.database', [
            'csrf' => $this->context($request)['csrf'],
            'values' => [
                'site_name' => $draft['site']['name'] ?? config('app.name', 'Yunvix Payment'),
                'app_url' => $draft['site']['app_url'] ?? $request->getSchemeAndHttpHost(),
                'db_host' => $draft['database']['host'] ?? '127.0.0.1',
                'db_port' => $draft['database']['port'] ?? 3306,
                'db_database' => $draft['database']['database'] ?? '',
                'db_username' => $draft['database']['username'] ?? '',
            ],
        ]);
    }

    public function storeDatabase(Request $request): RedirectResponse|Response
    {
        if (! $this->requirements->passes()) {
            return redirect()->route('install.requirements');
        }

        $validator = Validator::make($request->all(), [
            'site_name' => ['required', 'string', 'max:80'],
            'app_url' => ['required', 'string', 'max:255'],
            'db_host' => ['required', 'string', 'max:255', 'regex:/\A[^;\s]+\z/'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_$-]+\z/'],
            'db_username' => ['required', 'string', 'max:128'],
            'db_password' => ['nullable', 'string', 'max:1024'],
        ], [
            'required' => '该字段不能为空。',
            'regex' => '字段格式不安全或不受支持。',
            'integer' => '端口必须是整数。',
            'between' => '端口必须在 1 到 65535 之间。',
            'max' => '字段内容过长。',
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $this->validApplicationUrl((string) $request->input('app_url'))) {
                $validator->errors()->add('app_url', '公网生产站点必须使用 HTTPS，且地址不能包含路径、查询参数或片段。');
            }
        });

        if ($validator->fails()) {
            return response()->view('install.database', [
                'csrf' => $this->context($request)['csrf'],
                'values' => $request->except(['db_password', '_install_csrf']),
                'formErrors' => $validator->errors()->toArray(),
            ], 422);
        }

        $database = [
            'host' => trim((string) $request->input('db_host')),
            'port' => (int) $request->input('db_port'),
            'database' => trim((string) $request->input('db_database')),
            'username' => trim((string) $request->input('db_username')),
            'password' => (string) $request->input('db_password', ''),
        ];

        try {
            $this->databaseProbe->assertConnectableAndEmpty($database);
        } catch (\Throwable $exception) {
            return response()->view('install.database', [
                'csrf' => $this->context($request)['csrf'],
                'values' => $request->except(['db_password', '_install_csrf']),
                'formErrors' => ['database' => [$exception->getMessage()]],
            ], 422);
        }

        $draft = $this->draft($request);
        $draft['site'] = [
            'name' => trim((string) $request->input('site_name')),
            'app_url' => rtrim(trim((string) $request->input('app_url')), '/'),
        ];
        $draft['database'] = $database;
        $this->sessions->write($this->context($request)['session_id'], $draft);

        return redirect()->route('install.easypay');
    }

    public function easypay(Request $request): RedirectResponse|View
    {
        $draft = $this->draft($request);

        if (! isset($draft['database'])) {
            return redirect()->route('install.database');
        }

        return view('install.easypay', [
            'csrf' => $this->context($request)['csrf'],
            'values' => [
                'merchant_id' => $draft['easypay']['merchant_id'] ?? '',
                'base_url' => $draft['easypay']['base_url'] ?? 'https://pay.yunvix.com',
            ],
            'configured' => isset($draft['easypay']),
        ]);
    }

    public function storeEasypay(Request $request): RedirectResponse|Response
    {
        $draft = $this->draft($request);

        if (! isset($draft['database'])) {
            return redirect()->route('install.database');
        }

        $validator = Validator::make($request->all(), [
            'merchant_id' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'string', 'max:255'],
            'merchant_private_key' => ['required', 'string', 'max:16384'],
            'platform_public_key' => ['required', 'string', 'max:16384'],
        ], [
            'required' => '该字段不能为空。',
            'max' => '字段内容过长。',
        ]);

        if ($validator->fails()) {
            return response()->view('install.easypay', [
                'csrf' => $this->context($request)['csrf'],
                'values' => $request->only(['merchant_id', 'base_url']),
                'configured' => isset($draft['easypay']),
                'formErrors' => $validator->errors()->toArray(),
            ], 422);
        }

        $configuration = [
            'merchant_id' => trim((string) $request->input('merchant_id')),
            'base_url' => rtrim(trim((string) $request->input('base_url')), '/'),
            'merchant_private_key' => trim((string) $request->input('merchant_private_key')),
            'platform_public_key' => trim((string) $request->input('platform_public_key')),
        ];

        try {
            $this->easyPayValidator->validate($configuration);
        } catch (\Throwable $exception) {
            return response()->view('install.easypay', [
                'csrf' => $this->context($request)['csrf'],
                'values' => $request->only(['merchant_id', 'base_url']),
                'configured' => isset($draft['easypay']),
                'formErrors' => ['easypay' => [$exception->getMessage()]],
            ], 422);
        }

        $draft['easypay'] = $configuration;
        $this->sessions->write($this->context($request)['session_id'], $draft);

        return redirect()->route('install.administrator');
    }

    public function administrator(Request $request): RedirectResponse|View
    {
        $draft = $this->draft($request);

        if (! isset($draft['database'])) {
            return redirect()->route('install.database');
        }

        if (! isset($draft['easypay'])) {
            return redirect()->route('install.easypay');
        }

        return view('install.administrator', [
            'csrf' => $this->context($request)['csrf'],
            'summary' => $this->summary($draft),
        ]);
    }

    public function install(Request $request): Response
    {
        $draft = $this->draft($request);
        $validator = Validator::make($request->all(), [
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'confirm_empty_database' => ['accepted'],
        ], [
            'required' => '该字段不能为空。',
            'email' => '请输入有效的管理员邮箱。',
            'confirmed' => '两次输入的密码不一致。',
            'accepted' => '必须确认目标数据库为空且可以写入。',
            'max' => '字段内容过长。',
        ]);

        if (! isset($draft['database'], $draft['easypay'])) {
            $validator->errors()->add('installation', '安装配置不完整，请返回前面的步骤重新填写。');
        }

        if ($validator->fails()) {
            return response()->view('install.administrator', [
                'csrf' => $this->context($request)['csrf'],
                'summary' => $this->summary($draft),
                'values' => $request->only(['admin_name', 'admin_email']),
                'formErrors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            $this->installer->install($draft, [
                'name' => trim((string) $request->input('admin_name')),
                'email' => strtolower(trim((string) $request->input('admin_email'))),
                'password' => (string) $request->input('admin_password'),
            ]);
            $this->sessions->delete($this->context($request)['session_id']);
        } catch (\Throwable $exception) {
            Log::error('Payment SaaS installation failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->view('install.administrator', [
                'csrf' => $this->context($request)['csrf'],
                'summary' => $this->summary($draft),
                'values' => $request->only(['admin_name', 'admin_email']),
                'formErrors' => ['installation' => [
                    $exception->getMessage().' 如果迁移已经开始，请更换或恢复为空数据库后再试；安装器绝不会自动删除已有表。',
                ]],
            ], 500);
        }

        return response()
            ->view('install.complete', ['appUrl' => $draft['site']['app_url']], 200)
            ->withCookie($this->access->expireCookie($request));
    }

    /** @return array{session_id: string, csrf: string, expires_at: int} */
    private function context(Request $request): array
    {
        $context = $request->attributes->get('install_access');

        abort_unless(is_array($context), 403);

        return $context;
    }

    private function draft(Request $request): array
    {
        return $this->sessions->read($this->context($request)['session_id']);
    }

    private function validApplicationUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/')) {
            return false;
        }

        if (isset($parts['query']) || isset($parts['fragment']) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http'
            && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function summary(array $draft): array
    {
        return [
            'site_name' => $draft['site']['name'] ?? '—',
            'app_url' => $draft['site']['app_url'] ?? '—',
            'database' => isset($draft['database'])
                ? $draft['database']['username'].'@'.$draft['database']['host'].':'.$draft['database']['port'].'/'.$draft['database']['database']
                : '—',
            'merchant_id' => $draft['easypay']['merchant_id'] ?? '—',
            'easypay_url' => $draft['easypay']['base_url'] ?? '—',
        ];
    }
}
