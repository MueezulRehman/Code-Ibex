@extends('manager.layout.master')

@section('title', 'Emergency & Trauma')

@section('page-content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-lg font-bold text-hut-dark">Emergency &amp; Trauma</h1>
            <p class="text-xs text-gray-500">Register, triage, assign, and route emergency cases into active hospital admissions.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    @if($canManage)
        <form method="POST" action="{{ route('manager.hospital-emergency.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="mb-4 text-sm font-bold text-hut-dark">Emergency intake</h2>
            <div class="grid gap-3 md:grid-cols-3">
                <select name="patient_id" required class="rounded-lg border-gray-200 text-sm">
                    <option value="">Select patient</option>
                    @foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} — {{ $patient->name }}</option>@endforeach
                </select>
                <select name="priority" required class="rounded-lg border-gray-200 text-sm">
                    <option value="critical">Critical</option><option value="urgent">Urgent</option><option value="routine" selected>Routine</option>
                </select>
                <input type="datetime-local" name="arrived_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded-lg border-gray-200 text-sm">
                <input name="chief_complaint" required maxlength="1000" placeholder="Chief complaint" class="rounded-lg border-gray-200 text-sm md:col-span-2">
                <input type="number" name="emergency_charge_amount" min="0" step="0.01" placeholder="Emergency charge (optional)" class="rounded-lg border-gray-200 text-sm">
                <select name="doctor_id" class="rounded-lg border-gray-200 text-sm"><option value="">Assign doctor</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->name }}</option>@endforeach</select>
                <select name="nurse_id" class="rounded-lg border-gray-200 text-sm"><option value="">Assign nurse</option>@foreach($nurses as $nurse)<option value="{{ $nurse->id }}">{{ $nurse->name }}</option>@endforeach</select>
                <select name="department_id" class="rounded-lg border-gray-200 text-sm"><option value="">Department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
                <textarea name="triage_notes" maxlength="5000" placeholder="Initial triage notes" class="rounded-lg border-gray-200 text-sm md:col-span-3"></textarea>
            </div>
            <button class="mt-4 rounded-lg bg-hut-yellow px-4 py-2 text-sm font-semibold text-hut-dark">Register emergency case</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                <tr><th class="px-4 py-3">Case</th><th class="px-3 py-3">Patient / complaint</th><th class="px-3 py-3">Priority</th><th class="px-3 py-3">Assignments</th><th class="px-3 py-3">Status</th><th class="px-4 py-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($cases as $case)
                <tr class="align-top">
                    <td class="px-4 py-3"><div class="font-semibold text-hut-dark">{{ $case->case_number }}</div><div class="text-xs text-gray-500">{{ $case->arrived_at?->format('d M Y H:i') }}</div></td>
                    <td class="px-3 py-3"><div class="font-medium">{{ $case->patient?->name }}</div><div class="max-w-xs text-xs text-gray-500">{{ $case->chief_complaint }}</div></td>
                    <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $case->priority === 'critical' ? 'bg-red-100 text-red-700' : ($case->priority === 'urgent' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">{{ ucfirst($case->priority) }}</span></td>
                    <td class="px-3 py-3 text-xs text-gray-600">Dr. {{ $case->doctor?->name ?? 'Unassigned' }}<br>Nurse: {{ $case->nurse?->name ?? 'Unassigned' }}<br>{{ $case->department?->name ?? 'No department' }}</td>
                    <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs capitalize text-slate-700">{{ $case->status }}</span>@if($case->admission)<div class="mt-1 text-xs text-green-700">Admission {{ $case->admission->admission_number }}</div><div class="text-xs text-gray-500">Destination: {{ ucfirst($case->destination) }}</div>@endif</td>
                    <td class="px-4 py-3">
                        @if($case->status !== 'routed')
                            <details class="mb-2"><summary class="cursor-pointer text-xs font-semibold text-amber-700">Update triage</summary>
                                <form method="POST" action="{{ route('manager.hospital-emergency.triage', $case) }}" class="mt-2 grid gap-2">
                                    @csrf @method('PATCH')
                                    <select name="priority" required class="rounded border-gray-200 text-xs"><option value="critical" @selected($case->priority === 'critical')>Critical</option><option value="urgent" @selected($case->priority === 'urgent')>Urgent</option><option value="routine" @selected($case->priority === 'routine')>Routine</option></select>
                                    <select name="doctor_id" class="rounded border-gray-200 text-xs"><option value="">Assign doctor</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected($case->doctor_id === $doctor->id)>{{ $doctor->name }}</option>@endforeach</select>
                                    <select name="nurse_id" class="rounded border-gray-200 text-xs"><option value="">Assign nurse</option>@foreach($nurses as $nurse)<option value="{{ $nurse->id }}" @selected($case->nurse_id === $nurse->id)>{{ $nurse->name }}</option>@endforeach</select>
                                    <textarea name="triage_notes" maxlength="5000" placeholder="Triage notes" class="rounded border-gray-200 text-xs">{{ $case->triage_notes }}</textarea>
                                    <button class="rounded bg-amber-600 px-2 py-1 text-xs font-semibold text-white">Save triage</button>
                                </form>
                            </details>
                        @endif
                        @if($case->status !== 'routed' && $canManage)
                            <details><summary class="cursor-pointer text-xs font-semibold text-blue-700">Route case</summary>
                                <form method="POST" action="{{ route('manager.hospital-emergency.route', $case) }}" class="mt-2 grid gap-2">
                                    @csrf @method('PATCH')
                                    <select name="destination" required class="rounded border-gray-200 text-xs"><option value="ward">Ward</option><option value="icu">ICU</option><option value="observation">Observation</option></select>
                                    <select name="department_id" class="rounded border-gray-200 text-xs"><option value="">Department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
                                    <select name="bed_id" class="rounded border-gray-200 text-xs"><option value="">No bed (observation only)</option>@foreach($beds as $bed)<option value="{{ $bed->id }}">{{ $bed->code }} — {{ $bed->ward?->name }}</option>@endforeach</select>
                                    <input type="datetime-local" name="admitted_at" value="{{ now()->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs">
                                    <textarea name="routing_notes" maxlength="5000" placeholder="Routing notes" class="rounded border-gray-200 text-xs"></textarea>
                                    <button class="rounded bg-blue-700 px-2 py-1 text-xs font-semibold text-white">Create active admission</button>
                                </form>
                            </details>
                        @elseif($case->status === 'routed')
                            <span class="text-xs text-gray-500">Routed and active</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No emergency cases have been registered.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="border-t border-gray-100 px-4 py-3">{{ $cases->links() }}</div>
    </div>
</div>
@endsection
