<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta === [] ? null : $meta,
            'error' => null,
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details === [] ? null : $details,
            ],
        ], $status);
    }
}
