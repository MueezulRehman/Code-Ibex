<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    private const STATUSES = ['pending', 'active', 'declined'];

    private function restaurantId(): int
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $restaurantId = $user->effectiveRestaurantId();
        abort_unless($restaurantId !== null, 403);

        return (int) $restaurantId;
    }

    public function index()
    {
        $doctors = Doctor::query()->orderBy('name')->paginate(20);

        return view('manager.doctors.index', compact('doctors'));
    }

    public function create()
    {
        return view('manager.doctors.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $restaurantId = $this->restaurantId();
        $data = $request->validate($this->rules());
        $data['restaurant_id'] = $restaurantId;

        Doctor::create($data);

        return redirect()->route('manager.doctors.index')->with('success', 'Doctor created.');
    }

    public function edit(Doctor $doctor)
    {
        return view('manager.doctors.edit', ['doctor' => $doctor] + $this->formOptions());
    }

    public function update(Request $request, Doctor $doctor)
    {
        $data = $request->validate($this->rules($doctor));
        $doctor->update($data);

        return redirect()->route('manager.doctors.index')->with('success', 'Doctor updated.');
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();

        return redirect()->route('manager.doctors.index')->with('success', 'Doctor deleted.');
    }

    private function formOptions(): array
    {
        $restaurantId = $this->restaurantId();
        return [
            'users' => User::where('restaurant_id', $restaurantId)->where('staff_type', 'doctor')->orderBy('name')->get(),
            'departments' => Department::where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function rules(?Doctor $doctor = null): array
    {
        $restaurantId = $this->restaurantId();
        return [
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('staff_type', 'doctor'))],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)->where('is_active', true))],
        ];
    }
}
