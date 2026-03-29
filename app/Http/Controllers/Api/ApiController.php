<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ApiController extends Controller
{
    protected function respond(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    protected function respondPaginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->respond(
            $paginator->items(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]
        );
    }

    protected function respondCollection(Collection $collection): JsonResponse
    {
        return $this->respond($collection->values(), ['total' => $collection->count()]);
    }
}
