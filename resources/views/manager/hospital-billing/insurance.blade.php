@extends('manager.layout.master')
@section('title', 'Patient Insurance')
@section('page-content')
<div class="space-y-5">
    @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <div class="flex items-end justify-between">
        <div><h1 class="font-display text-lg font-bold text-hut-dark">Patient insurance policies</h1><p class="text-sm text-gray-500">Maintain tenant-scoped payer and member identity for hospital admissions.</p></div>
        <a href="{{ route('manager.hospital-billing.index') }}" class="rounded border border-gray-200 px-3 py-2 text-sm">Back to billing</a>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('manager.hospital-billing.insurance.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">@csrf
            <h2 class="mb-3 font-semibold">Add patient policy</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <select name="patient_id" required class="rounded border-gray-200 text-sm"><option value="">Patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} · {{ $patient->name }}</option>@endforeach</select>
                <input name="payer" required maxlength="150" placeholder="Payer / insurer" class="rounded border-gray-200 text-sm">
                <select name="provider_id" class="rounded border-gray-200 text-sm"><option value="">Existing provider (optional)</option>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select>
                <input name="policy_number" maxlength="100" placeholder="Policy number" class="rounded border-gray-200 text-sm">
                <input name="member_number" maxlength="100" placeholder="Member number" class="rounded border-gray-200 text-sm">
                <select name="status" required class="rounded border-gray-200 text-sm">@foreach(['active','pending','inactive','expired'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select>
                <label class="text-xs text-gray-500">Coverage starts<input type="date" name="coverage_starts" class="mt-1 w-full rounded border-gray-200 text-sm"></label>
                <label class="text-xs text-gray-500">Coverage ends<input type="date" name="coverage_ends" class="mt-1 w-full rounded border-gray-200 text-sm"></label>
            </div>
            <textarea name="notes" rows="2" maxlength="5000" placeholder="Notes" class="mt-3 w-full rounded border-gray-200 text-sm"></textarea>
            <button class="mt-3 rounded bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Save policy</button>
        </form>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold">Link active admissions</h2>
            <div class="space-y-3">@forelse($admissions as $admission)
                <form method="POST" action="{{ route('manager.hospital-billing.insurance.admission', $admission) }}" class="flex flex-wrap items-center gap-2 text-sm">@csrf @method('PATCH')
                    <span class="min-w-44 font-medium">{{ $admission->admission_number }} · {{ $admission->patient?->name }}</span>
                    <select name="hospital_patient_insurance_id" class="min-w-52 flex-1 rounded border-gray-200 text-xs"><option value="">No policy</option>@foreach($allPolicies->where('patient_id', $admission->patient_id) as $policy)<option value="{{ $policy->id }}" @selected((int) $admission->hospital_patient_insurance_id === (int) $policy->id)>{{ $policy->payer }} · {{ $policy->member_number ?: ($policy->policy_number ?: 'No number') }}</option>@endforeach</select>
                    <button class="rounded bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">Link</button>
                </form>
            @empty<p class="text-sm text-gray-500">No active admissions.</p>@endforelse</div>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm"><thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Patient</th><th class="px-3 py-3">Payer / numbers</th><th class="px-3 py-3">Coverage</th><th class="px-3 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
        <tbody class="divide-y">@forelse($policies as $policy)<tr>
            <td class="px-4 py-3"><div class="font-semibold">{{ $policy->patient?->name }}</div><div class="text-xs text-gray-500">{{ $policy->patient?->patient_number }}</div></td>
            <td class="px-3 py-3"><div>{{ $policy->payer }}</div><div class="text-xs text-gray-500">Policy: {{ $policy->policy_number ?: '—' }} · Member: {{ $policy->member_number ?: '—' }}</div></td>
            <td class="px-3 py-3 text-xs">{{ $policy->coverage_starts?->format('d M Y') ?: '—' }} to {{ $policy->coverage_ends?->format('d M Y') ?: '—' }}</td>
            <td class="px-3 py-3"><span class="rounded-full bg-blue-50 px-2 py-1 text-xs capitalize text-blue-700">{{ $policy->status }}</span></td>
            <td class="px-4 py-3 text-right"><details class="text-left"><summary class="cursor-pointer text-xs font-semibold text-gray-700">Edit</summary>
                <form method="POST" action="{{ route('manager.hospital-billing.insurance.update', $policy) }}" class="mt-2 grid gap-2">@csrf @method('PATCH')
                    <input name="patient_id" type="hidden" value="{{ $policy->patient_id }}"><input name="provider_id" type="hidden" value="{{ $policy->provider_id }}"><input name="payer" required maxlength="150" value="{{ $policy->payer }}" class="rounded border-gray-200 text-xs"><input name="policy_number" maxlength="100" value="{{ $policy->policy_number }}" placeholder="Policy number" class="rounded border-gray-200 text-xs"><input name="member_number" maxlength="100" value="{{ $policy->member_number }}" placeholder="Member number" class="rounded border-gray-200 text-xs"><select name="status" class="rounded border-gray-200 text-xs">@foreach(['active','pending','inactive','expired'] as $status)<option value="{{ $status }}" @selected($policy->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select><input type="date" name="coverage_starts" value="{{ $policy->coverage_starts?->format('Y-m-d') }}" class="rounded border-gray-200 text-xs"><input type="date" name="coverage_ends" value="{{ $policy->coverage_ends?->format('Y-m-d') }}" class="rounded border-gray-200 text-xs"><textarea name="notes" class="rounded border-gray-200 text-xs">{{ $policy->notes }}</textarea><button class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold">Save</button>
                </form></details>
                <form method="POST" action="{{ route('manager.hospital-billing.insurance.destroy', $policy) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700">Remove</button></form>
            </td>
        </tr>@empty<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No patient policies yet.</td></tr>@endforelse</tbody></table>
        <div class="p-4">{{ $policies->links() }}</div>
    </div>
</div>
@endsection
