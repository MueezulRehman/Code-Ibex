@extends('manager.layout.master')
@section('title', 'Inpatient Medication')
@section('page-content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="page-eyebrow">Pharmacy & nursing</p><h1 class="page-title">Inpatient medication</h1><p class="page-description">Prescribe, dispense, and document each admission-linked dose from one controlled workflow.</p></div>
        <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-800"><span class="font-semibold">Workflow:</span> Order → Dispense → Administer</div>
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($canOrder)
        <form method="POST" action="{{ route('manager.hospital-medication.orders.store') }}" class="page-card grid gap-4 p-5 shadow-sm md:grid-cols-4">
            @csrf
            <div class="hospital-dropdown" data-hospital-dropdown>
                <label class="sr-only" for="hospital-admission-value">Admission</label>
                <input type="hidden" id="hospital-admission-value" name="hospital_admission_id" required>
                <button type="button" class="hospital-dropdown__trigger" aria-haspopup="listbox" aria-expanded="false" onclick="toggleHospitalDropdown(this)">Admission <span aria-hidden="true">⌄</span></button>
                <div class="hospital-dropdown__menu" role="listbox" hidden>
                    <button type="button" class="hospital-dropdown__option" role="option" data-value="" onclick="selectHospitalDropdownOption(this)">Admission</button>
                    @foreach($admissions as $admission)
                        <button type="button" class="hospital-dropdown__option" role="option" data-value="{{ $admission->id }}" onclick="selectHospitalDropdownOption(this)">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</button>
                    @endforeach
                </div>
            </div>
            <div class="hospital-dropdown" data-hospital-dropdown>
                <label class="sr-only" for="hospital-medicine-value">Medicine</label>
                <input type="hidden" id="hospital-medicine-value" name="medicine_id" required>
                <button type="button" class="hospital-dropdown__trigger" aria-haspopup="listbox" aria-expanded="false" onclick="toggleHospitalDropdown(this)">Medicine <span aria-hidden="true">⌄</span></button>
                <div class="hospital-dropdown__menu" role="listbox" hidden>
                    <button type="button" class="hospital-dropdown__option" role="option" data-value="" onclick="selectHospitalDropdownOption(this)">Medicine</button>
                    @foreach($medicines as $medicine)
                        <button type="button" class="hospital-dropdown__option" role="option" data-value="{{ $medicine->id }}" onclick="selectHospitalDropdownOption(this)">{{ $medicine->name }}@if($medicine->strength) ({{ $medicine->strength }}) @endif</button>
                    @endforeach
                </div>
            </div>
            <div class="page-field"><label for="med-dose">Dose</label><input id="med-dose" name="dose" required maxlength="100" placeholder="e.g. 500 mg"></div>
            <div class="page-field"><label for="med-route">Route</label><input id="med-route" name="route" required maxlength="50" placeholder="e.g. oral"></div>
            <div class="page-field"><label for="med-frequency">Frequency</label><input id="med-frequency" name="frequency" maxlength="100" placeholder="e.g. every 8 hours"></div>
            <div class="page-field"><label for="med-start-date">Start</label><div class="date-time-picker" data-date-time-picker><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input id="med-start-date" type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input value="{{ now()->format('m/d/Y') }}" placeholder="Select date" required></div><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input value="{{ now()->format('H:i:s') }}" required></div><input type="hidden" name="scheduled_start_at" data-date-time-value value="{{ now()->format('Y-m-d H:i:s') }}"></div></div>
            <div class="page-field"><label for="med-end-date">End</label><div class="date-time-picker" data-date-time-picker><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input id="med-end-date" type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input placeholder="Select date"></div><div class="date-field"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input></div><input type="hidden" name="scheduled_end_at" data-date-time-value></div></div>
            <div class="hospital-order-submit flex items-end"><button class="w-full rounded-lg bg-hut-dark px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">Create medication order</button></div>
            <div class="page-field md:col-span-4"><label for="med-notes">Clinical notes</label><textarea id="med-notes" name="notes" rows="3" placeholder="Optional clinical instructions or precautions"></textarea></div>
        </form>
    @endif
    <div class="page-card overflow-x-auto shadow-sm">
        <table class="medication-table w-full min-w-[980px] text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Order</th><th class="px-3 py-3">Admission</th><th class="px-3 py-3">Medicine / dose</th><th class="px-3 py-3">Window</th><th class="px-3 py-3">Administration</th><th class="px-5 py-3">Record dose</th></tr></thead>
            <tbody class="divide-y">
            @forelse($orders as $order)
                <tr>
                    <td class="px-5 py-3"><div class="font-semibold">{{ $order->order_number }}</div><div class="text-xs text-gray-500">{{ $order->status }}</div></td>
                    <td class="px-3 py-3">{{ $order->admission?->admission_number }}<div class="text-xs text-gray-500">{{ $order->patient?->name }}</div></td>
                    <td class="px-3 py-3">{{ $order->medicine?->name }}<div class="text-xs text-gray-500">{{ $order->dose }} via {{ $order->route }}</div></td>
                    <td class="px-3 py-3">{{ $order->scheduled_start_at?->format('d M Y H:i') }}@if($order->scheduled_end_at)<div class="text-xs text-gray-500">to {{ $order->scheduled_end_at->format('d M Y H:i') }}</div>@endif</td>
                    <td class="px-3 py-3">
                        @if($order->fulfillment)
                            <div class="text-xs font-semibold capitalize">{{ str_replace('_', ' ', $order->fulfillment->status) }}: {{ number_format((float) $order->fulfillment->quantity_dispensed, 3) }}</div>
                            <div class="text-xs text-gray-500">Required {{ number_format((float) $order->fulfillment->quantity_requested, 3) }}</div>
                            <div class="text-xs text-gray-500">Remaining {{ number_format(max((float) $order->fulfillment->quantity_requested - (float) $order->fulfillment->quantity_dispensed, 0), 3) }}</div>
                            @foreach($order->fulfillment->batches as $allocation)
                                <div class="text-xs text-gray-500">{{ $allocation->batch?->batch_number ?: 'unlisted' }}: {{ number_format((float) $allocation->quantity_dispensed, 3) }}</div>
                            @endforeach
                        @else
                            <span class="text-xs text-amber-600">Awaiting pharmacy</span>
                        @endif
                        <div class="mt-2 space-y-1">@forelse($order->administrations as $administration)<div class="text-xs"><span class="font-semibold capitalize">{{ $administration->status }}</span> — {{ $administration->scheduled_at?->format('d M H:i') }}@if($administration->administered_at) ({{ $administration->administered_at->format('H:i') }})@endif</div>@empty<span class="text-xs text-gray-400">None recorded</span>@endforelse</div>
                    </td>
                    <td class="px-5 py-3">
                        @if($canAdminister && $order->status === 'active')
                            <form method="POST" action="{{ route('manager.hospital-medication.administrations.store', $order) }}" class="space-y-2 min-w-56">@csrf
                                <input name="dose" required value="{{ $order->dose }}" class="w-full rounded border-gray-200 text-xs">
                                <input name="route" required value="{{ $order->route }}" class="w-full rounded border-gray-200 text-xs">
                                <label class="form-label">Scheduled dose</label><div class="date-time-picker date-time-picker--compact" data-date-time-picker><div class="date-field date-field--compact"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input value="{{ $order->scheduled_start_at?->format('m/d/Y') }}" required></div><div class="date-field date-field--compact"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input value="{{ $order->scheduled_start_at?->format('H:i:s') }}" required></div><input type="hidden" name="scheduled_at" data-date-time-value value="{{ $order->scheduled_start_at?->format('Y-m-d H:i:s') }}"></div>
                                <label class="form-label">Status</label><select name="status" required class="w-full text-xs"><option value="administered">Administered</option><option value="omitted">Omitted</option><option value="refused">Refused</option><option value="cancelled">Cancelled</option><option value="scheduled">Scheduled</option></select>
                                <label class="form-label">Administered at</label><div class="date-time-picker date-time-picker--compact" data-date-time-picker><div class="date-field date-field--compact"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 4v3M18 4v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12H4V7a1 1 0 0 1 1-1Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><input type="text" datepicker datepicker-buttons datepicker-autoselect-today data-date-input placeholder="Select date"></div><div class="date-field date-field--compact"><span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke-width="1.8" stroke-linecap="round"/></svg></span><input type="time" step="1" data-time-input></div><input type="hidden" name="administered_at" data-date-time-value></div>
                                <input name="reason" placeholder="Reason when not given" class="w-full rounded border-gray-200 text-xs">
                                <input name="idempotency_key" placeholder="Idempotency key (optional)" class="w-full rounded border-gray-200 text-xs">
                                <textarea name="notes" placeholder="Notes (optional)" class="w-full rounded border-gray-200 text-xs"></textarea>
                                <button class="rounded bg-hut-green px-3 py-2 text-xs font-semibold text-white">Record</button>
                            </form>
                        @elseif($canFulfill && (!$order->fulfillment || $order->fulfillment->status === 'partially_dispensed'))
                            <form method="POST" action="{{ route('manager.hospital-medication.fulfill', $order) }}" class="space-y-2 min-w-40">@csrf
                                <input type="number" name="quantity" min="0.001" step="0.001" required placeholder="{{ $order->fulfillment ? 'Total required quantity' : 'Quantity' }}" class="w-full rounded border border-gray-200 text-xs">
                                <input name="notes" placeholder="Pharmacy note (optional)" class="w-full rounded border border-gray-200 text-xs">
                                <button class="rounded bg-hut-dark px-3 py-2 text-xs font-semibold text-white">Dispense stock</button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">View only</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No inpatient medication orders found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4">{{ $orders->links() }}</div>
    </div>
</div>
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite-datepicker@1.3.2/dist/css/datepicker.min.css">
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flowbite-datepicker@1.3.2/dist/js/datepicker-full.min.js"></script>
<script>
document.querySelectorAll('[data-date-input]').forEach(function (input) {
    if (window.Datepicker && !input.datepicker) {
        input.datepicker = new Datepicker(input, { autoselectToday: true, todayBtn: true, clearBtn: true, format: 'mm/dd/yyyy' });
    }
    input.addEventListener('focus', function () { this.datepicker?.show(); });
    input.addEventListener('click', function () { this.datepicker?.show(); });
});
document.querySelectorAll('[data-date-time-picker]').forEach(function (picker) {
    var date = picker.querySelector('[data-date-input]'), time = picker.querySelector('[data-time-input]'), target = picker.querySelector('[data-date-time-value]');
    function sync() { var parts = date.value.split('/'); if (parts.length === 3 && time.value) target.value = parts[2] + '-' + parts[0].padStart(2, '0') + '-' + parts[1].padStart(2, '0') + ' ' + (time.value.length === 5 ? time.value + ':00' : time.value); }
    date.addEventListener('changeDate', function () { sync(); window.setTimeout(function () { date.datepicker?.hide(); }, 0); }); date.addEventListener('change', sync); time.addEventListener('change', sync);
    picker.closest('form').addEventListener('submit', sync);
});
document.addEventListener('focusin', function (event) {
    if (event.target.matches('[data-date-input]')) window.setTimeout(function () { event.target.datepicker?.show(); }, 0);
});
function toggleHospitalDropdown(trigger) {
    var dropdown = trigger.closest('[data-hospital-dropdown]');
    var menu = dropdown.querySelector('.hospital-dropdown__menu');
    var open = trigger.getAttribute('aria-expanded') === 'true';
    document.querySelectorAll('[data-hospital-dropdown]').forEach(function (other) {
        other.querySelector('.hospital-dropdown__trigger').setAttribute('aria-expanded', 'false');
        other.querySelector('.hospital-dropdown__menu').hidden = true;
    });
    trigger.setAttribute('aria-expanded', String(!open));
    menu.hidden = open;
}
function selectHospitalDropdownOption(option) {
    var dropdown = option.closest('[data-hospital-dropdown]');
    dropdown.querySelector('input[type="hidden"]').value = option.dataset.value;
    dropdown.querySelector('.hospital-dropdown__trigger').firstChild.textContent = option.textContent.trim() + ' ';
    dropdown.querySelector('.hospital-dropdown__trigger').setAttribute('aria-expanded', 'false');
    dropdown.querySelector('.hospital-dropdown__menu').hidden = true;
}
</script>
@endpush
@endsection
