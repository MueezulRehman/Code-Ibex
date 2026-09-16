@extends('manager.layout.master')
@section('title', 'Admission Invoice')
@section('page-content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inpatient invoice</p>
            <h1 class="mt-1 text-xl font-bold text-hut-dark">{{ $admission->admission_number }}</h1>
            <p class="text-sm text-gray-600">{{ $admission->patient?->name }} · {{ ucfirst($admission->status) }}</p>
        </div>
        <div class="flex gap-2 text-sm">
            <button type="button" onclick="window.print()" class="rounded bg-gray-900 px-3 py-2 font-semibold text-white">Print</button>
            <a href="{{ route('manager.hospital-billing.index', ['hospital_admission_id' => $admission->id]) }}" class="rounded border border-gray-200 px-3 py-2 text-gray-700">Billing</a>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Gross', $summary['gross']], ['Settled', $summary['settled']], ['Outstanding', $summary['outstanding']], ['Draft', $summary['draft']]] as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $metric[0] }}</div><div class="mt-1 text-xl font-bold text-hut-dark">{{ number_format($metric[1], 2) }}</div></div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Invoice</th><th class="px-3 py-3">Service</th><th class="px-3 py-3">Quantity</th><th class="px-3 py-3">Amount</th><th class="px-3 py-3">Settled</th><th class="px-3 py-3">Status</th></tr></thead>
            <tbody class="divide-y">
                @forelse($billings as $billing)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $billing->invoice_number }}</td>
                        <td class="px-3 py-3"><span class="font-semibold capitalize">{{ $billing->charge_type }}</span><span class="block text-xs text-gray-500">{{ $billing->description }}</span></td>
                        <td class="px-3 py-3">{{ number_format((float) $billing->quantity, 2) }}</td>
                        <td class="px-3 py-3">{{ number_format((float) $billing->total_amount, 2) }}</td>
                        <td class="px-3 py-3">{{ number_format($billing->settledAmount(), 2) }}</td>
                        <td class="px-3 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold capitalize">{{ $billing->status }}</span>@if($billing->insurance_claim_id)<span class="mt-1 block text-[10px] text-blue-700">Insurance linked</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No charges recorded for this admission.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
