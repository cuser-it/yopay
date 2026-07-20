<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateDeveloperRequest;
use App\Http\Middleware\EnsureApplicationInstalled;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->prepend(EnsureApplicationInstalled::class);
        $middleware->alias([
            'developer.auth' => AuthenticateDeveloperRequest::class,
            'admin' => EnsureAdministrator::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'payments/callbacks/easypay/*',
            'payapi/notify.php',
            'api/notify.php',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*', 'checkout-api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*', 'checkout-api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::error(
                'VALIDATION_ERROR',
                '请求参数验证失败。',
                422,
                $exception->errors(),
            );
        });
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (
                $exception instanceof ValidationException
                || (! $request->is('api/*', 'checkout-api/*') && ! $request->expectsJson())
            ) {
                return null;
            }

            $status = match (true) {
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            [$code, $message] = match ($status) {
                401 => ['UNAUTHENTICATED', '身份验证失败。'],
                403 => ['FORBIDDEN', '没有权限执行该操作。'],
                404 => ['NOT_FOUND', '请求的资源不存在。'],
                405 => ['METHOD_NOT_ALLOWED', '请求方法不受支持。'],
                419 => ['CSRF_TOKEN_MISMATCH', '页面会话已过期，请刷新后重试。'],
                429 => ['RATE_LIMITED', '请求过于频繁，请稍后重试。'],
                default => $status >= 500
                    ? ['INTERNAL_ERROR', '服务暂时不可用，请稍后重试。']
                    : ['REQUEST_FAILED', '请求无法完成。'],
            };

            return ApiResponse::error($code, $message, $status);
        });
    })->create();
