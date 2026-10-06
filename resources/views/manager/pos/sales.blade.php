@extends('manager.layout.master')
@section('title', 'Sales History')

@section('page-content')

<div class="flex justify-between items-center mb-6">
    <h2 class="text-lg font-display font-bold text-hut-dark">Sales History</h2>
    <a href="{{ route('manager.pos.index') }}" class="bg-hut-green text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-hut-green/90">🧾 New Sale</a>
</div>

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Sales today</p>
        <p class="text-2xl font-display font-bold text-hut-dark">{{ $summary['today_count'] }}</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-400">Revenue today</p>
        <p class="text-2xl font-display font-bold text-hut-green">Rs. {{ number_format($summary['today_total']) }}</p>
    </div>
</div>

<form method="GET" class="flex gap-3 mb-4 items-end flex-wrap">
    <div>
        <label class="text-xs text-gray-500 block mb-1">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block mb-1">Cashier</label>
        <select name="cashier_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">All cashiers</option>
            @foreach($cashiers as $c)
                <option value="{{ $c->id }}" @selected(request('cashier_id') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm">Filter</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b">
            <tr>
                <th class="px-4 py-3 text-left">Receipt #</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Customer</th>
                <th class="px-4 py-3 text-left">Cashier</th>
                <th class="px-4 py-3 text-left">Payment</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-right"><span title="Open printable receipt">Receipt</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($sales as $sale)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-hut-dark">{{ $sale->invoice_number ?? $sale->order_number }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $sale->created_at->format('d M Y, h:i A') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $sale->customer_name ?? 'Walk-in Customer' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $sale->cashier->name ?? '—' }}</td>
                <td class="px-4 py-3 capitalize text-gray-600">{{ $sale->payment_method }}</td>
                <td class="px-4 py-3 text-right font-medium">Rs. {{ number_format($sale->total) }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('manager.pos.receipt', ['order' => $sale->id]) }}" target="_blank"
                        title="View receipt" aria-label="View receipt"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-hut-green hover:bg-hut-green/10 hover:text-hut-dark">
                        <x-icons.eye class="h-4 w-4" />
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No POS sales recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $sales->links() }}
</div>

@endsection
