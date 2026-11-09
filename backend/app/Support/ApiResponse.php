<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function ok($data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof LengthAwarePaginator) {
            $meta = array_merge([
                'current_page' => $data->currentPage(), 'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(), 'total' => $data->total(),
            ], $meta);
            $data = $data->items();
        }
        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) $body['meta'] = $meta;
        return response()->json($body, $status);
    }

    protected function created($data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function perPage(): int
    {
        return min(max((int) request('per_page', 20), 1), 100);
    }
}
