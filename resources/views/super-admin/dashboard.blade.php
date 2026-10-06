@extends('super-admin.layout.master')
@section('title', 'Dashboard')

@section('page-content')
    <p class="mb-6 text-sm text-gray-500">CodeIbex platform-owner overview across all registered businesses.</p>

    <form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
        <label class="text-xs font-semibold text-gray-500">From
            <input type="date" name="from" value="{{ $portfolioSummary['from'] }}"
                class="mt-1 block rounded-lg border border-gray-200 px-3 py-2 text-sm">
        </label>
        <label class="text-xs font-semibold text-gray-500">To
            <input type="date" name="to" value="{{ $portfolioSummary['to'] }}"
                class="mt-1 block rounded-lg border border-gray-200 px-3 py-2 text-sm">
        </label>
        <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Refresh portfolio</button>
    </form>

    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Platform Owner Portfolio Summary</p>
    <div class="stagger-children mb-8 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Active businesses</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $platformStats['total_active_businesses'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Trials expiring this week</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $platformStats['trials_expiring_this_week'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Overdue subscriptions</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $platformStats['overdue_subscriptions'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Subscription revenue</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>Rs.
                {{ number_format($platformStats['revenue_this_month']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">New feedback</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ $platformStats['new_feedback'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Portfolio sales</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>Rs. {{ number_format($portfolioSummary['totals']['sales']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Portfolio orders</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ number_format($portfolioSummary['totals']['orders']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Portfolio branches</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ number_format($portfolioSummary['totals']['branches']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-400">Low stock rows</p>
            <p class="text-2xl font-display font-bold text-hut-dark" data-count-up>{{ number_format($portfolioSummary['totals']['low_stock']) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3 font-display font-semibold text-hut-dark">Business Activity Report
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Business</th>
                        <th class="px-4 py-3 text-left">Business status</th>
                        <th class="px-4 py-3 text-left">Manager login</th>
                        <th class="px-4 py-3 text-left">Last login</th>
                        <th class="px-4 py-3 text-left">Active duration</th>
                        <th class="px-4 py-3 text-left">Subscription</th>
                        <th class="px-4 py-3 text-left">Database</th>
                        <th class="px-4 py-3 text-left">Period sales</th>
                        <th class="px-4 py-3 text-left">Orders</th>
                        <th class="px-4 py-3 text-left">Branches</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($businessReports as $report)
                        @php
                            $business = $report['restaurant'];
                            $duration = $report['active_since'] ? $report['active_since']->diffForHumans(null, true) : null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-hut-dark">{{ $business->name }}</p>
                                <p class="text-xs text-gray-400">/{{ $business->slug }} · {{ $report['manager_count'] }}
                                    manager(s)</p>
                                @if($report['portfolio']['status'] === 'available' && $report['portfolio']['branch_reports']->isNotEmpty())
                                    <details class="mt-2 text-xs">
                                        <summary class="cursor-pointer font-semibold text-hut-dark">View branch performance</summary>
                                        <div class="mt-2 space-y-1 pl-2 text-gray-500">
                                            @foreach($report['portfolio']['branch_reports'] as $branchReport)
                                                <div class="flex justify-between gap-4">
                                                    <span>{{ $branchReport['branch']->name }}</span>
                                                    <span>{{ $branchReport['orders'] }} orders · Rs. {{ number_format($branchReport['sales']) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </td>
                            <td class="px-4 py-3"><span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $business->status === 'active' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($business->status) }}</span>@if($business->activated_at)
                                        <p class="mt-1 text-xs text-gray-400">Since {{ $business->activated_at->format('M d, Y') }}</p>
                                    @endif
                            </td>
                            <td class="px-4 py-3"><span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $report['logged_in'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $report['logged_in'] ? 'Logged in' : 'Offline' }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $report['last_login_at']?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $report['logged_in'] && $duration ? $duration : 'Not active' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ ucfirst($report['subscription_status']) }} ·
                                {{ $report['plan_name'] }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $business->hasTenantDatabase() ? 'Tenant DB' : 'Shared DB' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($report['portfolio']['status'] === 'available')
                                    Rs. {{ number_format($report['portfolio']['sales']) }}
                                @else
                                    <span class="text-amber-600">Unavailable</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $report['portfolio']['status'] === 'available' ? $report['portfolio']['orders'] : '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $report['portfolio']['status'] === 'available' ? $report['portfolio']['branches'] : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-6 text-center text-gray-500">No businesses registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection