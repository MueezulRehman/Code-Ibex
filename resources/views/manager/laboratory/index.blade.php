@extends('manager.layout.master')
@section('title', 'Laboratory')
@section('page-content')
<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h1 class="font-display text-base font-bold text-hut-dark">Laboratory orders &amp; results</h1>
        <p class="text-xs text-gray-500">Tenant-scoped inpatient laboratory records.</p>
        @if(session('success'))<div class="mt-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if($canOrder)
            <form method="POST" action="{{ route('manager.laboratory.orders.store') }}" class="mt-4 grid gap-3 md:grid-cols-5">@csrf
                <select name="hospital_admission_id" required class="rounded-lg border-gray-200 text-sm"><option value="">Admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach</select>
                <input name="test_name" required maxlength="255" placeholder="Test name" class="rounded-lg border-gray-200 text-sm">
                <select name="priority" class="rounded-lg border-gray-200 text-sm"><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="stat">STAT</option></select>
                <input type="datetime-local" name="ordered_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-lg border-gray-200 text-sm">
                <button class="rounded-lg bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Order test</button>
                <textarea name="clinical_notes" placeholder="Clinical notes (optional)" class="rounded-lg border-gray-200 text-sm md:col-span-5"></textarea>
            </form>
        @endif
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Order</th><th class="px-3 py-3">Admission / patient</th><th class="px-3 py-3">Test</th><th class="px-3 py-3">Result</th>@if($canManageResults)<th class="px-5 py-3">Manage</th>@endif</tr></thead><tbody class="divide-y divide-gray-100">
        @forelse($orders as $order)<tr><td class="px-5 py-3"><div class="font-semibold">{{ $order->order_number }}</div><div class="text-xs text-gray-500">{{ ucfirst($order->priority) }} · {{ $order->ordered_at?->format('d M Y H:i') }}</div></td><td class="px-3 py-3">{{ $order->admission?->admission_number }}<div class="text-xs text-gray-500">{{ $order->patient?->name }}</div></td><td class="px-3 py-3">{{ $order->test_name }}<div class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</div></td><td class="px-3 py-3">@if($order->result)<div class="whitespace-pre-line">{{ $order->result->result_value }}</div><div class="text-xs text-gray-500">{{ ucfirst($order->result->status) }}</div>@else<span class="text-xs text-gray-400">Pending</span>@endif</td>@if($canManageResults)<td class="px-5 py-3">@if(!$order->result)<form method="POST" action="{{ route('manager.laboratory.results.store', $order) }}" class="grid gap-2">@csrf<textarea name="result_value" required maxlength="10000" placeholder="Result" class="rounded border-gray-200 text-xs"></textarea><input type="datetime-local" name="resulted_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs"><select name="status" class="rounded border-gray-200 text-xs"><option value="entered">Entered</option><option value="verified">Verified</option></select><textarea name="notes" placeholder="Notes (optional)" class="rounded border-gray-200 text-xs"></textarea><button class="rounded bg-blue-700 px-2 py-1 text-xs font-semibold text-white">Save result</button></form>@else<span class="text-xs text-gray-500">Result recorded</span>@endif</td>@endif</tr>@empty<tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">No laboratory orders found.</td></tr>@endforelse
    </tbody></table><div class="px-5 py-4">{{ $orders->links() }}</div></div>
</div>
@endsection
