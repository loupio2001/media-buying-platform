<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ApiControllerResponseContractTest extends TestCase
{
    public function test_respond_wraps_payload_with_data_and_meta(): void
    {
        $controller = new class extends ApiController {
            public function exposeRespond(mixed $data, array $meta = [], int $status = 200)
            {
                return $this->respond($data, $meta, $status);
            }
        };

        $response = $controller->exposeRespond(['id' => 10], ['status' => 'ok'], 201);

        $this->assertSame(201, $response->status());
        $this->assertSame(['id' => 10], $response->getData(true)['data']);
        $this->assertSame(['status' => 'ok'], $response->getData(true)['meta']);
    }

    public function test_respond_paginated_includes_standard_pagination_meta(): void
    {
        $controller = new class extends ApiController {
            public function exposeRespondPaginated(LengthAwarePaginator $paginator)
            {
                return $this->respondPaginated($paginator);
            }
        };

        $paginator = new LengthAwarePaginator(
            items: [['id' => 1], ['id' => 2]],
            total: 20,
            perPage: 2,
            currentPage: 3
        );

        $response = $controller->exposeRespondPaginated($paginator);
        $json = $response->getData(true);

        $this->assertCount(2, $json['data']);
        $this->assertSame(20, $json['meta']['total']);
        $this->assertSame(2, $json['meta']['per_page']);
        $this->assertSame(3, $json['meta']['current_page']);
        $this->assertSame(10, $json['meta']['last_page']);
    }

    public function test_respond_collection_includes_total_meta(): void
    {
        $controller = new class extends ApiController {
            public function exposeRespondCollection(Collection $collection)
            {
                return $this->respondCollection($collection);
            }
        };

        $response = $controller->exposeRespondCollection(collect([['id' => 1], ['id' => 2], ['id' => 3]]));
        $json = $response->getData(true);

        $this->assertCount(3, $json['data']);
        $this->assertSame(3, $json['meta']['total']);
    }
}
