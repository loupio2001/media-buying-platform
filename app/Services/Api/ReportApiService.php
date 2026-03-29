<?php

namespace App\Services\Api;

use App\Models\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReportApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Report::query()->with(['campaign', 'creator', 'reviewer'])->paginate($perPage);
    }

    public function store(array $data, int $userId): Report
    {
        $data['created_by'] = $userId;

        return Report::create($data);
    }

    public function update(Report $report, array $data): Report
    {
        $report->update($data);

        return $report->refresh();
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }
}
