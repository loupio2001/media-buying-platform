@extends('layouts.app')

@section('title', 'Categories | Admin | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Admin</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Categories & Benchmarks</h1>
            <p class="mt-2 text-slate-300">Configure industry benchmarks per category and platform.</p>
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/80">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950/40 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Category</th>
                            <th class="px-5 py-3 font-medium">Clients</th>
                            <th class="px-5 py-3 font-medium">Benchmarks</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr class="border-t border-slate-800/80 text-slate-200">
                                <td class="px-5 py-3 font-medium">{{ $category->name }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $category->clients_count }}</td>
                                <td class="px-5 py-3 text-slate-300">{{ $category->benchmarks_count }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('web.admin.categories.edit', $category) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-orange-300 hover:border-orange-300/60">
                                        Edit Benchmarks
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-800/80 text-slate-300">
                                <td colspan="4" class="px-5 py-4">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-3">
                {{ $categories->links() }}
            </div>
        </section>
    </section>
@endsection
