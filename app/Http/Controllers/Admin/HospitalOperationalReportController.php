<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardLocation;
use App\Services\HospitalOperationalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HospitalOperationalReportController extends Controller
{
    public function __construct(private readonly HospitalOperationalReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $filters = $this->filters($request, $restaurantId);
        $report = $this->reports->generate($restaurantId, $filters);

        return view('manager.hospital-reports.index', [
            'report' => $report,
            'filters' => $filters,
            'wards' => Ward::where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => WardLocation::where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'ward_id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $filters = $this->filters($request, $restaurantId);
        $report = $this->reports->generate($restaurantId, $filters);
        $section = (string) $request->query('section', 'all');

        $sections = [
            'admission_census' => ['Admission census', $report['admission_census']['by_status'] ?? []],
            'occupancy' => ['Current occupancy', $report['occupancy']['rows'] ?? []],
            'bed_turnover' => ['Bed turnover and length of stay', $report['bed_turnover']['by_ward'] ?? []],
            'nursing_coverage' => ['Nursing assignment coverage', $report['nursing_coverage']['by_ward'] ?? []],
            'medication_administrations' => ['Medication administrations', $report['medication_administrations']['by_status'] ?? []],
            'pending_diagnostics' => ['Pending laboratory and radiology', [
                'laboratory' => $report['pending_diagnostics']['laboratory']['total'] ?? 0,
                'radiology' => $report['pending_diagnostics']['radiology']['total'] ?? 0,
            ]],
            'surgery' => ['Surgery schedule and status', $report['surgery']['schedule'] ?? []],
            'billing' => ['Billing, payments, refunds and outstanding', $report['billing']],
            'discharges_readmissions' => ['Discharges and readmissions', $report['discharges_readmissions']],
        ];
        if ($section !== 'all' && ! array_key_exists($section, $sections)) {
            abort(404);
        }

        return response()->streamDownload(function () use ($report, $sections, $section): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Professional hospital operational report']);
            fputcsv($out, ['From', $report['filters']['date_from'], 'To', $report['filters']['date_to']]);
            fputcsv($out, []);
            $selected = $section === 'all' ? $sections : [$section => $sections[$section]];
            foreach ($selected as $key => [$title, $rows]) {
                fputcsv($out, [$title]);
                if (is_array($rows) && $rows !== [] && is_array(reset($rows))) {
                    fputcsv($out, array_keys(reset($rows)));
                    foreach ($rows as $row) {
                        fputcsv($out, is_array($row) ? array_values($row) : [$row]);
                    }
                } elseif (is_array($rows)) {
                    fputcsv($out, ['Metric', 'Value']);
                    foreach ($rows as $metric => $value) {
                        fputcsv($out, [$metric, is_scalar($value) || $value === null ? $value : json_encode($value)]);
                    }
                }
                fputcsv($out, []);
            }
            fclose($out);
        }, 'hospital-operational-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function manager(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isRestaurantManager() && $user->effectiveRestaurantId(), 403);

        return $user;
    }

    private function filters(Request $request, int $restaurantId): array
    {
        $wardId = $request->integer('ward_id') ?: null;

        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId))],
            'location_id' => ['nullable', 'integer', Rule::exists('ward_locations', 'id')->where(function ($query) use ($restaurantId, $wardId): void {
                $query->where('restaurant_id', $restaurantId);
                if ($wardId !== null) {
                    $query->where('ward_id', $wardId);
                }
            })],
            'section' => ['nullable', Rule::in(['all', 'admission_census', 'occupancy', 'bed_turnover', 'nursing_coverage', 'medication_administrations', 'pending_diagnostics', 'surgery', 'billing', 'discharges_readmissions'])],
        ]);
    }
}
