<?php

namespace App\Http\Controllers\Admin;

use App\Models\Doctor;
use App\Models\HospitalAdmission;
use App\Models\User;

trait AdmissionAccess
{
    /**
     * Return true if the given user may view the admission according to
     * standardized rules: managers may view all; nurses may view active
     * assignments; doctors may view when assigned as attending or when the
     * doctor's department matches the admission department.
     */
    private function canViewAdmission(User $user, HospitalAdmission $admission): bool
    {
        if ((int) $admission->restaurant_id !== (int) $user->effectiveRestaurantId()) {
            return false;
        }

        if ($user->isRestaurantManager()) {
            return true;
        }

        if ($user->staff_type === 'nurse') {
            return $admission->status !== 'discharged'
                && $user->nursingAssignments()
                    ->where('hospital_admission_id', $admission->id)
                    ->where('status', 'active')
                    ->exists();
        }

        if ($user->staff_type === 'doctor') {
            $doctor = Doctor::where('restaurant_id', $user->effectiveRestaurantId())
                ->where('user_id', $user->id)->first();
            if (! $doctor) {
                return false;
            }
            return ((int) $admission->doctor_id === (int) $doctor->id)
                || ($doctor->department_id !== null && (int) $admission->department_id === (int) $doctor->department_id);
        }

        return false;
    }

    /**
     * Build a query constrained to admissions visible to the given user.
     * Managers receive an unconstrained query object.
     */
    private function admissionQuery(User $user)
    {
        $query = HospitalAdmission::with('patient')->where('restaurant_id', $user->effectiveRestaurantId());

        if ($user->isRestaurantManager()) {
            return $query;
        }

        if ($user->staff_type === 'nurse') {
            return $query->where('status', '!=', 'discharged')
                ->whereHas('nursingAssignments', fn ($assignment) => $assignment
                    ->where('nurse_id', $user->id)
                    ->where('status', 'active'));
        }

        if ($user->staff_type !== 'doctor') {
            return $query->whereRaw('0=1');
        }

        $doctor = Doctor::where('user_id', $user->id)->where('restaurant_id', $user->effectiveRestaurantId())->first();
        if (! $doctor) {
            return $query->whereRaw('0=1');
        }

        return $query->where(function ($scope) use ($doctor): void {
            $scope->where('doctor_id', $doctor->id);
            if ($doctor->department_id) {
                $scope->orWhere('department_id', $doctor->department_id);
            }
        });
    }
}
