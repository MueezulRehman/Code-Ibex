@extends('manager.layout.master')
@section('title', 'My Dashboard')

@section('page-content')

@include('manager.partials.hospital-dashboard')

<div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm mb-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Your Restaurant</p>
            <h2 class="text-2xl font-semibold text-hut-dark">{{ $restaurant->name }}</h2>
            <p class="text-sm text-gray-500">{{ $restaurant->address }}</p>
            <p class="mt-2 text-sm text-gray-600">Phone: {{ $restaurant->phone }} • Email: {{ $restaurant->email }}</p>
        </div>
        <div class="space-y-3 md:text-right">
            @php
                $restLogo = null;
                if ($restaurant->logo_path) {
                    if (file_exists(public_path('images/'.$restaurant->logo_path))) {
                        $restLogo = asset('images/'.$restaurant->logo_path);
                    } elseif (file_exists(public_path($restaurant->logo_path))) {
                        $restLogo = asset($restaurant->logo_path);
                    } else {
                        $restLogo = asset('storage/'.$restaurant->logo_path);
                    }
                }
            @endphp
            @if($restLogo)
                <img src="{{ $restLogo }}" alt="{{ $restaurant->name }} logo" class="h-24 w-24 rounded-full object-contain border border-gray-200 bg-white p-1" />
            @endif
            <a href="{{ $restaurant->getPublicUrl() }}" target="_blank" class="inline-flex items-center justify-center rounded-lg bg-hut-green px-4 py-2 text-sm font-semibold text-white hover:bg-hut-green/90">View public menu</a>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Orders today</p>
        <p class="text-2xl font-display font-bold text-hut-dark">{{ $stats['orders_today'] }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Income today</p>
        <p class="text-2xl font-display font-bold text-hut-green">Rs. {{ number_format($stats['revenue_today']) }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Pending orders</p>
        <p class="text-2xl font-display font-bold text-hut-yellow-dark">{{ $stats['pending_orders'] }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Expenses today</p>
        <p class="text-2xl font-display font-bold text-hut-red">Rs. {{ number_format($stats['expenses_today']) }}</p>
    </div>
</div>

@php
    $growthValues = $stats['growth']->pluck('revenue')->all();
    $growthMax = max(1, ...$growthValues);
    $growthPoints = collect($growthValues)->map(function ($value, $index) use ($growthMax, $growthValues) {
        $x = count($growthValues) > 1 ? ($index / (count($growthValues) - 1)) * 100 : 50;
        $y = 96 - (((float) $value / $growthMax) * 82);
        return number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    })->implode(' ');
    $latestGrowth = (float) ($growthValues[count($growthValues) - 1] ?? 0);
    $previousGrowth = (float) ($growthValues[count($growthValues) - 8] ?? 0);
    $growthChange = $previousGrowth > 0 ? (($latestGrowth - $previousGrowth) / $previousGrowth) * 100 : null;
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(18rem,.8fr)] mb-6">
    <section class="rounded-2xl border border-slate-200 bg-slate-950 p-5 text-white shadow-lg">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">Business growth</p>
                <h3 class="mt-1 text-lg font-semibold">Revenue trend · last 30 days</h3>
                <p class="mt-1 text-xs text-slate-400">Sales revenue from completed orders</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-300">Rs. {{ number_format($latestGrowth) }}</p>
                @if($growthChange !== null)
                    <p class="text-xs {{ $growthChange >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $growthChange >= 0 ? '↑' : '↓' }} {{ number_format(abs($growthChange), 1) }}% vs previous week</p>
                @endif
            </div>
        </div>
        <div class="mt-5 h-44 rounded-xl bg-white/[.04] p-3">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-full w-full" role="img" aria-label="Revenue growth chart">
                <path d="M0 96H100 M0 55H100 M0 14H100" stroke="rgba(148,163,184,.18)" stroke-width=".5" vector-effect="non-scaling-stroke" />
                @if(count($growthValues) > 1)
                    <polyline points="{{ $growthPoints }}" fill="none" stroke="#86efac" stroke-width="2.5" vector-effect="non-scaling-stroke" />
                    <polygon points="0,100 {{ $growthPoints }} 100,100" fill="url(#growth-fill)" opacity=".28" />
                @endif
                <defs><linearGradient id="growth-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#86efac" /><stop offset="1" stop-color="#86efac" stop-opacity="0" /></linearGradient></defs>
            </svg>
        </div>
        <div class="mt-2 flex justify-between text-[10px] text-slate-500"><span>{{ $stats['growth']->first()['label'] ?? '' }}</span><span>Today</span></div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-gray-400">Cash position</p>
        <h3 class="mt-1 text-lg font-semibold text-hut-dark">Income and costs</h3>
        <div class="mt-5 space-y-4">
            @php($monthSummary = collect($stats['period_summaries'])->firstWhere('key', 'month'))
            <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Sales income</span><strong class="text-hut-green">Rs. {{ number_format($monthSummary['sales'] ?? 0) }}</strong></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Other income</span><strong class="text-hut-green">Rs. {{ number_format($monthSummary['other_income'] ?? 0) }}</strong></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Recorded expenses</span><strong class="text-hut-red">Rs. {{ number_format(($monthSummary['expense'] ?? 0) - ($monthSummary['salary'] ?? 0) - ($monthSummary['cash_out'] ?? 0)) }}</strong></div>
            <div class="flex items-center justify-between"><span class="text-sm text-gray-500">Salary paid</span><strong class="text-hut-red">Rs. {{ number_format($monthSummary['salary'] ?? 0) }}</strong></div>
            <div class="flex items-center justify-between border-t border-gray-100 pt-3"><span class="font-semibold text-hut-dark">Net profit</span><strong class="text-xl {{ ($monthSummary['profit'] ?? 0) >= 0 ? 'text-hut-green' : 'text-hut-red' }}">Rs. {{ number_format($monthSummary['profit'] ?? 0) }}</strong></div>
        </div>
    </section>
</div>

<div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide">Business Performance</p>
            <h3 class="text-lg font-semibold text-hut-dark">Income, expense and profit summary</h3>
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach($stats['period_summaries'] as $summary)
            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                <p class="text-sm font-semibold text-hut-dark">{{ $summary['label'] }}</p>
                <p class="mt-2 text-xs text-gray-500">Income</p>
                <p class="text-lg font-semibold text-hut-green">Rs. {{ number_format($summary['income']) }}</p>
                <p class="mt-2 text-xs text-gray-500">Expense</p>
                <p class="text-lg font-semibold text-hut-red">Rs. {{ number_format($summary['expense']) }}</p>
                <p class="mt-2 text-xs text-gray-500">Profit</p>
                <p class="text-lg font-semibold {{ $summary['profit'] >= 0 ? 'text-hut-green' : 'text-hut-red' }}">Rs. {{ number_format($summary['profit']) }}</p>
            </div>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Low stock items</p>
        <p class="text-2xl font-display font-bold {{ $stats['low_stock_items'] > 0 ? 'text-hut-red' : 'text-hut-dark' }}">{{ $stats['low_stock_items'] }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Monthly net profit</p>
        <p class="text-2xl font-display font-bold {{ $stats['monthly_net_profit'] >= 0 ? 'text-hut-green' : 'text-hut-red' }}">Rs. {{ number_format($stats['monthly_net_profit']) }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">New customer feedback</p>
        <p class="text-2xl font-display font-bold text-hut-dark">{{ $stats['new_customer_feedback'] }}</p>
    </div>
</div>

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-gray-400">Stock control</p>
            <h3 class="mt-1 text-lg font-semibold text-hut-dark">Inventory movement and value</h3>
            <p class="mt-1 text-xs text-gray-500">Based on recorded purchases, sales, returns, damage, and current item cost.</p>
        </div>
        <div class="rounded-xl bg-blue-50 px-4 py-3 text-right"><p class="text-xs text-blue-700">Current stock value</p><p class="text-lg font-bold text-blue-800">Rs. {{ number_format($stats['stock_value']) }}</p></div>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl bg-emerald-50 p-4"><p class="text-xs text-emerald-700">Received</p><p class="mt-1 text-xl font-bold text-emerald-800">{{ number_format($stats['stock_received'], 3) }}</p><p class="text-[11px] text-emerald-700">units added</p></div>
        <div class="rounded-xl bg-amber-50 p-4"><p class="text-xs text-amber-700">Sold</p><p class="mt-1 text-xl font-bold text-amber-800">{{ number_format($stats['stock_sold'], 3) }}</p><p class="text-[11px] text-amber-700">units moved</p></div>
        <div class="rounded-xl bg-rose-50 p-4"><p class="text-xs text-rose-700">Damaged / expired</p><p class="mt-1 text-xl font-bold text-rose-800">{{ number_format($stats['stock_damaged'], 3) }}</p><p class="text-[11px] text-rose-700">units lost</p></div>
        <div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Low stock</p><p class="mt-1 text-xl font-bold text-slate-800">{{ number_format($stats['low_stock_items']) }}</p><p class="text-[11px] text-slate-500">items to review</p></div>
    </div>
    @if($stats['stock_movements']->isNotEmpty())
        <div class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-100">
            @foreach($stats['stock_movements'] as $movement)
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm"><span class="font-medium text-hut-dark">{{ $movement->menuItem->name ?? 'Item' }}</span><span class="text-gray-500">{{ number_format($movement->sold_qty, 3) }} sold · {{ number_format($movement->received_qty, 3) }} received</span></div>
            @endforeach
        </div>
    @endif
</div>

@if($bestSeller)
<div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-6">
    <p class="text-sm text-gray-500">🔥 Best seller today: <span class="font-semibold text-hut-dark">{{ $bestSeller->item_name }}</span> ({{ $bestSeller->total_qty }} sold)</p>
</div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 font-display font-semibold text-hut-dark">Recent orders</div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-4 py-2 text-left">Order #</th>
                <th class="px-4 py-2 text-left">Customer</th>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-left">Status</th>
                <th class="px-4 py-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($recentOrders as $order)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('manager.orders.show', $order) }}'">
                <td class="px-4 py-2 font-medium text-hut-dark">{{ $order->order_number }}</td>
                <td class="px-4 py-2">{{ $order->customer_name }}</td>
                <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $order->order_type) }}</td>
                <td class="px-4 py-2"><span class="badge-status bg-hut-yellow/20 text-hut-yellow-dark">{{ $order->status_label }}</span></td>
                <td class="px-4 py-2 text-right font-medium">Rs. {{ number_format($order->total) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
