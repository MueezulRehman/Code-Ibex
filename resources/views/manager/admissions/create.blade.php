@extends('manager.layout.master')
@section('title', 'New Admission')
@section('page-content')
<div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm"><h1 class="font-display text-lg font-bold text-hut-dark">New Hospital Admission</h1>
<form method="POST" action="{{ route('manager.admissions.store') }}" class="mt-5 space-y-4">@csrf
<div><label class="mb-1 block text-sm font-medium">Patient</label><select name="patient_id" required class="w-full rounded-lg border-gray-200"><option value="">Select patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}">{{ $patient->patient_number }} — {{ $patient->name }}</option>@endforeach</select></div>
<div><label class="mb-1 block text-sm font-medium">Department</label><select name="department_id" class="w-full rounded-lg border-gray-200"><option value="">Unassigned</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>@endforeach</select></div>
<div><label class="mb-1 block text-sm font-medium">Attending doctor</label><select name="doctor_id" class="w-full rounded-lg border-gray-200"><option value="">Unassigned</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->name }} — {{ $doctor->specialty }}</option>@endforeach</select></div>
<div><label class="mb-1 block text-sm font-medium">Bed</label><select name="bed_id" class="w-full rounded-lg border-gray-200"><option value="">Assign later</option>@foreach($beds as $bed)<option value="{{ $bed->id }}">{{ $bed->code }} — {{ $bed->ward?->name ?? 'Ward' }}@if($bed->location) · {{ $bed->location->name }}@endif</option>@endforeach</select></div>
<div><label class="mb-1 block text-sm font-medium">Admitted at</label><input type="datetime-local" name="admitted_at" value="{{ old('admitted_at', now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-lg border-gray-200"></div>
<div><label class="mb-1 block text-sm font-medium">Notes</label><textarea name="notes" rows="4" class="w-full rounded-lg border-gray-200"></textarea></div>
<div class="flex justify-end gap-3"><a href="{{ route('manager.admissions.index') }}" class="rounded-lg border px-4 py-2 text-sm">Cancel</a><button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Admit Patient</button></div>
</form></div>
@endsection
