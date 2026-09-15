@extends('manager.layout.master')
@section('title', 'Wards and Beds')
@section('page-content')
<div class="space-y-6">
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <h1 class="font-display text-lg font-bold text-hut-dark">Wards &amp; Beds</h1>
    <p class="text-xs text-gray-500">Manage inpatient capacity for this hospital. Locations are optional; existing ward beds remain supported.</p>
    @if(session('success'))<div class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <form method="POST" action="{{ route('manager.wards.store') }}" class="mt-5 grid gap-3 md:grid-cols-4">
        @csrf
        <input name="name" required placeholder="Ward name" class="rounded-lg border-gray-200">
        <input name="code" required placeholder="WARD-A" class="rounded-lg border-gray-200">
        <input name="description" placeholder="Description" class="rounded-lg border-gray-200">
        <button class="rounded-lg bg-hut-dark px-3 py-2 text-sm font-semibold text-white">Add Ward</button>
    </form>
</div>

@foreach($wards as $ward)
<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="flex justify-between">
        <div>
            <h2 class="font-semibold text-hut-dark">{{ $ward->name }} <span class="text-xs text-gray-500">({{ $ward->code }})</span></h2>
            <p class="text-xs text-gray-500">{{ $ward->beds_count }} beds</p>
        </div>
    </div>

    <div class="mt-4 space-y-4">
        @foreach($ward->locations as $location)
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-hut-dark">{{ $location->name }} <span class="text-xs uppercase text-gray-500">{{ $location->type }} · {{ $location->code }}</span></h3>
                    @if($location->description)<p class="text-xs text-gray-500">{{ $location->description }}</p>@endif
                </div>
                <div class="flex items-center gap-2">
                    <details class="text-xs"><summary class="cursor-pointer font-semibold text-slate-600">Edit</summary>
                        <form method="POST" action="{{ route('manager.wards.locations.update', $location) }}" class="mt-2 grid gap-2 rounded bg-white p-2 shadow">
                            @csrf @method('PATCH')
                            <select name="type" required class="rounded border-gray-200 text-xs"><option value="room" @selected($location->type === 'room')>Room</option><option value="hall" @selected($location->type === 'hall')>Hall</option><option value="bay" @selected($location->type === 'bay')>Bay</option></select>
                            <input name="name" value="{{ $location->name }}" required class="rounded border-gray-200 text-xs">
                            <input name="code" value="{{ $location->code }}" required class="rounded border-gray-200 text-xs">
                            <input name="description" value="{{ $location->description }}" class="rounded border-gray-200 text-xs">
                            <button class="rounded bg-slate-700 px-2 py-1 text-xs font-semibold text-white">Save</button>
                        </form>
                    </details>
                    @if($location->beds->isEmpty())
                        <form method="POST" action="{{ route('manager.wards.locations.destroy', $location) }}" onsubmit="return confirm('Delete this location?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Delete</button></form>
                    @endif
                </div>
                <span class="text-xs text-gray-500">{{ $location->beds->count() }} beds</span>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @forelse($location->beds as $bed)
                    <span class="rounded-full {{ $bed->status === 'available' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs">{{ $bed->name }} · {{ $bed->status }}</span>
                @empty
                    <span class="text-xs text-gray-400">No beds configured.</span>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('manager.wards.locations.store', $ward) }}" class="mt-4 grid gap-2 md:grid-cols-5">
        @csrf
        <select name="type" required class="rounded-lg border-gray-200 text-sm">
            <option value="">Location type</option>
            <option value="room">Room</option>
            <option value="hall">Hall</option>
            <option value="bay">Bay</option>
        </select>
        <input name="name" required placeholder="Room / hall / bay name" class="rounded-lg border-gray-200 text-sm">
        <input name="code" required placeholder="ROOM-01" class="rounded-lg border-gray-200 text-sm">
        <input name="description" placeholder="Description" class="rounded-lg border-gray-200 text-sm">
        <button class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white">Add Location</button>
    </form>

    <div class="mt-4 flex flex-wrap gap-2">
        @forelse($ward->beds->whereNull('location_id') as $bed)
            <span class="rounded-full {{ $bed->status === 'available' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-xs">{{ $bed->name }} · {{ $bed->status }}</span>
        @empty
            <span class="text-xs text-gray-400">No legacy ward-level beds configured.</span>
        @endforelse
    </div>
    <form method="POST" action="{{ route('manager.wards.beds.store', $ward) }}" class="mt-4 grid gap-2 md:grid-cols-4">
        @csrf
        <input name="name" required placeholder="Bed name" class="rounded-lg border-gray-200 text-sm">
        <input name="code" required placeholder="BED-01" class="rounded-lg border-gray-200 text-sm">
        <select name="location_id" class="rounded-lg border-gray-200 text-sm">
            <option value="">Ward-level bed (optional location)</option>
            @foreach($ward->locations->where('is_active', true) as $location)
                <option value="{{ $location->id }}">{{ ucfirst($location->type) }}: {{ $location->name }} ({{ $location->code }})</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-hut-yellow px-3 py-2 text-xs font-semibold text-hut-dark">Add Bed</button>
    </form>
</div>
@endforeach
<div>{{ $wards->links() }}</div>
</div>
@endsection
