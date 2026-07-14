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

    /**
     * Success with a JSON array under data, plus optional top-level meta (pagination, filters).
     *
     * @param  list<mixed>  $items
     * @param  array<string, mixed>  $meta
     */
    public static function successList(array $items, string $message = '', int $status = 200, array $meta = []): JsonResponse
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            $normalized = json_decode(
                json_encode(array_values($items), JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $metaNormalized = $meta === [] ? [] : json_decode(
                json_encode($meta, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            $normalized = array_values($items);
            $metaNormalized = $meta;
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', (string) $previous);
            }
        }

        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $normalized,
        ], is_array($metaNormalized) ? $metaNormalized : []), $status);
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

        // Avoid JSON long floats like 178.66999999999999 from PHP binary floats.
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            return json_decode(
                json_encode($data, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable) {
            return $data;
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', (string) $previous);
            }
        }
    }
}
