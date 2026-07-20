<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Install\InstallState;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApplicationInstalled
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || $this->state->isInstalled() || $request->is('install', 'install/*')) {
            return $next($request);
        }

        if (
            $request->isMethod('GET')
            && ! $request->is('api/*', 'checkout-api/*', 'payments/callbacks/*', 'up')
            && ! $request->expectsJson()
        ) {
            return redirect('/install');
        }

        return ApiResponse::error(
            'APPLICATION_NOT_INSTALLED',
            '系统尚未完成安装。',
            503,
        );
    }
}
