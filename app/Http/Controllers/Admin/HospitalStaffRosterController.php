<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HospitalStaffRoster;
use App\Models\User;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HospitalStaffRosterController extends Controller
{
    private const ACTIVE_STATUSES = ['scheduled', 'confirmed'];
    private const STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled'];
    private const SHIFT_TYPES = ['morning', 'day', 'evening', 'night', 'on_call'];

    public function index()
    {
        $user = $this->authorizeView();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $viewOnly = ! $this->canManage($user);

        $rosters = HospitalStaffRoster::with(['staffUser', 'department', 'ward'])
            ->when($viewOnly, fn ($query) => $query->where('staff_user_id', $user->id))
            ->orderBy('shift_date')
            ->orderBy('starts_at')
            ->paginate(25);

        return view('manager.hospital-staff-rosters.index', [
            'rosters' => $rosters,
            'viewOnly' => $viewOnly,
            'staff' => $viewOnly ? collect() : User::query()
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->whereIn('staff_type', ['nurse', 'doctor'])
                ->orderBy('name')
                ->get(),
            'departments' => $viewOnly ? collect() : Department::query()
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'wards' => $viewOnly ? collect() : Ward::query()
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeManager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $this->validatedRoster($request, $restaurantId);
        $this->ensureNoOverlap($data['staff_user_id'], $data['starts_at'], $data['ends_at']);

        DB::transaction(function () use ($data, $restaurantId): void {
            HospitalStaffRoster::where('staff_user_id', $data['staff_user_id'])
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();
            HospitalStaffRoster::create($data + [
                'restaurant_id' => $restaurantId,
                'status' => $data['status'] ?? 'scheduled',
            ]);
        });

        return back()->with('success', 'Staff roster shift scheduled.');
    }

    public function update(Request $request, HospitalStaffRoster $roster)
    {
        $user = $this->authorizeManager();
        $this->ensureRosterTenant($roster, $user);
        $data = $this->validatedRoster($request, (int) $user->effectiveRestaurantId(), $roster);
        $this->ensureNoOverlap($data['staff_user_id'], $data['starts_at'], $data['ends_at'], $roster->id);
        $roster->update($data);

        return back()->with('success', 'Staff roster shift updated.');
    }

    public function destroy(HospitalStaffRoster $roster)
    {
        $user = $this->authorizeManager();
        $this->ensureRosterTenant($roster, $user);
        $roster->delete();

        return back()->with('success', 'Staff roster shift deleted.');
    }

    public function confirm(HospitalStaffRoster $roster)
    {
        $user = $this->authorizeManager();
        $this->ensureRosterTenant($roster, $user);
        abort_unless($roster->status === 'scheduled', 422, 'Only scheduled shifts can be confirmed.');
        $roster->update(['status' => 'confirmed']);

        return back()->with('success', 'Staff roster shift confirmed.');
    }

    public function cancel(HospitalStaffRoster $roster)
    {
        $user = $this->authorizeManager();
        $this->ensureRosterTenant($roster, $user);
        abort_unless(in_array($roster->status, self::ACTIVE_STATUSES, true), 422, 'This shift cannot be cancelled.');
        $roster->update(['status' => 'cancelled']);

        return back()->with('success', 'Staff roster shift cancelled.');
    }

    private function authorizeView(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);

        $moduleEnabled = $user->effectiveRestaurant()?->isModuleEnabled('hospital-staff-rosters');
        $isActiveClinicalStaff = (bool) $user->is_active
            && in_array($user->staff_type, ['nurse', 'doctor'], true);
        abort_unless($moduleEnabled && ($this->canManage($user) || $isActiveClinicalStaff), 403);

        return $user;
    }

    private function authorizeManager(): User
    {
        $user = $this->authorizeView();
        abort_unless($this->canManage($user), 403, 'Only hospital managers can manage staff rosters.');

        return $user;
    }

    private function canManage(User $user): bool
    {
        return $user->isRestaurantManager() && $user->hasModuleAccess('hospital-staff-rosters');
    }

    private function ensureRosterTenant(HospitalStaffRoster $roster, User $user): void
    {
        abort_unless((int) $roster->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
    }

    private function validatedRoster(Request $request, int $restaurantId, ?HospitalStaffRoster $existing = null): array
    {
        $required = $existing ? ['sometimes'] : ['required'];
        $data = $request->validate([
            'staff_user_id' => [...$required, 'integer', Rule::exists('users', 'id')->where(
                fn ($query) => $query->where('restaurant_id', $restaurantId)
                    ->where('is_active', true)
                    ->whereIn('staff_type', ['nurse', 'doctor'])
            )],
            'shift_date' => [...$required, 'date'],
            'starts_at' => [...$required, 'string', 'max:50'],
            'ends_at' => [...$required, 'string', 'max:50'],
            'shift_type' => [...$required, Rule::in(self::SHIFT_TYPES)],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId))],
            'ward_id' => ['nullable', 'integer', Rule::exists('wards', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($existing) {
            $data = array_merge([
                'staff_user_id' => $existing->staff_user_id,
                'shift_date' => $existing->shift_date?->toDateString(),
                'starts_at' => $existing->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $existing->ends_at?->format('Y-m-d H:i:s'),
                'shift_type' => $existing->shift_type,
                'status' => $existing->status,
                'department_id' => $existing->department_id,
                'ward_id' => $existing->ward_id,
                'notes' => $existing->notes,
            ], $data);
        }

        $shiftDate = Carbon::parse($data['shift_date'])->startOfDay();
        $today = now()->startOfDay();
        if (! $shiftDate->greaterThanOrEqualTo($today->copy()->subDays(30))
            || ! $shiftDate->lessThanOrEqualTo($today->copy()->addYear())) {
            throw ValidationException::withMessages([
                'shift_date' => 'Shift date must be within the scheduling window.',
            ]);
        }

        $data['shift_date'] = $shiftDate->toDateString();
        $data['starts_at'] = $this->normaliseDateTime($data['starts_at'], $shiftDate, 'starts_at');
        $data['ends_at'] = $this->normaliseDateTime($data['ends_at'], $shiftDate, 'ends_at');

        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => 'Shift end must be after shift start.']);
        }
        if ($start->diffInMinutes($end) > 24 * 60) {
            throw ValidationException::withMessages(['ends_at' => 'A shift cannot be longer than 24 hours.']);
        }

        return $data;
    }

    private function normaliseDateTime(string $value, Carbon $shiftDate, string $field): string
    {
        try {
            $timeOnly = preg_match('/^\s*\d{1,2}:\d{2}(?::\d{2})?\s*$/', $value) === 1;
            $parsed = $timeOnly
                ? Carbon::parse($shiftDate->toDateString().' '.$value)
                : Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Enter a valid shift date and time.']);
        }

        if ($parsed->toDateString() !== $shiftDate->toDateString()) {
            throw ValidationException::withMessages([$field => 'Shift times must be on the selected shift date.']);
        }

        return $parsed->format('Y-m-d H:i:s');
    }

    private function ensureNoOverlap(int $staffUserId, string $startsAt, string $endsAt, ?int $ignoreId = null): void
    {
        $overlap = HospitalStaffRoster::query()
            ->where('staff_user_id', $staffUserId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($ignoreId, fn ($query) => $query->where('hospital_staff_rosters.id', '<>', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'This staff member already has an overlapping scheduled or confirmed shift.',
            ]);
        }
    }
}
