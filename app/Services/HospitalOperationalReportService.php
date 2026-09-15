<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\HospitalAdmission;
use App\Models\HospitalBilling;
use App\Models\HospitalMedicationAdministration;
use App\Models\LabOrder;
use App\Models\NursingAssignment;
use App\Models\RadiologyOrder;
use App\Models\Surgery;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only, tenant-scoped operational reporting for professional hospitals.
 *
 * The service deliberately works from set-based queries and eager-loaded
 * collections. Reports are snapshots for the current request; they are not a
 * second reporting data model and never issue queries while rendering rows.
 */
class HospitalOperationalReportService
{
    public function generate(int $restaurantId, array $filters = []): array
    {
        $from = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        $wardId = isset($filters['ward_id']) ? (int) $filters['ward_id'] : null;
        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;

        return [
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'ward_id' => $wardId,
                'location_id' => $locationId,
            ],
            'admission_census' => $this->admissionCensus($restaurantId),
            'occupancy' => $this->occupancy($restaurantId, $wardId, $locationId),
            'bed_turnover' => $this->bedTurnover($restaurantId, $from, $to, $wardId, $locationId),
            'nursing_coverage' => $this->nursingCoverage($restaurantId, $from, $to, $wardId, $locationId),
            'medication_administrations' => $this->medications($restaurantId, $from, $to),
            'pending_diagnostics' => $this->pendingDiagnostics($restaurantId, $from, $to),
            'surgery' => $this->surgery($restaurantId, $from, $to),
            'billing' => $this->billing($restaurantId, $from, $to),
            'discharges_readmissions' => $this->dischargesAndReadmissions($restaurantId, $from, $to),
        ];
    }

    private function admissionCensus(int $restaurantId): array
    {
        $rows = HospitalAdmission::query()
            ->where('restaurant_id', $restaurantId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return [
            'current' => (int) (($rows['admitted'] ?? 0) + ($rows['transferred'] ?? 0)),
            'by_status' => $rows,
            'total' => array_sum($rows),
        ];
    }

    private function occupancy(int $restaurantId, ?int $wardId, ?int $locationId): array
    {
        $beds = Bed::query()
            ->where('restaurant_id', $restaurantId)
            ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->with(['ward:id,name', 'location:id,name'])
            ->withCount([
                'admissions as current_admissions_count' => fn ($query) => $query
                    ->where('restaurant_id', $restaurantId)
                    ->whereIn('status', ['admitted', 'transferred']),
            ])
            ->orderBy('ward_id')
            ->orderBy('name')
            ->get(['id', 'ward_id', 'location_id', 'name', 'code', 'status']);

        $rows = $beds->map(function (Bed $bed): array {
            return [
                'bed_id' => $bed->id,
                'ward' => $bed->ward?->name ?? 'Unassigned ward',
                'location' => $bed->location?->name ?? 'Unassigned location',
                'bed' => $bed->name,
                'code' => $bed->code,
                'status' => $bed->status,
                'occupied' => (int) $bed->current_admissions_count > 0,
                'current_admissions' => (int) $bed->current_admissions_count,
            ];
        })->values()->all();

        $occupied = collect($rows)->where('occupied', true)->count();
        $available = collect($rows)->filter(fn (array $row): bool => ! $row['occupied'] && $row['status'] === 'available')->count();
        return [
            'total_beds' => count($rows),
            'occupied_beds' => $occupied,
            'available_beds' => $available,
            'rows' => $rows,
        ];
    }

    private function bedTurnover(
        int $restaurantId,
        Carbon $from,
        Carbon $to,
        ?int $wardId,
        ?int $locationId
    ): array {
        $admissions = HospitalAdmission::query()
            ->where('restaurant_id', $restaurantId)
            ->where('admitted_at', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('discharged_at')->orWhere('discharged_at', '>=', $from);
            })
            ->with(['bed.ward:id,name', 'bed.location:id,name'])
            ->when($wardId, fn ($query) => $query->whereHas('bed', fn ($bed) => $bed->where('ward_id', $wardId)))
            ->when($locationId, fn ($query) => $query->whereHas('bed', fn ($bed) => $bed->where('location_id', $locationId)))
            ->get(['id', 'bed_id', 'status', 'admitted_at', 'discharged_at']);

        $rows = $admissions->groupBy(fn (HospitalAdmission $admission): string => $admission->bed?->ward?->name ?? 'Unassigned ward')
            ->map(function (Collection $group, string $ward): array {
                $discharged = $group->filter(fn (HospitalAdmission $admission): bool => (bool) $admission->discharged_at);
                $losDays = $discharged->map(fn (HospitalAdmission $admission): float => round(
                    $admission->admitted_at->diffInMinutes($admission->discharged_at) / 1440,
                    2
                ));

                return [
                    'ward' => $ward,
                    'admissions' => $group->count(),
                    'discharges' => $discharged->count(),
                    'bed_turnovers' => $discharged->whereNotNull('bed_id')->count(),
                    'average_los_days' => $losDays->isEmpty() ? 0.0 : round($losDays->avg(), 2),
                ];
            })->values()->all();

        $discharged = $admissions->filter(fn (HospitalAdmission $admission): bool => (bool) $admission->discharged_at);
        $los = $discharged->map(fn (HospitalAdmission $admission): float => $admission->admitted_at->diffInMinutes($admission->discharged_at) / 1440);

        return [
            'admissions' => $admissions->count(),
            'discharges' => $discharged->count(),
            'bed_turnovers' => $discharged->whereNotNull('bed_id')->count(),
            'average_los_days' => $los->isEmpty() ? 0.0 : round($los->avg(), 2),
            'by_ward' => $rows,
        ];
    }

    private function nursingCoverage(
        int $restaurantId,
        Carbon $from,
        Carbon $to,
        ?int $wardId,
        ?int $locationId
    ): array {
        $currentAdmissions = HospitalAdmission::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['admitted', 'transferred'])
            ->when($wardId, fn ($query) => $query->whereHas('bed', fn ($bed) => $bed->where('ward_id', $wardId)))
            ->when($locationId, fn ($query) => $query->whereHas('bed', fn ($bed) => $bed->where('location_id', $locationId)))
            ->pluck('id');

        $assignments = NursingAssignment::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('hospital_admission_id', $currentAdmissions)
            ->where('assigned_at', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from);
            })
            ->where('status', 'active')
            ->with('admission.bed.ward:id,name')
            ->get(['id', 'hospital_admission_id', 'nurse_id', 'shift', 'assigned_at', 'ended_at']);

        $coveredAdmissionIds = $assignments->pluck('hospital_admission_id')->unique();
        $byWard = $assignments->groupBy(fn (NursingAssignment $assignment): string => $assignment->admission?->bed?->ward?->name ?? 'Unassigned ward')
            ->map(fn (Collection $group, string $ward): array => [
                'ward' => $ward,
                'assignments' => $group->count(),
                'patients_covered' => $group->pluck('hospital_admission_id')->unique()->count(),
                'nurses' => $group->pluck('nurse_id')->unique()->count(),
            ])->values()->all();

        return [
            'patients' => $currentAdmissions->count(),
            'patients_covered' => $coveredAdmissionIds->count(),
            'patients_uncovered' => max($currentAdmissions->count() - $coveredAdmissionIds->count(), 0),
            'coverage_percent' => $currentAdmissions->isEmpty() ? 100.0 : round($coveredAdmissionIds->count() / $currentAdmissions->count() * 100, 2),
            'assignments' => $assignments->count(),
            'by_ward' => $byWard,
        ];
    }

    private function medications(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $counts = HospitalMedicationAdministration::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('scheduled_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return [
            'total' => array_sum($counts),
            'administered' => (int) ($counts['administered'] ?? 0),
            'omitted' => (int) ($counts['omitted'] ?? 0),
            'refused' => (int) ($counts['refused'] ?? 0),
            'by_status' => $counts,
        ];
    }

    private function pendingDiagnostics(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $completed = ['completed', 'reported', 'resulted', 'verified', 'cancelled', 'rejected'];
        $labs = LabOrder::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('ordered_at', [$from, $to])
            ->whereNotIn('status', $completed)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)->all();
        $radiology = RadiologyOrder::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('ordered_at', [$from, $to])
            ->whereNotIn('status', $completed)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status')
            ->map(fn ($value): int => (int) $value)->all();

        return [
            'laboratory' => ['total' => array_sum($labs), 'by_status' => $labs],
            'radiology' => ['total' => array_sum($radiology), 'by_status' => $radiology],
            'total' => array_sum($labs) + array_sum($radiology),
        ];
    }

    private function surgery(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $surgeries = Surgery::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('starts_at', [$from, $to])
            ->with(['operatingRoom:id,name'])
            ->get(['id', 'operating_room_id', 'procedure_name', 'status', 'starts_at', 'ends_at']);
        $statuses = $surgeries->groupBy('status')->map->count()->all();

        return [
            'total' => $surgeries->count(),
            'by_status' => $statuses,
            'schedule' => $surgeries->map(fn (Surgery $surgery): array => [
                'procedure' => $surgery->procedure_name,
                'room' => $surgery->operatingRoom?->name ?? 'Unassigned room',
                'status' => $surgery->status,
                'starts_at' => $surgery->starts_at?->toDateTimeString(),
                'ends_at' => $surgery->ends_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    private function billing(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $billings = HospitalBilling::query()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween('charged_at', [$from, $to])
            ->with(['payments' => fn ($query) => $query->where('restaurant_id', $restaurantId)])
            ->get(['id', 'status', 'total_amount', 'charged_at']);

        $payments = $billings->flatMap->payments;
        $postedPayments = $payments->where('status', 'posted');
        $paymentsTotal = (float) $billings->sum(fn (HospitalBilling $billing): float => $billing->settledAmount());
        $refundsTotal = (float) $postedPayments->where('type', 'refund')->sum('amount');
        $chargesTotal = (float) $billings->whereIn('status', ['posted', 'paid'])->sum('total_amount');
        $outstanding = (float) $billings
            ->whereIn('status', ['posted', 'paid'])
            ->sum(fn (HospitalBilling $billing): float => $billing->outstandingAmount());

        return [
            'charges' => round($chargesTotal, 2),
            'payments' => round($paymentsTotal, 2),
            'refunds' => round($refundsTotal, 2),
            'outstanding' => round(max($outstanding, 0), 2),
            'by_status' => $billings->groupBy('status')->map->count()->all(),
            'bill_count' => $billings->count(),
        ];
    }

    private function dischargesAndReadmissions(int $restaurantId, Carbon $from, Carbon $to): array
    {
        $admissions = HospitalAdmission::query()
            ->where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('admitted_at', [$from->copy()->subDays(30), $to])
                    ->orWhereBetween('discharged_at', [$from, $to]);
            })
            ->get(['id', 'patient_id', 'admitted_at', 'discharged_at']);

        $discharges = $admissions->filter(fn (HospitalAdmission $admission): bool => $admission->discharged_at
            && $admission->discharged_at->betweenIncluded($from, $to));
        $readmissions = $admissions->filter(function (HospitalAdmission $admission) use ($from, $to, $admissions): bool {
            if (! $admission->admitted_at->betweenIncluded($from, $to)) {
                return false;
            }

            return $admissions->contains(function (HospitalAdmission $previous) use ($admission): bool {
                return $previous->patient_id === $admission->patient_id
                    && $previous->id !== $admission->id
                    && $previous->discharged_at
                    && $previous->discharged_at->lt($admission->admitted_at)
                    && $previous->discharged_at->gte($admission->admitted_at->copy()->subDays(30));
            });
        });

        return [
            'discharges' => $discharges->count(),
            'readmissions' => $readmissions->count(),
            'readmission_rate' => $discharges->isEmpty() ? 0.0 : round($readmissions->count() / $discharges->count() * 100, 2),
        ];
    }
}
