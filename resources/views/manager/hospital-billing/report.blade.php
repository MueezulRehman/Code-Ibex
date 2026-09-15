@extends('manager.layout.master')
@section('title', 'Hospital Billing Report')
@section('page-content')
<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="font-display text-base font-bold text-hut-dark">Hospital Billing Report</h1>
                <p class="text-xs text-gray-500">Manager-only tenant report for inpatient charge and insurance reconciliation.</p>
            </div>
            <div class="flex gap-2 text-sm">
                <a href="{{ route('manager.hospital-billing.report.export', array_merge($filters, ['format' => 'csv'])) }}" class="rounded bg-hut-yellow px-3 py-2 font-semibold text-hut-dark">Export CSV</a>
                <a href="{{ route('manager.hospital-billing.index') }}" class="rounded border border-gray-200 px-3 py-2 text-gray-700">Billing</a>
            </div>
        </div>

        <form method="GET" action="{{ route('manager.hospital-billing.report') }}" class="mt-5 grid gap-2 md:grid-cols-3 xl:grid-cols-6">
            <select name="status" class="rounded border-gray-200 text-sm"><option value="all">All statuses</option>@foreach(['draft','posted','paid','void'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? 'all') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <select name="charge_type" class="rounded border-gray-200 text-sm"><option value="all">All charge types</option>@foreach(['admission','bed','nursing','laboratory','radiology','surgery','medication','other'] as $type)<option value="{{ $type }}" @selected(($filters['charge_type'] ?? 'all') === $type)>{{ ucfirst($type) }}</option>@endforeach</select>
            <select name="payment_method" class="rounded border-gray-200 text-sm"><option value="all">All payment methods</option>@foreach(['cash','card','bank_transfer','insurance','other'] as $method)<option value="{{ $method }}" @selected(($filters['payment_method'] ?? 'all') === $method)>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>@endforeach</select>
            <select name="insurance_linked" class="rounded border-gray-200 text-sm"><option value="all">All insurance links</option><option value="linked" @selected(($filters['insurance_linked'] ?? 'all') === 'linked')>Insurance linked</option><option value="unlinked" @selected(($filters['insurance_linked'] ?? 'all') === 'unlinked')>Not linked</option></select>
            <select name="hospital_admission_id" class="rounded border-gray-200 text-sm"><option value="">All admissions</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}" @selected((string) ($filters['hospital_admission_id'] ?? '') === (string) $admission->id)>{{ $admission->admission_number }} - {{ $admission->patient?->name }}</option>@endforeach</select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded border-gray-200 text-sm">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded border-gray-200 text-sm">
            <div class="flex gap-2 xl:col-span-6"><button class="rounded bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Apply filters</button><a href="{{ route('manager.hospital-billing.report') }}" class="rounded border border-gray-200 px-3 py-2 text-sm text-gray-700">Reset</a></div>
        </form>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([['Gross', $totals['gross']], ['Outstanding', $totals['outstanding']], ['Insurance linked', $totals['insurance_linked']], ['Insurance outstanding', $totals['insurance_outstanding']]] as $metric)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $metric[0] }}</div><div class="mt-1 text-xl font-bold text-hut-dark">{{ number_format((float) $metric[1], 2) }}</div></div>
        @endforeach
    </div>

    @foreach([
        'Totals by status' => $statusTotals,
        'Totals by charge type' => $chargeTypeTotals,
        'Totals by payment method' => $paymentMethodTotals,
        'Totals by admission' => $admissionTotals,
        'Totals by charged date' => $dateTotals,
    ] as $heading => $rows)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-hut-dark">{{ $heading }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm"><thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Group</th><th class="px-3 py-2">Charges</th><th class="px-3 py-2 text-right">Amount</th></tr></thead><tbody class="divide-y">
                    @forelse($rows as $label => $row)<tr><td class="px-3 py-2 capitalize">{{ str_replace('_', ' ', $label) }}</td><td class="px-3 py-2">{{ $row['count'] }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $row['amount'], 2) }}</td></tr>@empty<tr><td colspan="3" class="px-3 py-3 text-gray-500">No charges match these filters.</td></tr>@endforelse
                </tbody></table>
            </div>
        </div>
    @endforeach
</div>
@endsection
