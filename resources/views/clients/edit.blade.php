@extends('layouts.app')

@section('title', 'Edit Client | Havas Media Buying Platform')

@section('content')
    <section class="space-y-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-orange-300">Clients</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Edit Client</h1>
        </div>

        <form method="POST" action="{{ route('web.clients.update', $client) }}" class="space-y-5 rounded-xl border border-slate-800 bg-slate-900/80 p-6">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm text-slate-300">Client Name <span class="text-rose-400">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $client->name) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="category_id" class="mb-1 block text-sm text-slate-300">Category</label>
                    <select id="category_id" name="category_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                        <option value="">Select category...</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', (int) $client->category_id) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="industry" class="mb-1 block text-sm text-slate-300">Industry</label>
                    <input type="text" id="industry" name="industry" value="{{ old('industry', $client->industry) }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="contact_name" class="mb-1 block text-sm text-slate-300">Contact Name</label>
                    <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $client->contact_name) }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="contact_email" class="mb-1 block text-sm text-slate-300">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $client->contact_email) }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>

                <div>
                    <label for="contact_phone" class="mb-1 block text-sm text-slate-300">Contact Phone</label>
                    <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $client->contact_phone) }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-slate-100 focus:border-orange-300 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-md bg-orange-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-orange-400">
                    Save changes
                </button>
                <a href="{{ route('web.clients.show', $client) }}" class="rounded-md border border-slate-700 px-4 py-2 text-sm hover:border-orange-300/60">
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
