<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Developer\Support\DeveloperRequestSignature;
use App\Http\Responses\ApiResponse;
use App\Models\ApiRequestNonce;
use App\Models\DeveloperApiCredential;
use App\Models\DeveloperApplication;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateDeveloperRequest
{
    public function __construct(private ConfigRepository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $appId = (string) $request->header('X-App-Id', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = (string) $request->header('X-Signature', '');
        $keyId = (string) $request->header('X-Key-Id', '');

        if ($appId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return ApiResponse::error('INVALID_SIGNATURE', '缺少开发者签名请求头。', 401);
        }

        $timestampValue = filter_var($timestamp, FILTER_VALIDATE_INT);
        $tolerance = (int) $this->config->get('payment.developer_api.timestamp_tolerance_seconds', 300);

        if ($timestampValue === false || abs(time() - $timestampValue) > $tolerance) {
            return ApiResponse::error('REQUEST_EXPIRED', '请求时间戳已过期。', 401);
        }

        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return ApiResponse::error('INVALID_SIGNATURE', '请求 nonce 格式无效。', 401);
        }

        $application = DeveloperApplication::query()->where('public_id', $appId)->first();

        if ($application === null) {
            return ApiResponse::error('INVALID_SIGNATURE', '开发者签名无效。', 401);
        }

        if ($application->status !== 'active') {
            return ApiResponse::error('APP_DISABLED', '开发者应用已停用。', 403);
        }

        if (! $this->ipAllowed($request->ip(), $application->ip_allowlist ?? [])) {
            return ApiResponse::error('INVALID_SIGNATURE', '请求来源不在应用 IP 白名单中。', 403);
        }

        $credentials = DeveloperApiCredential::query()
            ->where('application_id', $application->getKey())
            ->whereNull('revoked_at')
            ->where(static function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($keyId !== '', static fn ($query) => $query->where('key_id', $keyId))
            ->get();
        $canonical = DeveloperRequestSignature::canonical(
            timestamp: $timestamp,
            nonce: $nonce,
            method: $request->method(),
            requestTarget: $request->getRequestUri(),
            rawBody: $request->getContent(),
        );
        $credential = $credentials->first(
            static fn (DeveloperApiCredential $candidate): bool => DeveloperRequestSignature::verify(
                $canonical,
                (string) $candidate->secret_ciphertext,
                $signature,
            ),
        );

        if ($credential === null) {
            return ApiResponse::error('INVALID_SIGNATURE', '开发者签名无效。', 401);
        }

        try {
            ApiRequestNonce::query()->create([
                'application_id' => $application->getKey(),
                'nonce_hash' => hash('sha256', $nonce),
                'request_timestamp' => now()->setTimestamp((int) $timestampValue),
                'expires_at' => now()->addSeconds((int) $this->config->get('payment.developer_api.nonce_ttl_seconds', 600)),
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return ApiResponse::error('NONCE_REPLAYED', '请求 nonce 已使用。', 409);
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('developer_application', $application);
        $request->attributes->set('developer_credential', $credential);

        return $next($request);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23505'
            || ($sqlState === '23000' && $driverCode === 1062)
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    private function ipAllowed(?string $ip, array $allowlist): bool
    {
        if ($allowlist === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowlist);
    }
}
