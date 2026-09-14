@if($hospitalDashboard)
<section class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-blue-600">Professional Hospital</p>
            <h2 class="mt-1 text-xl font-semibold text-hut-dark">Inpatient operations</h2>
            <p class="mt-1 text-sm text-gray-600">Live capacity, coverage, and clinical follow-up indicators.</p>
        </div>
        <div class="flex gap-2 text-xs font-semibold">
            <a href="{{ route('manager.admissions.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Admissions</a>
            @if($hospitalDashboard['active_nursing_assignments'] !== null)<a href="{{ route('manager.nursing.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Nursing</a>@endif
            @if($hospitalDashboard['without_recent_vitals'] !== null)<a href="{{ route('manager.vitals.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Vital Signs</a>@endif
        </div>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach([
            ['Active admissions', $hospitalDashboard['active_admissions'], 'text-blue-700'],
            ['Available beds', $hospitalDashboard['available_beds'] ?? '—', 'text-emerald-700'],
            ['Occupied beds', $hospitalDashboard['occupied_beds'] ?? '—', 'text-amber-700'],
            ['Unassigned', $hospitalDashboard['unassigned_admissions'], 'text-rose-700'],
            ['Nursing assignments', $hospitalDashboard['active_nursing_assignments'] ?? '—', 'text-indigo-700'],
            ['Missing recent vitals', $hospitalDashboard['without_recent_vitals'] ?? '—', 'text-orange-700'],
            ['Discharges today', $hospitalDashboard['discharges_today'], 'text-slate-700'],
        ] as [$label, $value, $color])
        <div class="rounded-xl border border-white bg-white p-3 shadow-sm">
            <p class="text-[11px] leading-tight text-gray-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold {{ $color }}">{{ $value }}</p>
        </div>
        @endforeach
    </div>
    <div class="mt-5 rounded-xl border border-white bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-hut-dark">Department occupancy</h3>
            <span class="text-xs text-gray-500">Active admissions</span>
        </div>
        <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
            @forelse($hospitalDashboard['department_occupancy'] as $department)
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm">
                <span class="text-gray-700">{{ $department->name }}</span>
                <span class="font-semibold text-hut-dark">{{ $department->active_admissions_count }}</span>
            </div>
            @empty
            <p class="text-sm text-gray-500">No active departments configured.</p>
            @endforelse
        </div>
    </div>
</section>
@endif
