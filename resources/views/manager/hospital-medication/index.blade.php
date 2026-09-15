@extends('manager.layout.master')
@section('title', 'Inpatient Medication')
@section('page-content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-lg font-bold text-hut-dark">Inpatient Medication</h1>
        <p class="text-sm text-gray-500">Admission-linked orders and medication administration records.</p>
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($canOrder)
        <form method="POST" action="{{ route('manager.hospital-medication.orders.store') }}" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 md:grid-cols-4">
            @csrf
            <select name="hospital_admission_id" required class="rounded-lg border-gray-200"><option value="">Admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach</select>
            <select name="medicine_id" required class="rounded-lg border-gray-200"><option value="">Medicine</option>@foreach($medicines as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->name }}@if($medicine->strength) ({{ $medicine->strength }}) @endif</option>@endforeach</select>
            <input name="dose" required maxlength="100" placeholder="Dose (e.g. 500 mg)" class="rounded-lg border-gray-200">
            <input name="route" required maxlength="50" placeholder="Route (e.g. oral)" class="rounded-lg border-gray-200">
            <input name="frequency" maxlength="100" placeholder="Frequency (optional)" class="rounded-lg border-gray-200">
            <input type="datetime-local" name="scheduled_start_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-lg border-gray-200">
            <input type="datetime-local" name="scheduled_end_at" class="rounded-lg border-gray-200">
            <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Create order</button>
            <textarea name="notes" placeholder="Clinical notes (optional)" class="md:col-span-4 rounded-lg border-gray-200"></textarea>
        </form>
    @endif
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Order</th><th class="px-3 py-3">Admission</th><th class="px-3 py-3">Medicine / dose</th><th class="px-3 py-3">Window</th><th class="px-3 py-3">Administration</th><th class="px-5 py-3">Record dose</th></tr></thead>
            <tbody class="divide-y">
            @forelse($orders as $order)
                <tr>
                    <td class="px-5 py-3"><div class="font-semibold">{{ $order->order_number }}</div><div class="text-xs text-gray-500">{{ $order->status }}</div></td>
                    <td class="px-3 py-3">{{ $order->admission?->admission_number }}<div class="text-xs text-gray-500">{{ $order->patient?->name }}</div></td>
                    <td class="px-3 py-3">{{ $order->medicine?->name }}<div class="text-xs text-gray-500">{{ $order->dose }} via {{ $order->route }}</div></td>
                    <td class="px-3 py-3">{{ $order->scheduled_start_at?->format('d M Y H:i') }}@if($order->scheduled_end_at)<div class="text-xs text-gray-500">to {{ $order->scheduled_end_at->format('d M Y H:i') }}</div>@endif</td>
                    <td class="px-3 py-3"><div class="space-y-1">@forelse($order->administrations as $administration)<div class="text-xs"><span class="font-semibold capitalize">{{ $administration->status }}</span> — {{ $administration->scheduled_at?->format('d M H:i') }}@if($administration->administered_at) ({{ $administration->administered_at->format('H:i') }})@endif</div>@empty<span class="text-xs text-gray-400">None recorded</span>@endforelse</div></td>
                    <td class="px-5 py-3">
                        @if($canAdminister && $order->status === 'active')
                            <form method="POST" action="{{ route('manager.hospital-medication.administrations.store', $order) }}" class="space-y-2 min-w-56">@csrf
                                <input name="dose" required value="{{ $order->dose }}" class="w-full rounded border-gray-200 text-xs">
                                <input name="route" required value="{{ $order->route }}" class="w-full rounded border-gray-200 text-xs">
                                <input type="datetime-local" name="scheduled_at" required value="{{ $order->scheduled_start_at?->format('Y-m-d\TH:i') }}" class="w-full rounded border-gray-200 text-xs">
                                <select name="status" required class="w-full rounded border-gray-200 text-xs"><option value="administered">Administered</option><option value="omitted">Omitted</option><option value="refused">Refused</option><option value="cancelled">Cancelled</option><option value="scheduled">Scheduled</option></select>
                                <input type="datetime-local" name="administered_at" placeholder="Required only when administered" class="w-full rounded border-gray-200 text-xs">
                                <input name="reason" placeholder="Reason when not given" class="w-full rounded border-gray-200 text-xs">
                                <input name="idempotency_key" placeholder="Idempotency key (optional)" class="w-full rounded border-gray-200 text-xs">
                                <textarea name="notes" placeholder="Notes (optional)" class="w-full rounded border-gray-200 text-xs"></textarea>
                                <button class="rounded bg-hut-green px-3 py-2 text-xs font-semibold text-white">Record</button>
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
@endsection
