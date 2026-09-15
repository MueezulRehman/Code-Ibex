@extends('manager.layout.master')
@section('title', 'Inpatient Care Plans')
@section('page-content')
<div class="space-y-6">
    <div>
        <h1 class="font-display text-lg font-bold text-hut-dark">Inpatient Care Plans</h1>
        <p class="text-sm text-gray-500">Structured problems, goals, interventions, reviews, and nursing progress linked to an admission.</p>
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($canCreate)
        <form method="POST" action="{{ route('manager.hospital-care-plans.store') }}" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-5 md:grid-cols-4">
            @csrf
            <select name="hospital_admission_id" required class="rounded-lg border-gray-200 md:col-span-2"><option value="">Admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach</select>
            <input name="problem_diagnosis" required maxlength="255" placeholder="Problem / diagnosis" class="rounded-lg border-gray-200 md:col-span-2">
            <textarea name="goal" required maxlength="10000" placeholder="Goal / expected outcome" class="min-h-20 rounded-lg border-gray-200"></textarea>
            <textarea name="interventions" required maxlength="10000" placeholder="Interventions" class="min-h-20 rounded-lg border-gray-200"></textarea>
            <select name="responsible_role" class="rounded-lg border-gray-200"><option value="">Responsible role (optional)</option>@foreach(['doctor','nurse','pharmacist','physiotherapist','dietitian','other'] as $role)<option value="{{ $role }}">{{ ucfirst($role) }}</option>@endforeach</select>
            <select name="responsible_user_id" class="rounded-lg border-gray-200"><option value="">Responsible user (optional)</option>@foreach($users as $staff)<option value="{{ $staff->id }}">{{ $staff->name }} ({{ $staff->staff_type ?: $staff->role }})</option>@endforeach</select>
            <input type="datetime-local" name="opened_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-lg border-gray-200">
            <input type="datetime-local" name="review_due_at" class="rounded-lg border-gray-200" placeholder="Review due">
            <textarea name="notes" maxlength="10000" placeholder="Plan notes" class="rounded-lg border-gray-200 md:col-span-3"></textarea>
            <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Create Care Plan</button>
        </form>
    @endif
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Admission / Problem</th><th class="px-3 py-3">Goal &amp; interventions</th><th class="px-3 py-3">Status / review</th><th class="px-3 py-3">Nursing progress</th><th class="px-5 py-3">Responsible</th><th class="px-5 py-3">Actions</th></tr></thead>
            <tbody class="divide-y">
            @forelse($plans as $plan)
                <tr>
                    <td class="px-5 py-3 align-top"><strong>{{ $plan->admission?->admission_number }}</strong><div class="text-xs text-gray-500">{{ $plan->patient?->name }}</div><div class="mt-1 font-semibold">{{ $plan->problem_diagnosis }}</div><div class="text-xs text-gray-500">Opened {{ $plan->opened_at?->format('d M Y H:i') }}</div></td>
                    <td class="max-w-sm whitespace-pre-wrap px-3 py-3 align-top"><strong>Goal:</strong> {{ $plan->goal }}<br><strong>Interventions:</strong> {{ $plan->interventions }}</td>
                    <td class="px-3 py-3 align-top"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $plan->status === 'active' ? 'bg-blue-50 text-blue-700' : ($plan->status === 'resolved' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600') }}">{{ ucfirst($plan->status) }}</span><div class="mt-2 text-xs text-gray-500">Review {{ $plan->review_due_at?->format('d M Y H:i') ?: '—' }}</div>@if($plan->resolved_at)<div class="text-xs text-gray-500">Resolved {{ $plan->resolved_at->format('d M Y H:i') }}</div>@endif</td>
                    <td class="max-w-xs whitespace-pre-wrap px-3 py-3 align-top">{{ $plan->nursing_progress ?: '—' }}<div class="mt-1 text-xs text-gray-500">{{ $plan->nursing_status ? ucfirst(str_replace('_', ' ', $plan->nursing_status)) : '' }} @if($plan->nursing_progress_at) · {{ $plan->nursing_progress_at->format('d M Y H:i') }} @endif</div></td>
                    <td class="px-5 py-3 align-top">{{ $plan->responsibleUser?->name ?: '—' }}<div class="text-xs text-gray-500">{{ $plan->responsible_role ? ucfirst($plan->responsible_role) : '' }}</div></td>
                    <td class="px-5 py-3 align-top">
                        @if($canEdit && $plan->status === 'active')
                            <details><summary class="cursor-pointer text-xs font-semibold text-hut-green">Edit plan</summary>
                                <form method="POST" action="{{ route('manager.hospital-care-plans.update', $plan) }}" class="mt-2 grid min-w-64 gap-2">@csrf @method('PATCH')
                                    <input name="problem_diagnosis" required value="{{ $plan->problem_diagnosis }}" class="rounded border-gray-200 text-xs"><textarea name="goal" required class="rounded border-gray-200 text-xs">{{ $plan->goal }}</textarea><textarea name="interventions" required class="rounded border-gray-200 text-xs">{{ $plan->interventions }}</textarea>
                                    <select name="status" class="rounded border-gray-200 text-xs"><option value="active">Active</option><option value="resolved">Resolved</option><option value="cancelled">Cancelled</option></select>
                                    <input type="datetime-local" name="opened_at" value="{{ $plan->opened_at?->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs"><input type="datetime-local" name="review_due_at" value="{{ $plan->review_due_at?->format('Y-m-d\TH:i') }}" class="rounded border-gray-200 text-xs"><input type="datetime-local" name="resolved_at" value="{{ $plan->resolved_at?->format('Y-m-d\TH:i') }}" class="rounded border-gray-200 text-xs"><textarea name="notes" class="rounded border-gray-200 text-xs">{{ $plan->notes }}</textarea><textarea name="correction_notes" required placeholder="Reason for correction" class="rounded border-gray-200 text-xs"></textarea><button class="rounded bg-hut-dark px-2 py-1 text-xs font-semibold text-white">Save</button>
                                </form>
                            </details>
                        @endif
                        @if($canUpdateNursing && ($plan->status === 'active' || $plan->nursing_progress))
                            <details class="mt-2"><summary class="cursor-pointer text-xs font-semibold text-hut-green">Nursing update</summary>
                                <form method="POST" action="{{ route('manager.hospital-care-plans.update', $plan) }}" class="mt-2 grid min-w-64 gap-2">@csrf @method('PATCH')
                                    <select name="status" class="rounded border-gray-200 text-xs"><option value="active" @selected($plan->status === 'active')>Active</option><option value="resolved" @selected($plan->status === 'resolved')>Resolved</option><option value="cancelled" @selected($plan->status === 'cancelled')>Cancelled</option></select><select name="nursing_status" class="rounded border-gray-200 text-xs"><option value="">Progress status</option>@foreach(['not_started','in_progress','completed'] as $status)<option value="{{ $status }}" @selected($plan->nursing_status === $status)>{{ ucfirst(str_replace('_',' ', $status)) }}</option>@endforeach</select><textarea name="nursing_progress" maxlength="10000" placeholder="Nursing progress" class="rounded border-gray-200 text-xs">{{ $plan->nursing_progress }}</textarea><button class="rounded bg-hut-dark px-2 py-1 text-xs font-semibold text-white">Update progress</button>
                                </form>
                            </details>
                        @endif
                        @if($plan->events->isNotEmpty())<details class="mt-2 text-xs"><summary class="cursor-pointer text-gray-500">Audit ({{ $plan->events->count() }})</summary><ul class="mt-1 space-y-1">@foreach($plan->events as $event)<li>{{ ucfirst(str_replace('_', ' ', $event->action)) }} by {{ $event->changedBy?->name }} — {{ $event->changed_at?->format('d M Y H:i') }}<br>{{ $event->notes }}</li>@endforeach</ul></details>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No care plans available for your authorized admissions.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4">{{ $plans->links() }}</div>
    </div>
</div>
@endsection
