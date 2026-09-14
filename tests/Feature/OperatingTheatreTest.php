<?php

namespace Tests\Feature;

use App\Models\HospitalAdmission;
use App\Models\OperatingRoom;
use App\Models\Patient;
use App\Models\Restaurant;
use App\Models\Surgery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingTheatreTest extends TestCase
{
    use RefreshDatabase;

    public function test_theatre_is_module_gated_and_denies_non_clinical_staff(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital', 'slug' => 'theatre-gate', 'status' => 'active', 'enabled_modules' => []]);
        $user = User::create(['name' => 'Reception', 'email' => 'theatre-reception@example.com', 'phone' => '03000000101', 'role' => 'staff', 'staff_type' => 'receptionist', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $this->actingAs($user)->get(route('manager.operating-theatre.index'))->assertForbidden();

        $restaurant->update(['enabled_modules' => ['hospital-operating-theatre']]);
        $this->actingAs($user)->get(route('manager.operating-theatre.index'))->assertForbidden();
    }

    public function test_manager_schedules_surgery_and_overlaps_are_rejected(): void
    {
        $restaurant = Restaurant::create(['name' => 'Hospital', 'slug' => 'theatre-flow', 'status' => 'active', 'enabled_modules' => ['hospital-operating-theatre']]);
        $manager = User::create(['name' => 'Manager', 'email' => 'theatre-manager@example.com', 'phone' => '03000000102', 'role' => 'manager', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $doctor = User::create(['name' => 'Surgeon', 'email' => 'theatre-doctor@example.com', 'phone' => '03000000103', 'role' => 'staff', 'staff_type' => 'doctor', 'restaurant_id' => $restaurant->id, 'password' => bcrypt('password')]);
        $patient = Patient::create(['restaurant_id' => $restaurant->id, 'patient_number' => 'PAT-THEATRE', 'name' => 'Surgical Patient']);
        $admission = HospitalAdmission::create(['restaurant_id' => $restaurant->id, 'admission_number' => 'ADM-THEATRE', 'patient_id' => $patient->id, 'status' => 'admitted', 'admitted_at' => '2026-09-14 08:00:00']);
        $room = OperatingRoom::create(['restaurant_id' => $restaurant->id, 'name' => 'OT 1', 'code' => 'OT1']);

        $payload = ['hospital_admission_id' => $admission->id, 'operating_room_id' => $room->id, 'surgeon_id' => $doctor->id, 'procedure_name' => 'Appendectomy', 'starts_at' => '2026-09-14 09:00:00', 'ends_at' => '2026-09-14 10:00:00'];
        $this->actingAs($manager)->post(route('manager.operating-theatre.surgeries.store'), $payload)->assertRedirect();
        $this->assertDatabaseHas('surgeries', ['status' => 'scheduled', 'patient_id' => $patient->id]);
        $this->actingAs($manager)->post(route('manager.operating-theatre.surgeries.store'), $payload)->assertStatus(422);

        $surgery = Surgery::where('restaurant_id', $restaurant->id)->firstOrFail();
        $this->actingAs($manager)->patch(route('manager.operating-theatre.surgeries.update', $surgery), [
            'operating_room_id' => $room->id,
            'procedure_name' => $surgery->procedure_name,
            'starts_at' => $surgery->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $surgery->ends_at->format('Y-m-d H:i:s'),
            'status' => 'completed',
            'notes' => 'Procedure completed.',
        ])->assertRedirect();
        $this->assertDatabaseHas('surgeries', ['id' => $surgery->id, 'status' => 'completed']);
    }
}
