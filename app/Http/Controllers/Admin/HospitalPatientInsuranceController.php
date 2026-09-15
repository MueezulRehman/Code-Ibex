<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\HospitalPatientInsurance;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HospitalPatientInsuranceController extends Controller
{
    private function manager(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager(), 403, 'Only managers and owners can manage hospital insurance.');

        return $user;
    }

    private function policyRules(int $restaurantId, ?int $ignoreId = null): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'provider_id' => ['nullable', 'integer', Rule::exists('insurance_providers', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId))],
            'payer' => ['required', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'member_number' => ['nullable', 'string', 'max:100'],
            'coverage_starts' => ['nullable', 'date'],
            'coverage_ends' => ['nullable', 'date', 'after_or_equal:coverage_starts'],
            'status' => ['required', Rule::in(['active', 'pending', 'inactive', 'expired'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function rejectDuplicate(array $data, int $restaurantId, ?int $ignoreId = null): void
    {
        $query = HospitalPatientInsurance::where('restaurant_id', $restaurantId)
            ->where('patient_id', $data['patient_id'])
            ->where('payer', trim($data['payer']))
            ->where(function ($query) use ($data): void {
                if ($data['policy_number'] ?? null) {
                    $query->where('policy_number', trim($data['policy_number']));
                } else {
                    $query->whereNull('policy_number');
                }
            })
            ->where(function ($query) use ($data): void {
                if ($data['member_number'] ?? null) {
                    $query->where('member_number', trim($data['member_number']));
                } else {
                    $query->whereNull('member_number');
                }
            })
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId));

        if ($query->exists()) {
            abort(422, 'This patient insurance policy already exists.');
        }
    }

    public function index()
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();

        $policies = HospitalPatientInsurance::with(['patient', 'provider'])
            ->where('restaurant_id', $restaurantId)->latest('id')->paginate(20);
        $allPolicies = HospitalPatientInsurance::where('restaurant_id', $restaurantId)->latest('id')->get();

        return view('manager.hospital-billing.insurance', [
            'policies' => $policies,
            'allPolicies' => $allPolicies,
            'patients' => Patient::where('restaurant_id', $restaurantId)->orderBy('name')->get(),
            'providers' => InsuranceProvider::where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(),
            'admissions' => HospitalAdmission::with(['patient', 'insurancePolicy'])
                ->where('restaurant_id', $restaurantId)
                ->whereIn('status', ['admitted', 'transferred'])
                ->latest('admitted_at')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate($this->policyRules($restaurantId));
        $this->rejectDuplicate($data, $restaurantId);
        $data['restaurant_id'] = $restaurantId;
        $data['payer'] = trim($data['payer']);
        $data['policy_number'] = isset($data['policy_number']) ? trim($data['policy_number']) : null;
        $data['member_number'] = isset($data['member_number']) ? trim($data['member_number']) : null;
        HospitalPatientInsurance::create($data);

        return back()->with('success', 'Patient insurance policy added.');
    }

    public function update(Request $request, HospitalPatientInsurance $policy)
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        abort_unless((int) $policy->restaurant_id === $restaurantId, 404);
        $data = $request->validate($this->policyRules($restaurantId));
        $this->rejectDuplicate($data, $restaurantId, $policy->id);
        $data['payer'] = trim($data['payer']);
        $data['policy_number'] = isset($data['policy_number']) ? trim($data['policy_number']) : null;
        $data['member_number'] = isset($data['member_number']) ? trim($data['member_number']) : null;
        $policy->update($data);

        return back()->with('success', 'Patient insurance policy updated.');
    }

    public function destroy(HospitalPatientInsurance $policy)
    {
        $user = $this->manager();
        abort_unless((int) $policy->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        $policy->delete();

        return back()->with('success', 'Patient insurance policy removed.');
    }

    public function linkAdmission(Request $request, HospitalAdmission $admission)
    {
        $user = $this->manager();
        $restaurantId = (int) $user->effectiveRestaurantId();
        abort_unless((int) $admission->restaurant_id === $restaurantId, 404);
        $data = $request->validate([
            'hospital_patient_insurance_id' => [
                'nullable', 'integer',
                Rule::exists('hospital_patient_insurances', 'id')->where(fn ($q) => $q->where('restaurant_id', $restaurantId)),
            ],
        ]);

        if ($data['hospital_patient_insurance_id'] ?? null) {
            $policy = HospitalPatientInsurance::where('restaurant_id', $restaurantId)
                ->findOrFail($data['hospital_patient_insurance_id']);
            abort_unless((int) $policy->patient_id === (int) $admission->patient_id, 422, 'Insurance policy does not belong to this patient.');
        }

        $admission->update(['hospital_patient_insurance_id' => $data['hospital_patient_insurance_id'] ?? null]);

        return back()->with('success', 'Admission insurance linkage updated.');
    }
}
