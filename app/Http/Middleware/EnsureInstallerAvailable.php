<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstallerAvailable
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->isInstalled()) {
            abort(404);
        }

        $loopbackRequest = $this->isLoopbackRequest($request);

        if (! $request->isSecure() && ! $loopbackRequest) {
            abort(403, '安装向导仅允许通过 HTTPS 或服务器本机回环地址访问。');
        }

        if (! $loopbackRequest && (bool) config('app.debug')) {
            abort(503, '公网安装前必须关闭 APP_DEBUG。');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; script-src 'self'; style-src 'self'",
        );

        return $response;
    }

    private function isLoopbackRequest(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $clientIp = $request->ip();

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && in_array($clientIp, ['127.0.0.1', '::1'], true);
    }

}
