<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Bed;
use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\HospitalTransfer;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HospitalAdmissionController extends Controller
{
    private function restaurantId(): int
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $id = $user->effectiveRestaurantId();
        abort_unless($id !== null, 403);
        return (int) $id;
    }

    public function index()
    {
        $admissions = HospitalAdmission::with(['patient', 'doctor', 'department', 'bed'])->latest('admitted_at')->paginate(20);
        return view('manager.admissions.index', [
            'admissions' => $admissions,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with('ward')->where('status', 'available')->orderBy('code')->get(),
        ]);
    }

    public function create()
    {
        return view('manager.admissions.create', [
            'patients' => Patient::orderBy('name')->get(),
            'doctors' => Doctor::where('status', 'active')->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'beds' => Bed::with('ward')->where('status', 'available')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $restaurantId = $this->restaurantId();
        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'doctor_id' => ['nullable', 'integer', Rule::exists('doctors', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
            'admitted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['restaurant_id'] = $restaurantId;
        $data['admission_number'] = 'ADM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        $data['status'] = 'admitted';
        DB::transaction(function () use ($data): void {
            $admission = HospitalAdmission::create($data);
            if ($admission->bed_id) {
                Bed::whereKey($admission->bed_id)->where('status', 'available')->lockForUpdate()->firstOrFail()
                    ->update(['status' => 'occupied']);
            }
        });
        return redirect()->route('manager.admissions.index')->with('success', 'Patient admitted.');
    }

    public function assignBed(Request $request, HospitalAdmission $admission)
    {
        $restaurantId = $this->restaurantId();
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'bed_id' => ['required', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
        ]);
        DB::transaction(function () use ($admission, $data, $restaurantId): void {
            $bed = Bed::where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                ->where('status', 'available')->lockForUpdate()->firstOrFail();
            if ($admission->bed_id && $admission->bed_id !== $bed->id) {
                Bed::whereKey($admission->bed_id)->update(['status' => 'available']);
            }
            $admission->update(['bed_id' => $bed->id]);
            $bed->update(['status' => 'occupied']);
        });
        return back()->with('success', 'Bed assigned.');
    }

    public function updateStatus(Request $request, HospitalAdmission $admission)
    {
        abort_unless($admission->restaurant_id === $this->restaurantId(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['admitted', 'discharged'])]]);
        DB::transaction(function () use ($admission, $data): void {
            if ($data['status'] === 'discharged' && $admission->bed_id) {
                Bed::whereKey($admission->bed_id)->lockForUpdate()->firstOrFail()
                    ->update(['status' => 'available']);
            }
            $admission->update([
                'status' => $data['status'],
                'bed_id' => $data['status'] === 'discharged' ? null : $admission->bed_id,
                'discharged_at' => $data['status'] === 'discharged' ? now() : null,
            ]);
        });
        return back()->with('success', 'Admission status updated.');
    }

    public function transfer(Request $request, HospitalAdmission $admission)
    {
        $user = Auth::user();
        $restaurantId = $this->restaurantId();
        abort_unless($admission->restaurant_id === $restaurantId && $admission->status !== 'discharged', 404);
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
            'bed_id' => ['nullable', 'integer', Rule::exists('beds', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'available'))],
            'transferred_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data, $admission, $restaurantId, $user): void {
            $newBed = null;
            if (! empty($data['bed_id'])) {
                $newBed = Bed::where('restaurant_id', $restaurantId)->whereKey($data['bed_id'])
                    ->where('status', 'available')->lockForUpdate()->firstOrFail();
            }
            $oldBedId = $admission->bed_id;
            $oldDepartmentId = $admission->department_id;
            if ($oldBedId && (! $newBed || $newBed->id !== $oldBedId)) {
                Bed::whereKey($oldBedId)->update(['status' => 'available']);
            }
            if ($newBed) {
                $newBed->update(['status' => 'occupied']);
            }
            HospitalTransfer::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'from_department_id' => $oldDepartmentId,
                'to_department_id' => $data['department_id'] ?? null,
                'from_bed_id' => $oldBedId,
                'to_bed_id' => $newBed?->id,
                'requested_by' => $user->id,
                'transferred_at' => $data['transferred_at'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);
            $admission->update([
                'department_id' => $data['department_id'] ?? null,
                'bed_id' => $newBed?->id,
                'status' => 'transferred',
            ]);
        });

        return back()->with('success', 'Admission transfer recorded.');
    }
}
