<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Install\InstallAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstallerAccess
{
    public function __construct(private readonly InstallAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->access->accessContext($request);

        if ($context === null) {
            return $request->isMethodSafe() ? redirect()->route('install.index') : abort(403);
        }

        if (! $request->isMethodSafe()) {
            $submittedToken = $request->input('_install_csrf');

            if (! is_string($submittedToken) || ! hash_equals($context['csrf'], $submittedToken)) {
                abort(419, '安装会话已失效，请重新验证安装令牌。');
            }
        }

        $request->attributes->set('install_access', $context);

        return $next($request);
    }
}
