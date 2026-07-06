<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::formatData($data),
        ], $status);
    }

    public static function error(string $message, mixed $data = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => self::formatData($data),
        ], $status);
    }

    protected static function formatData(mixed $data): mixed
    {
        if ($data === [] || $data === null) {
            return (object) [];
        }

        return $data;
    }
}
