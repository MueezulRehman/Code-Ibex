@if($hospitalDashboard)
<section class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-blue-600">Professional Hospital</p>
            <h2 class="mt-1 text-xl font-semibold text-hut-dark">Inpatient operations</h2>
            <p class="mt-1 text-sm text-gray-600">Live capacity, coverage, and clinical follow-up indicators.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <a href="{{ route('manager.admissions.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Admissions</a>
            @if($hospitalDashboard['active_nursing_assignments'] !== null)<a href="{{ route('manager.nursing.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Nursing</a>@endif
            @if($hospitalDashboard['without_recent_vitals'] !== null)<a href="{{ route('manager.vitals.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Vital Signs</a>@endif
            @if($hospitalDashboard['billing_outstanding'] !== null)<a href="{{ route('manager.hospital-billing.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Billing</a>@endif
            @if($hospitalDashboard['pending_lab_orders'] !== null)<a href="{{ route('manager.laboratory.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Laboratory</a>@endif
            @if($hospitalDashboard['pending_radiology_orders'] !== null)<a href="{{ route('manager.radiology.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Radiology</a>@endif
            @if($hospitalDashboard['surgery_upcoming'] !== null)<a href="{{ route('manager.operating-theatre.index') }}" class="rounded-lg bg-white px-3 py-2 text-blue-700 shadow-sm">Theatre</a>@endif
        </div>
    </div>
    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-11">
        @foreach([
            ['Active admissions', $hospitalDashboard['active_admissions'], 'text-blue-700'],
            ['Available beds', $hospitalDashboard['available_beds'] ?? '—', 'text-emerald-700'],
            ['Occupied beds', $hospitalDashboard['occupied_beds'] ?? '—', 'text-amber-700'],
            ['Unassigned', $hospitalDashboard['unassigned_admissions'], 'text-rose-700'],
            ['Nursing assignments', $hospitalDashboard['active_nursing_assignments'] ?? '—', 'text-indigo-700'],
            ['Missing recent vitals', $hospitalDashboard['without_recent_vitals'] ?? '—', 'text-orange-700'],
            ['Discharges today', $hospitalDashboard['discharges_today'], 'text-slate-700'],
            ['Outstanding billing', $hospitalDashboard['billing_outstanding'] === null ? '—' : 'Rs. '.number_format($hospitalDashboard['billing_outstanding'], 2), 'text-red-700'],
            ['Pending lab orders', $hospitalDashboard['pending_lab_orders'] ?? '—', 'text-cyan-700'],
            ['Pending radiology', $hospitalDashboard['pending_radiology_orders'] ?? '—', 'text-cyan-700'],
            ['Surgeries today', $hospitalDashboard['surgery_scheduled_today'] ?? '—', 'text-violet-700'],
            ['Surgeries next 7 days', $hospitalDashboard['surgery_upcoming'] ?? '—', 'text-violet-700'],
        ] as [$label, $value, $color])
        <div class="rounded-xl border border-white bg-white p-3 shadow-sm">
            <p class="text-[11px] leading-tight text-gray-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold {{ $color }}">{{ $value }}</p>
        </div>
        @if($hospitalDashboard['recent_unpaid_charges']->isNotEmpty())
        <div class="mt-5 rounded-xl border border-white bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-hut-dark">Recent unpaid posted charges</h3>
                <a href="{{ route('manager.hospital-billing.index') }}" class="text-xs font-semibold text-blue-700">Review billing</a>
            </div>
            <div class="mt-3 divide-y divide-gray-100">
                @foreach($hospitalDashboard['recent_unpaid_charges'] as $charge)
                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="text-gray-700">{{ $charge->patient?->name ?? 'Patient' }} · {{ $charge->description }}</span>
                        <strong class="text-red-700">Rs. {{ number_format((float) $charge->total_amount, 2) }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
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
