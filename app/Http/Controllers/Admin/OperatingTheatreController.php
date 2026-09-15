<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\OperatingRoom;
use App\Models\Surgery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperatingTheatreController extends Controller
{
    private const PREOP_ITEMS = ['identity_confirmed', 'site_marked', 'fasting_confirmed', 'labs_reviewed'];

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true), 403);
        return $user;
    }

    private function admission(User $user, int $id): HospitalAdmission
    {
        $admission = HospitalAdmission::where('restaurant_id', $user->effectiveRestaurantId())
            ->whereIn('status', ['admitted', 'transferred'])->findOrFail($id);
        if ($user->staff_type === 'nurse' && ! $user->nursingAssignments()->where('hospital_admission_id', $id)->where('status', 'active')->exists()) {
            abort(403);
        }
        return $admission;
    }

    private function assertNoOverlap(int $restaurantId, int $roomId, Carbon $start, Carbon $end, ?int $ignore = null): void
    {
        $query = Surgery::where('restaurant_id', $restaurantId)->where('operating_room_id', $roomId)
            ->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        if ($ignore) $query->where('id', '!=', $ignore);
        abort_if($query->exists(), 422, 'The operating room is already booked during this time.');
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $user->staff_type === 'nurse' && ! $user->isRestaurantManager()
            ? $user->nursingAssignments()->where('status', 'active')->pluck('hospital_admission_id') : null;

        return view('manager.operating-theatre.index', [
            'surgeries' => Surgery::with(['patient', 'admission', 'operatingRoom', 'surgeon', 'anaesthetist', 'assistantSurgeon'])->latest('starts_at')
                ->when($admissionIds !== null, fn ($q) => $q->whereIn('hospital_admission_id', $admissionIds))->paginate(20),
            'rooms' => OperatingRoom::where('status', 'active')->orderBy('name')->get(),
            'admissions' => HospitalAdmission::with('patient')->whereIn('status', ['admitted', 'transferred'])
                ->when($admissionIds !== null, fn ($q) => $q->whereIn('id', $admissionIds))->orderBy('admitted_at')->get(),
            'doctors' => User::where('restaurant_id', $user->effectiveRestaurantId())->where('staff_type', 'doctor')->orderBy('name')->get(),
            'canManage' => $user->isRestaurantManager(),
            'canSchedule' => $user->isRestaurantManager() || $user->staff_type === 'doctor',
        ]);
    }

    public function storeRoom(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:40', Rule::unique('operating_rooms', 'code')->where(fn ($q) => $q->where('restaurant_id', $user->effectiveRestaurantId()))], 'notes' => ['nullable', 'string', 'max:2000']]);
        OperatingRoom::create($data + ['restaurant_id' => $user->effectiveRestaurantId(), 'status' => 'active']);
        return back()->with('success', 'Operating room added.');
    }

    public function updateRoom(Request $request, OperatingRoom $room)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403);
        abort_unless((int) $room->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'string', 'max:40', Rule::unique('operating_rooms', 'code')->where(fn ($q) => $q->where('restaurant_id', $user->effectiveRestaurantId()))->ignore($room->id)], 'status' => ['required', Rule::in(['active', 'inactive'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $room->update($data);
        return back()->with('success', 'Operating room updated.');
    }

    public function storeSurgery(Request $request)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403);
        $restaurantId = (int) $user->effectiveRestaurantId();
        $admission = $this->admission($user, (int) $request->input('hospital_admission_id'));
        $data = $request->validate([
            'hospital_admission_id' => ['required', 'integer', Rule::exists('hospital_admissions', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->whereIn('status', ['admitted', 'transferred']))],
            'operating_room_id' => ['required', 'integer', Rule::exists('operating_rooms', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'active'))],
            'surgeon_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'anaesthetist_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'assistant_surgeon_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'surgical_team_details' => ['nullable', 'string', 'max:5000'],
            'consent_status' => ['nullable', Rule::in(['pending', 'obtained', 'declined'])],
            'preop_status' => ['nullable', Rule::in(['pending', 'incomplete', 'ready'])],
            'preop_checklist' => ['nullable', 'array'],
            'preop_checklist.*' => [Rule::in(self::PREOP_ITEMS)],
            'postoperative_notes' => ['nullable', 'string', 'max:10000'],
            'procedure_name' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['consent_status'] = $data['consent_status'] ?? 'pending';
        $data['preop_status'] = $data['preop_status'] ?? 'pending';
        $this->assertReadiness($data, false);
        $start = Carbon::parse($data['starts_at']); $end = Carbon::parse($data['ends_at']);
        abort_if($start->lt($admission->admitted_at), 422, 'Surgery must start on or after admission.');
        abort_if($admission->discharged_at && $end->gt($admission->discharged_at), 422, 'Surgery must end before discharge.');
        $this->assertNoOverlap($restaurantId, (int) $data['operating_room_id'], $start, $end);
        Surgery::create($data + ['restaurant_id' => $restaurantId, 'patient_id' => $admission->patient_id, 'scheduled_by' => $user->id, 'surgery_number' => 'SUR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)), 'status' => 'scheduled']);
        return back()->with('success', 'Surgery scheduled.');
    }

    public function updateSurgery(Request $request, Surgery $surgery)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager() || $user->staff_type === 'doctor', 403);
        abort_unless((int) $surgery->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'operating_room_id' => ['required', 'integer', Rule::exists('operating_rooms', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('status', 'active'))],
            'surgeon_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'anaesthetist_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'assistant_surgeon_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'surgical_team_details' => ['nullable', 'string', 'max:5000'],
            'consent_status' => ['nullable', Rule::in(['pending', 'obtained', 'declined'])],
            'preop_status' => ['nullable', Rule::in(['pending', 'incomplete', 'ready'])],
            'preop_checklist' => ['nullable', 'array'],
            'preop_checklist.*' => [Rule::in(self::PREOP_ITEMS)],
            'postoperative_notes' => ['nullable', 'string', 'max:10000'],
            'procedure_name' => ['required', 'string', 'max:255'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['consent_status'] = $data['consent_status'] ?? $surgery->consent_status ?? 'pending';
        $data['preop_status'] = $data['preop_status'] ?? $surgery->preop_status ?? 'pending';
        $data['surgeon_id'] = $data['surgeon_id'] ?? $surgery->surgeon_id;
        $this->assertReadiness($data, in_array($data['status'], ['in_progress', 'completed'], true));
        if ($data['status'] === 'completed' && blank($data['postoperative_notes'] ?? null)) {
            abort(422, 'Post-operative notes are required before completing surgery.');
        }
        $admission = $surgery->admission;
        $start = Carbon::parse($data['starts_at']); $end = Carbon::parse($data['ends_at']);
        abort_if($start->lt($admission->admitted_at) || ($admission->discharged_at && $end->gt($admission->discharged_at)), 422, 'Surgery timestamps must be within the admission.');
        $this->assertNoOverlap($restaurantId, (int) $data['operating_room_id'], $start, $end, $surgery->id);
        $data['completed_at'] = $data['status'] === 'completed' ? ($surgery->completed_at ?? now()) : null;
        $surgery->update($data);
        return back()->with('success', 'Surgery updated.');
    }

    private function assertReadiness(array $data, bool $transitioning): void
    {
        if (! $transitioning) {
            return;
        }
        if (($data['consent_status'] ?? null) !== 'obtained') {
            abort(422, 'Documented patient consent is required before surgery starts.');
        }
        $checklist = array_values(array_unique($data['preop_checklist'] ?? []));
        if (($data['preop_status'] ?? null) !== 'ready' || count(array_diff(self::PREOP_ITEMS, $checklist)) > 0) {
            abort(422, 'Complete the pre-operative checklist before surgery starts.');
        }
    }
}
