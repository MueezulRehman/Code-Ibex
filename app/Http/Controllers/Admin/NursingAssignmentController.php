<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\NursingAssignment;
use App\Models\NursingAssignmentEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NursingAssignmentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);

        $showHistory = request()->boolean('history');
        return view('manager.nursing.index', [
            'showHistory' => $showHistory,
            'assignments' => NursingAssignment::with(['admission.patient', 'nurse', 'events.changedBy', 'events.previousNurse', 'events.newNurse'])
                ->when(! $showHistory, fn ($query) => $query->where('status', 'active'))
                ->latest('assigned_at')->paginate(20),
            'admissions' => HospitalAdmission::with('patient')->whereIn('status', ['admitted', 'transferred'])->orderBy('admitted_at')->get(),
            'nurses' => User::where('restaurant_id', $user->effectiveRestaurantId())
                ->where('staff_type', 'nurse')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->whereIn('status', ['admitted', 'transferred']))],
            'nurse_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'nurse')->where('is_active', true))],
            'assigned_at' => ['required', 'date'],
            'shift' => ['required', Rule::in(['morning', 'evening', 'night'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workload = NursingAssignment::where('nurse_id', $data['nurse_id'])->where('status', 'active')->count();
        if ($workload >= 5) {
            return back()->withErrors(['nurse_id' => 'This nurse already has the maximum of five active assignments.']);
        }
        $duplicate = NursingAssignment::where('hospital_admission_id', $data['hospital_admission_id'])->where('status', 'active')->exists();
        if ($duplicate) {
            return back()->withErrors(['hospital_admission_id' => 'This admission already has an active nursing assignment.']);
        }
        DB::transaction(function () use ($data, $restaurantId, $user): void {
            $assignment = NursingAssignment::create($data + ['restaurant_id' => $restaurantId, 'status' => 'active']);
            $this->recordEvent($assignment, $user, 'assigned', null, $assignment->nurse_id, $assignment->notes);
        });
        return back()->with('success', 'Nurse assigned.');
    }

    public function reassign(Request $request, NursingAssignment $assignment)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId() === $assignment->restaurant_id, 404);
        abort_unless($assignment->status === 'active', 422);
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'nurse_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'nurse')->where('is_active', true))],
            'shift' => ['required', Rule::in(['morning', 'evening', 'night'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (NursingAssignment::where('nurse_id', $data['nurse_id'])->where('status', 'active')->count() >= 5) {
            return back()->withErrors(['nurse_id' => 'This nurse already has the maximum of five active assignments.']);
        }
        DB::transaction(function () use ($assignment, $data, $user): void {
            $previousNurseId = $assignment->nurse_id;
            $assignment->update(array_filter($data, static fn ($value) => $value !== null));
            $this->recordEvent($assignment, $user, 'reassigned', $previousNurseId, $assignment->nurse_id, $data['notes'] ?? null);
        });
        return back()->with('success', 'Nurse assignment updated.');
    }

    public function end(Request $request, NursingAssignment $assignment)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId() === $assignment->restaurant_id, 404);
        abort_unless($assignment->status === 'active', 422);
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($assignment, $data, $user): void {
            $previousNurseId = $assignment->nurse_id;
            $assignment->update(['status' => $data['status'], 'ended_at' => now()]);
            $this->recordEvent($assignment, $user, $data['status'], $previousNurseId, null, $data['notes'] ?? $assignment->notes);
        });
        return back()->with('success', 'Nursing assignment ended.');
    }

    private function recordEvent(
        NursingAssignment $assignment,
        User $actor,
        string $action,
        ?int $previousNurseId,
        ?int $newNurseId,
        ?string $notes
    ): void {
        NursingAssignmentEvent::create([
            'restaurant_id' => $assignment->restaurant_id,
            'nursing_assignment_id' => $assignment->id,
            'action' => $action,
            'previous_nurse_id' => $previousNurseId,
            'new_nurse_id' => $newNurseId,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }
}
