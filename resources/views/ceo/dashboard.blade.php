@extends('ceo.layout.master')

@section('title', 'CEO Dashboard')

@section('page-content')
    <p class="mb-6 text-sm text-gray-500">Businesses and branches assigned to {{ auth()->user()->name }}.</p>

    <form method="get" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <label class="text-xs font-semibold text-gray-500">From
            <input type="date" name="from" value="{{ $summary['from'] }}" class="mt-1 block rounded-lg border border-gray-200 px-3 py-2 text-sm">
        </label>
        <label class="text-xs font-semibold text-gray-500">To
            <input type="date" name="to" value="{{ $summary['to'] }}" class="mt-1 block rounded-lg border border-gray-200 px-3 py-2 text-sm">
        </label>
        <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Refresh</button>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Businesses</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $summary['totals']['businesses'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Branches</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $summary['totals']['branches'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Orders</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ number_format($summary['totals']['orders']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Sales</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>Rs. {{ number_format($summary['totals']['sales']) }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="font-display text-lg font-semibold text-hut-dark">Assigned businesses</h2>
            <div class="mt-4 space-y-3">
                @forelse($businesses as $assignment)
                    <div class="rounded-lg border border-gray-100 p-3">
                        <p class="font-semibold text-hut-dark">{{ $assignment->restaurant?->name ?? 'Unavailable business' }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($assignment->access_level) }} access</p>
                        @php($report = $summary['reports']->firstWhere('restaurant.id', $assignment->restaurant_id))
                        @if($report && $report['status'] === 'available')
                            <p class="mt-2 text-xs text-gray-500">
                                {{ number_format($report['orders']) }} orders · Rs. {{ number_format($report['sales']) }} sales · {{ $report['branches'] }} branches
                            </p>
                        @else
                            <p class="mt-2 text-xs text-amber-600">Operational data unavailable</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No businesses have been assigned yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="font-display text-lg font-semibold text-hut-dark">Assigned branches</h2>
            <p class="mt-2 text-sm text-gray-500">{{ $branches->count() }} branch assignment(s)</p>
        </section>
    </div>
@endsection
