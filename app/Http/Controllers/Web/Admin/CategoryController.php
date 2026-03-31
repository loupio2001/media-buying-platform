<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryBenchmark;
use App\Models\Platform;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount(['benchmarks', 'clients'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function edit(int $category): View
    {
        $category = Category::findOrFail($category);
        $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();
        $benchmarks = CategoryBenchmark::query()
            ->where('category_id', $category->id)
            ->get()
            ->groupBy('platform_id')
            ->map(fn ($rows) => $rows->keyBy('metric'));

        return view('admin.categories.edit', compact('category', 'platforms', 'benchmarks'));
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $category = Category::findOrFail($category);

        $data = $request->validate([
            'benchmarks' => ['nullable', 'array'],
            'benchmarks.*' => ['array'],
            'benchmarks.*.*' => ['array'],
            'benchmarks.*.*.*' => ['nullable', 'numeric'],
        ]);

        DB::transaction(function () use ($category, $data): void {
            foreach ($data['benchmarks'] ?? [] as $platformId => $metrics) {
                foreach ($metrics as $metric => $values) {
                    CategoryBenchmark::query()->updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'platform_id' => (int) $platformId,
                            'metric' => $metric,
                        ],
                        [
                            'min_value' => isset($values['min']) && $values['min'] !== '' ? (float) $values['min'] : null,
                            'max_value' => isset($values['max']) && $values['max'] !== '' ? (float) $values['max'] : null,
                            'last_reviewed_at' => now(),
                        ]
                    );
                }
            }
        });

        return redirect()
            ->route('web.admin.categories.index')
            ->with('status', 'Benchmarks updated for ' . $category->name . '.');
    }
}
