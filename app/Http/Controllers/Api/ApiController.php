<?php

namespace App\Http\Controllers\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * API 响应工具基类：统一 success/error/paginated 响应格式。
 */
abstract class ApiController
{
    protected function success(mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $data,
        ], $code);
    }

    protected function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function created(mixed $data): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $data,
        ], 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => null,
        ], 204);
    }
}
