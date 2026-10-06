@extends('manager.layout.master')
@section('title', 'Hospital Staff Rosters')
@section('page-content')
<div class="space-y-6">
    <div class="glass-strong rounded-2xl p-5">
        <p class="page-eyebrow">Hospital operations</p>
        <h1 class="font-display text-xl font-bold text-hut-dark">Hospital Staff Rosters</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $viewOnly ? 'Your assigned inpatient shifts.' : 'Schedule and confirm inpatient nurse and doctor shifts.' }}</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @unless($viewOnly)
        <form method="POST" action="{{ route('manager.staff-rosters.store') }}" class="glass-strong grid gap-3 rounded-2xl p-5 md:grid-cols-4">
            @csrf
            <select name="staff_user_id" required class="rounded-lg border-gray-200">
                <option value="">Select nurse or doctor</option>
                @foreach($staff as $member)
                    <option value="{{ $member->id }}" @selected(old('staff_user_id') == $member->id)>{{ $member->name }} ({{ ucfirst($member->staff_type) }})</option>
                @endforeach
            </select>
            <input type="date" name="shift_date" value="{{ old('shift_date', now()->toDateString()) }}" required class="rounded-lg border-gray-200">
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->startOfHour()->format('Y-m-d\TH:i')) }}" required class="rounded-lg border-gray-200">
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', now()->startOfHour()->addHours(8)->format('Y-m-d\TH:i')) }}" required class="rounded-lg border-gray-200">
            <select name="shift_type" required class="rounded-lg border-gray-200">
                @foreach(['morning', 'day', 'evening', 'night', 'on_call'] as $type)
                    <option value="{{ $type }}" @selected(old('shift_type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
            <select name="department_id" class="rounded-lg border-gray-200">
                <option value="">Any department</option>
                @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
            </select>
            <select name="ward_id" class="rounded-lg border-gray-200">
                <option value="">Any ward</option>
                @foreach($wards as $ward)<option value="{{ $ward->id }}">{{ $ward->name }}</option>@endforeach
            </select>
            <button class="rounded-lg bg-hut-dark px-4 py-2 text-sm font-semibold text-white">Schedule shift</button>
            <textarea name="notes" placeholder="Roster notes (optional)" class="md:col-span-4 rounded-lg border-gray-200">{{ old('notes') }}</textarea>
        </form>
    @endunless

    <div class="glass-strong overflow-x-auto rounded-2xl">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b bg-gray-50 text-xs uppercase text-gray-500">
                <tr><th class="px-5 py-3">Staff</th><th class="px-3 py-3">Date</th><th class="px-3 py-3">Window</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Department / Ward</th><th class="px-3 py-3">Status</th><th class="px-5 py-3">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
            @forelse($rosters as $roster)
                <tr>
                    <td class="px-5 py-3"><div class="font-medium">{{ $roster->staffUser?->name }}</div><div class="text-xs capitalize text-gray-500">{{ $roster->staffUser?->staff_type }}</div></td>
                    <td class="px-3 py-3">{{ $roster->shift_date?->format('d M Y') }}</td>
                    <td class="px-3 py-3">{{ $roster->starts_at?->format('H:i') }} – {{ $roster->ends_at?->format('H:i') }}</td>
                    <td class="px-3 py-3 capitalize">{{ str_replace('_', ' ', $roster->shift_type) }}</td>
                    <td class="px-3 py-3">{{ $roster->department?->name ?? 'Any' }} / {{ $roster->ward?->name ?? 'Any' }}</td>
                    <td class="px-3 py-3"><span class="capitalize">{{ $roster->status }}</span></td>
                    <td class="px-5 py-3">
                        @unless($viewOnly)
                            <div class="flex flex-wrap gap-2">
                                @if($roster->status === 'scheduled')
                                    <form method="POST" action="{{ route('manager.staff-rosters.confirm', $roster) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-hut-green">Confirm</button></form>
                                @endif
                                @if(in_array($roster->status, ['scheduled', 'confirmed'], true))
                                    <form method="POST" action="{{ route('manager.staff-rosters.cancel', $roster) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-red-600">Cancel</button></form>
                                @endif
                                <form method="POST" action="{{ route('manager.staff-rosters.destroy', $roster) }}" data-confirm="Delete this roster shift?">@csrf @method('DELETE')<button class="text-xs font-semibold text-gray-500">Delete</button></form>
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs font-semibold text-hut-green">Edit</summary>
                                <form method="POST" action="{{ route('manager.staff-rosters.update', $roster) }}" class="mt-2 grid min-w-72 gap-2 rounded-lg bg-gray-50 p-2">
                                    @csrf @method('PATCH')
                                    <select name="staff_user_id" class="rounded border-gray-200 text-xs">@foreach($staff as $member)<option value="{{ $member->id }}" @selected($member->id === $roster->staff_user_id)>{{ $member->name }} ({{ ucfirst($member->staff_type) }})</option>@endforeach</select>
                                    <input type="date" name="shift_date" value="{{ $roster->shift_date?->toDateString() }}" required class="rounded border-gray-200 text-xs">
                                    <input type="datetime-local" name="starts_at" value="{{ $roster->starts_at?->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs">
                                    <input type="datetime-local" name="ends_at" value="{{ $roster->ends_at?->format('Y-m-d\TH:i') }}" required class="rounded border-gray-200 text-xs">
                                    <select name="shift_type" class="rounded border-gray-200 text-xs">@foreach(['morning', 'day', 'evening', 'night', 'on_call'] as $type)<option value="{{ $type }}" @selected($type === $roster->shift_type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>@endforeach</select>
                                    <select name="status" class="rounded border-gray-200 text-xs">@foreach(['scheduled', 'confirmed', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected($status === $roster->status)>{{ ucfirst($status) }}</option>@endforeach</select>
                                    <textarea name="notes" class="rounded border-gray-200 text-xs">{{ $roster->notes }}</textarea>
                                    <button class="rounded bg-hut-green px-2 py-1 text-xs font-semibold text-white">Save changes</button>
                                </form>
                            </details>
                        @else
                            <span class="text-xs text-gray-400">View only</span>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No roster shifts found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-4">{{ $rosters->links() }}</div>
    </div>
</div>
@endsection
