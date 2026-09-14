@extends('manager.layout.master')
@section('title', 'Operating Theatre')
@section('page-content')
<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h1 class="font-display text-base font-bold text-hut-dark">Operating theatre</h1>
        <p class="text-xs text-gray-500">Tenant-scoped operating rooms and inpatient surgery schedule.</p>
        @if(session('success'))<div class="mt-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if($canManage)
        <form method="POST" action="{{ route('manager.operating-theatre.rooms.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">@csrf
            <input name="name" required maxlength="120" placeholder="Room name" class="rounded-lg border-gray-200 text-sm">
            <input name="code" required maxlength="40" placeholder="Room code" class="rounded-lg border-gray-200 text-sm">
            <input name="notes" maxlength="2000" placeholder="Notes (optional)" class="rounded-lg border-gray-200 text-sm">
            <button class="rounded-lg bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Add room</button>
        </form>
        @endif
        @if($canSchedule)
        <form method="POST" action="{{ route('manager.operating-theatre.surgeries.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">@csrf
            <select name="hospital_admission_id" required class="rounded-lg border-gray-200 text-sm"><option value="">Admission</option>@foreach($admissions as $admission)<option value="{{ $admission->id }}">{{ $admission->admission_number }} — {{ $admission->patient?->name }}</option>@endforeach</select>
            <select name="operating_room_id" required class="rounded-lg border-gray-200 text-sm"><option value="">Operating room</option>@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }} ({{ $room->code }})</option>@endforeach</select>
            <select name="surgeon_id" required class="rounded-lg border-gray-200 text-sm">@foreach($doctors as $doctor)<option value="{{ $doctor->id }}">{{ $doctor->name }}</option>@endforeach</select>
            <input name="procedure_name" required maxlength="255" placeholder="Procedure name" class="rounded-lg border-gray-200 text-sm">
            <input type="datetime-local" name="starts_at" required class="rounded-lg border-gray-200 text-sm">
            <input type="datetime-local" name="ends_at" required class="rounded-lg border-gray-200 text-sm">
            <input name="notes" maxlength="5000" placeholder="Notes (optional)" class="rounded-lg border-gray-200 text-sm">
            <button class="rounded-lg bg-hut-yellow px-3 py-2 text-sm font-semibold text-hut-dark">Schedule surgery</button>
        </form>
        @endif
    </div>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"><table class="w-full text-left text-sm"><thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-5 py-3">Surgery</th><th class="px-3 py-3">Admission / patient</th><th class="px-3 py-3">Room</th><th class="px-3 py-3">Procedure / surgeon</th><th class="px-3 py-3">Schedule</th><th class="px-5 py-3">Status</th></tr></thead><tbody class="divide-y divide-gray-100">
        @forelse($surgeries as $surgery)<tr><td class="px-5 py-3 font-semibold">{{ $surgery->surgery_number }}</td><td class="px-3 py-3">{{ $surgery->admission?->admission_number }}<div class="text-xs text-gray-500">{{ $surgery->patient?->name }}</div></td><td class="px-3 py-3">{{ $surgery->operatingRoom?->name }}</td><td class="px-3 py-3">{{ $surgery->procedure_name }}<div class="text-xs text-gray-500">{{ $surgery->surgeon?->name }}</div></td><td class="px-3 py-3 text-xs">{{ $surgery->starts_at?->format('d M Y H:i') }} – {{ $surgery->ends_at?->format('H:i') }}</td><td class="px-5 py-3">{{ ucfirst(str_replace('_', ' ', $surgery->status)) }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">No surgeries found.</td></tr>@endforelse
    </tbody></table><div class="px-5 py-4">{{ $surgeries->links() }}</div></div>
</div>
@endsection
