@extends('manager.layout.master')
@section('title', 'Hospital Billing')
@section('page-content')
<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-display text-base font-bold text-hut-dark">Hospital Billing</h1>
                <p class="mb-4 text-xs text-gray-500">Capture inpatient charges and track posting and payment.</p>
            </div>
            @if($canManage)
                <div class="text-right text-xs text-gray-500">
                    <div class="font-semibold text-hut-dark">Current tenant reconciliation</div>
                    <div>{{ number_format((float) ($reconciliationSummary['gross'] ?? 0), 2) }} gross / {{ number_format((float) ($reconciliationSummary['paid'] ?? 0), 2) }} paid / {{ number_format((float) ($reconciliationSummary['outstanding'] ?? 0), 2) }} outstanding</div>
                </div>
            @endif
        </div>

        @if($canManage)
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                @foreach([
                    ['label' => 'Gross', 'value' => $reconciliationSummary['gross'] ?? 0],
                    ['label' => 'Posted', 'value' => $reconciliationSummary['posted'] ?? 0],
                    ['label' => 'Paid', 'value' => $reconciliationSummary['paid'] ?? 0],
                    ['label' => 'Outstanding', 'value' => $reconciliationSummary['outstanding'] ?? 0],
                    ['label' => 'Draft', 'value' => $reconciliationSummary['draft'] ?? 0],
                    ['label' => 'Void', 'value' => $reconciliationSummary['void'] ?? 0],
                ] as $metric)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $metric['label'] }}</div>
                        <div class="mt-1 text-lg font-bold text-hut-dark">{{ number_format((float) $metric['value'], 2) }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($canManage)
            <form method="GET" action="{{ route('manager.hospital-billing.index') }}" class="mt-5 grid gap-2 md:grid-cols-5">
                <select name="status" class="rounded border-gray-200 text-sm">
                    <option value="all" @selected($filterStatus === 'all')>All statuses</option>
                    @foreach(['draft','posted','paid','void'] as $status)
                        <option value="{{ $status }}" @selected($filterStatus === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="hospital_admission_id" class="rounded border-gray-200 text-sm">
                    <option value="">All admissions</option>
                    @foreach($admissions as $admission)
                        <option value="{{ $admission->id }}" @selected((string) $filterAdmissionId === (string) $admission->id)>{{ $admission->admission_number }} - {{ $admission->patient?->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filterDateFrom }}" class="rounded border-gray-200 text-sm" placeholder="From date">
                <input type="date" name="date_to" value="{{ $filterDateTo }}" class="rounded border-gray-200 text-sm" placeholder="To date">
                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Filter</button>
                    <a href="{{ route('manager.hospital-billing.index') }}" class="rounded border border-gray-200 px-3 py-2 text-sm text-gray-700">Reset</a>
                </div>
            </form>
        @endif

        @if($canManage)
            <form method="POST" action="{{ route('manager.hospital-billing.store') }}" class="mt-5 grid gap-2 md:grid-cols-4">@csrf
                <select name="hospital_admission_id" required class="rounded border-gray-200 text-sm"><option value="">Admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} - {{ $admission->patient?->name }}</option>@endforeach</select>
                <select name="charge_type" required class="rounded border-gray-200 text-sm"><option value="">Charge type</option>@foreach(['admission','bed','nursing','laboratory','radiology','surgery','medication','other'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select>
                <input name="description" required maxlength="1000" placeholder="Description" class="rounded border-gray-200 text-sm">
                <input name="quantity" type="number" min="0.01" step="0.01" required placeholder="Quantity" class="rounded border-gray-200 text-sm">
                <input name="unit_amount" type="number" min="0.01" step="0.01" required placeholder="Unit amount" class="rounded border-gray-200 text-sm">
                <input name="charged_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-sm">
                <input name="notes" placeholder="Notes" class="rounded border-gray-200 text-sm md:col-span-2">
                <button class="rounded bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark md:col-span-1">Add draft</button>
            </form>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Admission / Patient</th>
                    <th class="px-3 py-3">Charge</th>
                    <th class="px-3 py-3">Amount</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($billings as $billing)
                    <tr>
                        <td class="px-4 py-3">
                            {{ $billing->admission?->admission_number }}
                            <div class="text-xs text-gray-500">{{ $billing->patient?->name }}</div>
                        </td>
                        <td class="px-3 py-3">
                            {{ ucfirst($billing->charge_type) }}
                            <div class="text-xs text-gray-500">{{ $billing->description }}</div>
                            @if($billing->status === 'paid' && $billing->payment_reference)
                                <div class="mt-1 text-[11px] text-gray-500">Ref: {{ $billing->payment_reference }}</div>
                            @endif
                            @if($billing->payment_notes)
                                <div class="mt-1 text-[11px] text-gray-500">{{ \Illuminate\Support\Str::limit($billing->payment_notes, 80) }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            {{ number_format((float) $billing->total_amount, 2) }}
                            @if($billing->status === 'paid')
                                <div class="mt-1 text-[11px] text-gray-500">Paid {{ $billing->paid_at?->format('d M Y H:i') ?? '?' }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            <span class="rounded-full bg-blue-50 px-2 py-1 text-xs capitalize text-blue-700">{{ $billing->status }}</span>
                            @if($billing->status === 'paid')
                                <div class="mt-2 text-[11px] text-gray-500">Payer: {{ $billing->paidBy?->name ?? 'Unknown' }}<br>Time: {{ $billing->paid_at?->format('d M Y H:i') ?? '?' }}<br>Reference: {{ $billing->payment_reference ?: '?' }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($canManage)
                                <div class="flex justify-end gap-2">
                                    @if($billing->status === 'draft')
                                        <details class="text-left">
                                            <summary class="cursor-pointer text-xs font-semibold text-gray-700">Edit</summary>
                                            <form method="POST" action="{{ route('manager.hospital-billing.update', $billing) }}" class="mt-2 grid gap-1">@csrf @method('PATCH')
                                                <select name="charge_type" class="rounded border-gray-200 text-xs">@foreach(['admission','bed','nursing','laboratory','radiology','surgery','medication','other'] as $type)<option value="{{ $type }}" @selected($billing->charge_type === $type)>{{ ucfirst($type) }}</option>@endforeach</select>
                                                <input name="description" value="{{ $billing->description }}" required maxlength="1000" class="rounded border-gray-200 text-xs">
                                                <input name="quantity" type="number" min="0.01" step="0.01" value="{{ $billing->quantity }}" required class="rounded border-gray-200 text-xs">
                                                <input name="unit_amount" type="number" min="0.01" step="0.01" value="{{ $billing->unit_amount }}" required class="rounded border-gray-200 text-xs">
                                                <input name="charged_at" type="datetime-local" value="{{ $billing->charged_at?->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs">
                                                <input name="notes" value="{{ $billing->notes }}" class="rounded border-gray-200 text-xs">
                                                <button class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold">Save</button>
                                            </form>
                                        </details>
                                        <form method="POST" action="{{ route('manager.hospital-billing.post', $billing) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-blue-700">Post</button></form>
                                    @endif
                                    @if($billing->status === 'posted')
                                        <details class="text-left">
                                            <summary class="cursor-pointer text-xs font-semibold text-green-700">Pay</summary>
                                            <form method="POST" action="{{ route('manager.hospital-billing.pay', $billing) }}" class="mt-2 grid gap-2 w-60">@csrf @method('PATCH')
                                                <input name="payment_reference" required maxlength="255" placeholder="Payment reference" class="rounded border-gray-200 text-xs">
                                                <textarea name="payment_notes" rows="2" maxlength="5000" placeholder="Payment notes (optional)" class="rounded border-gray-200 text-xs"></textarea>
                                                <button class="rounded bg-green-700 px-2 py-1 text-xs font-semibold text-white">Record payment</button>
                                            </form>
                                        </details>
                                    @endif
                                    @if(in_array($billing->status, ['draft', 'posted'], true))
                                        <form method="POST" action="{{ route('manager.hospital-billing.void', $billing) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-red-700">Void</button></form>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No billing records found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $billings->links() }}</div>
    </div>
</div>
@endsection
