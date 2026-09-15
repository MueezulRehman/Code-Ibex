<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalAdmission;
use App\Models\HospitalClinicalNote;
use App\Models\HospitalClinicalNoteEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HospitalClinicalNoteController extends Controller
{
    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->effectiveRestaurantId(), 403);
        abort_unless($user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true), 403);
        return $user;
    }

    private function canAccessAdmission(User $user, HospitalAdmission $admission): bool
    {
        if ((int) $admission->restaurant_id !== (int) $user->effectiveRestaurantId()) {
            return false;
        }

        if ($user->isRestaurantManager()) {
            return true;
        }

        if ($user->staff_type === 'doctor') {
            return $admission->doctor?->user_id === $user->id;
        }

        return $user->staff_type === 'nurse'
            && $admission->status !== 'discharged'
            && $admission->nursingAssignments()
                ->where('nurse_id', $user->id)
                ->where('status', 'active')
                ->exists();
    }

    private function admissionQuery(User $user)
    {
        $query = HospitalAdmission::with('patient')->where('restaurant_id', $user->effectiveRestaurantId());

        if ($user->isRestaurantManager()) {
            return $query;
        }

        if ($user->staff_type === 'doctor') {
            return $query->whereHas('doctor', fn ($doctor) => $doctor->where('user_id', $user->id));
        }

        return $query->where('status', '!=', 'discharged')
            ->whereHas('nursingAssignments', fn ($assignment) => $assignment
                ->where('nurse_id', $user->id)
                ->where('status', 'active'));
    }

    public function index()
    {
        $user = $this->user();
        $admissionIds = $this->admissionQuery($user)->pluck('id');

        return view('manager.hospital-clinical-notes.index', [
            'notes' => HospitalClinicalNote::with(['admission.patient', 'author', 'events.changedBy'])
                ->whereIn('hospital_admission_id', $admissionIds)
                ->latest('recorded_at')
                ->paginate(25),
            'admissions' => $this->admissionQuery($user)->orderBy('admitted_at')->get(),
            'canRecord' => $user->isRestaurantManager() || in_array($user->staff_type, ['doctor', 'nurse'], true),
            'canCorrect' => $user->isRestaurantManager(),
            'allowedTypes' => $user->isRestaurantManager()
                ? ['clinical', 'progress', 'nursing']
                : ($user->staff_type === 'nurse' ? ['nursing'] : ['clinical', 'progress']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->user();
        $restaurantId = (int) $user->effectiveRestaurantId();
        $data = $request->validate([
            'hospital_admission_id' => [
                'required', 'integer',
                Rule::exists('hospital_admissions', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurantId)),
            ],
            'note_type' => ['required', Rule::in(['clinical', 'progress', 'nursing'])],
            'content' => ['required', 'string', 'max:20000'],
            'recorded_at' => ['required', 'date'],
        ]);

        $admission = HospitalAdmission::where('restaurant_id', $restaurantId)->findOrFail($data['hospital_admission_id']);
        abort_unless($this->canAccessAdmission($user, $admission), 403);
        abort_unless(
            $user->isRestaurantManager()
                || ($user->staff_type === 'doctor' && in_array($data['note_type'], ['clinical', 'progress'], true))
                || ($user->staff_type === 'nurse' && $data['note_type'] === 'nursing'),
            403,
            'This staff type cannot record the selected note type.'
        );
        $this->validateRecordedAt($data['recorded_at'], $admission);

        DB::transaction(function () use ($data, $admission, $restaurantId, $user): void {
            $note = HospitalClinicalNote::create([
                'restaurant_id' => $restaurantId,
                'hospital_admission_id' => $admission->id,
                'author_id' => $user->id,
                'note_type' => $data['note_type'],
                'content' => $data['content'],
                'recorded_at' => $data['recorded_at'],
                'status' => 'active',
            ]);
            $this->recordEvent($note, $user, 'recorded', null, $note->content, null, $note->recorded_at, 'Initial note recorded.');
        });

        return back()->with('success', 'Clinical note recorded.');
    }

    public function update(Request $request, HospitalClinicalNote $note)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers can correct clinical notes.');
        abort_unless((int) $note->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        abort_unless($note->status === 'active', 422);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
            'recorded_at' => ['required', 'date'],
            'correction_notes' => ['required', 'string', 'max:2000'],
        ]);
        $admission = $note->admission;
        $this->validateRecordedAt($data['recorded_at'], $admission);
        $previousContent = $note->content;
        $previousRecordedAt = $note->recorded_at;

        DB::transaction(function () use ($note, $data, $user, $previousContent, $previousRecordedAt): void {
            $note->update(['content' => $data['content'], 'recorded_at' => $data['recorded_at']]);
            $this->recordEvent($note, $user, 'corrected', $previousContent, $note->content, $previousRecordedAt, $note->recorded_at, $data['correction_notes']);
        });

        return back()->with('success', 'Clinical note corrected and audit history recorded.');
    }

    public function void(Request $request, HospitalClinicalNote $note)
    {
        $user = $this->user();
        abort_unless($user->isRestaurantManager(), 403, 'Only managers can void clinical notes.');
        abort_unless((int) $note->restaurant_id === (int) $user->effectiveRestaurantId(), 404);
        abort_unless($note->status === 'active', 422);
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($note, $data, $user): void {
            $note->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $data['void_reason']]);
            $this->recordEvent($note, $user, 'voided', $note->content, null, $note->recorded_at, null, $data['void_reason']);
        });

        return back()->with('success', 'Clinical note voided and audit history recorded.');
    }

    private function validateRecordedAt(string $recordedAt, HospitalAdmission $admission): void
    {
        $validator = Validator::make([], []);
        $validator->after(function ($validator) use ($recordedAt, $admission): void {
            $timestamp = \Illuminate\Support\Carbon::parse($recordedAt);
            if ($timestamp->lt($admission->admitted_at)) {
                $validator->errors()->add('recorded_at', 'The note timestamp cannot be before the admission.');
            }
            if ($admission->discharged_at && $timestamp->gt($admission->discharged_at)) {
                $validator->errors()->add('recorded_at', 'The note timestamp cannot be after discharge.');
            }
        });
        $validator->validate();
    }

    private function recordEvent(
        HospitalClinicalNote $note,
        User $actor,
        string $action,
        ?string $previousContent,
        ?string $newContent,
        $previousRecordedAt,
        $newRecordedAt,
        ?string $notes
    ): void {
        HospitalClinicalNoteEvent::create([
            'restaurant_id' => $note->restaurant_id,
            'hospital_clinical_note_id' => $note->id,
            'action' => $action,
            'previous_content' => $previousContent,
            'new_content' => $newContent,
            'previous_recorded_at' => $previousRecordedAt,
            'new_recorded_at' => $newRecordedAt,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }
}
