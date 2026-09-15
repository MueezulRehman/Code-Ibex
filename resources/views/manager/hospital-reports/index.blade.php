@extends('manager.layout.master')

@section('title', 'Hospital operational reports')

@section('page-content')
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-hut-dark">Hospital operational reports</h1>
            <p class="mt-1 text-sm text-gray-500">Manager-only, tenant-scoped reporting for admissions, care operations and finance.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('manager.hospital-reports.export', request()->query()) }}" class="inline-flex items-center rounded-lg bg-hut-dark px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">Export all CSV</a>
        </div>
    </div>

    <form method="get" class="grid items-end gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:grid-cols-2 xl:grid-cols-5">
        <div><label for="report-date-from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">From</label><input id="report-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full rounded-lg border border-gray-300"></div>
        <div><label for="report-date-to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">To</label><input id="report-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full rounded-lg border border-gray-300"></div>
        <div><label for="report-ward" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Ward</label><select id="report-ward" name="ward_id" class="w-full rounded-lg border border-gray-300"><option value="">All wards</option>@foreach($wards as $ward)<option value="{{ $ward->id }}" @selected((string)($filters['ward_id'] ?? '') === (string)$ward->id)>{{ $ward->name }}</option>@endforeach</select></div>
        <div><label for="report-location" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Location</label><select id="report-location" name="location_id" class="w-full rounded-lg border border-gray-300"><option value="">All locations</option>@foreach($locations as $location)<option value="{{ $location->id }}" data-ward-id="{{ $location->ward_id }}" @selected((string)($filters['location_id'] ?? '') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="flex gap-2 md:col-span-2 xl:col-span-1"><button class="flex-1 rounded-lg bg-hut-yellow px-4 py-3 text-sm font-semibold text-hut-dark shadow-sm transition hover:bg-hut-yellow-dark">Apply filters</button><a href="{{ route('manager.hospital-reports.index') }}" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50" title="Clear report filters">Clear</a></div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Current census', $report['admission_census']['current'], 'admitted/transferred'],
            ['Occupied beds', $report['occupancy']['occupied_beds'].' / '.$report['occupancy']['total_beds'], 'current occupancy'],
            ['Nursing coverage', $report['nursing_coverage']['coverage_percent'].'%', $report['nursing_coverage']['patients_uncovered'].' uncovered'],
            ['Pending diagnostics', $report['pending_diagnostics']['total'], 'lab + radiology'],
        ] as [$label, $value, $hint])
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-hut-dark">{{ $value }}</p><p class="mt-1 text-xs text-gray-500">{{ $hint }}</p></div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-report-card title="Admission census" section="admission_census">
            <p class="mb-3 text-sm text-gray-600">Current: <strong>{{ $report['admission_census']['current'] }}</strong> · Total records: {{ $report['admission_census']['total'] }}</p>
            @foreach($report['admission_census']['by_status'] as $status => $count)<div class="flex justify-between border-b py-2 text-sm"><span>{{ ucfirst($status) }}</span><strong>{{ $count }}</strong></div>@endforeach
        </x-report-card>
        <x-report-card title="Occupancy by ward, location and bed" section="occupancy">
            <p class="mb-3 text-sm text-gray-600">Available: {{ $report['occupancy']['available_beds'] }} · Occupied: {{ $report['occupancy']['occupied_beds'] }}</p>
            <div class="max-h-56 overflow-auto">@foreach($report['occupancy']['rows'] as $row)<div class="flex justify-between border-b py-2 text-sm"><span>{{ $row['ward'] }} / {{ $row['location'] }} / {{ $row['bed'] }}</span><span class="{{ $row['occupied'] ? 'text-red-600' : 'text-green-600' }}">{{ $row['occupied'] ? 'Occupied' : ucfirst($row['status']) }}</span></div>@endforeach</div>
        </x-report-card>
        <x-report-card title="Bed turnover and length of stay" section="bed_turnover">
            <div class="grid grid-cols-2 gap-3 text-sm"><div>Admissions <strong>{{ $report['bed_turnover']['admissions'] }}</strong></div><div>Discharges <strong>{{ $report['bed_turnover']['discharges'] }}</strong></div><div>Turnovers <strong>{{ $report['bed_turnover']['bed_turnovers'] }}</strong></div><div>Average LOS <strong>{{ number_format($report['bed_turnover']['average_los_days'], 2) }} days</strong></div></div>
            @foreach($report['bed_turnover']['by_ward'] as $row)<div class="mt-3 flex justify-between border-b py-2 text-sm"><span>{{ $row['ward'] }}</span><span>{{ $row['average_los_days'] }}d LOS · {{ $row['bed_turnovers'] }} turnovers</span></div>@endforeach
        </x-report-card>
        <x-report-card title="Nursing assignment coverage" section="nursing_coverage">
            <div class="grid grid-cols-3 gap-3 text-sm"><div>Patients <strong>{{ $report['nursing_coverage']['patients'] }}</strong></div><div>Covered <strong>{{ $report['nursing_coverage']['patients_covered'] }}</strong></div><div>Uncovered <strong>{{ $report['nursing_coverage']['patients_uncovered'] }}</strong></div></div>
            @foreach($report['nursing_coverage']['by_ward'] as $row)<div class="mt-3 flex justify-between border-b py-2 text-sm"><span>{{ $row['ward'] }}</span><span>{{ $row['patients_covered'] }} patients / {{ $row['nurses'] }} nurses</span></div>@endforeach
        </x-report-card>
        <x-report-card title="Medication administrations" section="medication_administrations">
            <p class="mb-3 text-sm text-gray-600">Total scheduled administrations: {{ $report['medication_administrations']['total'] }}</p>
            @foreach($report['medication_administrations']['by_status'] as $status => $count)<div class="flex justify-between border-b py-2 text-sm"><span>{{ ucfirst($status) }}</span><strong>{{ $count }}</strong></div>@endforeach
            <p class="mt-3 text-xs text-gray-500">Omitted: {{ $report['medication_administrations']['omitted'] }} · Refused: {{ $report['medication_administrations']['refused'] }}</p>
        </x-report-card>
        <x-report-card title="Pending laboratory and radiology" section="pending_diagnostics">
            <div class="flex justify-between border-b py-2 text-sm"><span>Laboratory</span><strong>{{ $report['pending_diagnostics']['laboratory']['total'] }}</strong></div>
            <div class="flex justify-between border-b py-2 text-sm"><span>Radiology</span><strong>{{ $report['pending_diagnostics']['radiology']['total'] }}</strong></div>
        </x-report-card>
        <x-report-card title="Surgery status and schedule" section="surgery">
            @foreach($report['surgery']['by_status'] as $status => $count)<span class="mr-3 inline-block rounded-full bg-gray-100 px-2 py-1 text-xs">{{ ucfirst($status) }}: {{ $count }}</span>@endforeach
            <div class="mt-3 max-h-48 overflow-auto">@foreach($report['surgery']['schedule'] as $row)<div class="border-b py-2 text-sm"><strong>{{ $row['procedure'] }}</strong> · {{ $row['room'] }} · {{ ucfirst($row['status']) }}<div class="text-xs text-gray-500">{{ $row['starts_at'] }}</div></div>@endforeach</div>
        </x-report-card>
        <x-report-card title="Billing, payments, refunds and outstanding" section="billing">
            <div class="grid grid-cols-2 gap-3 text-sm"><div>Charges <strong>{{ number_format($report['billing']['charges'], 2) }}</strong></div><div>Payments <strong>{{ number_format($report['billing']['payments'], 2) }}</strong></div><div>Refunds <strong>{{ number_format($report['billing']['refunds'], 2) }}</strong></div><div>Outstanding <strong>{{ number_format($report['billing']['outstanding'], 2) }}</strong></div></div>
        </x-report-card>
        <x-report-card title="Discharges and readmissions" section="discharges_readmissions">
            <div class="grid grid-cols-3 gap-3 text-sm"><div>Discharges <strong>{{ $report['discharges_readmissions']['discharges'] }}</strong></div><div>Readmissions <strong>{{ $report['discharges_readmissions']['readmissions'] }}</strong></div><div>Rate <strong>{{ $report['discharges_readmissions']['readmission_rate'] }}%</strong></div></div>
        </x-report-card>
    </div>
</div>
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var ward = document.getElementById('report-ward');
        var location = document.getElementById('report-location');
        if (!ward || !location) return;
        var syncLocations = function () {
            var wardId = ward.value;
            Array.from(location.options).forEach(function (option) {
                option.hidden = Boolean(wardId && option.value && option.dataset.wardId !== wardId);
            });
            if (location.selectedOptions[0]?.hidden) location.value = '';
        };
        ward.addEventListener('change', syncLocations);
        syncLocations();
    });
</script>
@endpush
@endsection
