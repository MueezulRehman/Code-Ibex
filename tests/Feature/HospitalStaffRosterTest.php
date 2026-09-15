<?php

namespace Tests\Feature;

use App\Models\HospitalStaffRoster;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalStaffRosterTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, array $modules = ['hospital-staff-rosters']): Restaurant
    {
        return Restaurant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
            'enabled_modules' => $modules,
        ]);
    }

    private function user(Restaurant $restaurant, string $email, string $role = 'staff', ?string $staffType = null): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'phone' => '03'.str_pad((string) abs(crc32($email)) % 1000000000, 9, '0', STR_PAD_LEFT),
            'role' => $role,
            'staff_type' => $staffType,
            'is_active' => true,
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_manager_can_schedule_confirm_and_cancel_a_roster(): void
    {
        ModuleService::ensureDefaults();
        $restaurant = $this->tenant('roster-lifecycle');
        $manager = $this->user($restaurant, 'roster-manager@example.com', 'manager');
        $nurse = $this->user($restaurant, 'roster-nurse@example.com', 'staff', 'nurse');
        $date = now()->toDateString();

        $response = $this->actingAs($manager)->post(route('manager.staff-rosters.store'), [
            'staff_user_id' => $nurse->id,
            'shift_date' => $date,
            'starts_at' => $date.' 08:00',
            'ends_at' => $date.' 16:00',
            'shift_type' => 'day',
        ]);

        $response->assertRedirect();
        $roster = HospitalStaffRoster::firstOrFail();
        $this->assertSame('scheduled', $roster->status);
        $this->actingAs($manager)->patch(route('manager.staff-rosters.confirm', $roster))->assertRedirect();
        $this->assertSame('confirmed', $roster->fresh()->status);
        $this->actingAs($manager)->patch(route('manager.staff-rosters.cancel', $roster))->assertRedirect();
        $this->assertSame('cancelled', $roster->fresh()->status);
    }

    public function test_overlapping_active_shifts_are_rejected_but_cancelled_shifts_do_not_block(): void
    {
        ModuleService::ensureDefaults();
        $restaurant = $this->tenant('roster-overlap');
        $manager = $this->user($restaurant, 'overlap-manager@example.com', 'manager');
        $doctor = $this->user($restaurant, 'overlap-doctor@example.com', 'staff', 'doctor');
        $date = now()->toDateString();
        $payload = [
            'staff_user_id' => $doctor->id,
            'shift_date' => $date,
            'starts_at' => $date.' 08:00',
            'ends_at' => $date.' 16:00',
            'shift_type' => 'day',
        ];

        $this->actingAs($manager)->post(route('manager.staff-rosters.store'), $payload)->assertRedirect();
        $this->actingAs($manager)->post(route('manager.staff-rosters.store'), $payload + [
            'starts_at' => $date.' 15:00',
            'ends_at' => $date.' 20:00',
        ])->assertSessionHasErrors('starts_at');

        $roster = HospitalStaffRoster::firstOrFail();
        $this->actingAs($manager)->patch(route('manager.staff-rosters.cancel', $roster))->assertRedirect();
        $this->actingAs($manager)->post(route('manager.staff-rosters.store'), $payload)->assertRedirect();
        $this->assertCount(2, HospitalStaffRoster::all());
    }

    public function test_active_clinical_staff_can_only_view_own_rosters(): void
    {
        ModuleService::ensureDefaults();
        $restaurant = $this->tenant('roster-staff-view');
        $nurse = $this->user($restaurant, 'view-nurse@example.com', 'staff', 'nurse');
        $doctor = $this->user($restaurant, 'view-doctor@example.com', 'staff', 'doctor');
        $date = now()->toDateString();
        HospitalStaffRoster::create([
            'restaurant_id' => $restaurant->id,
            'staff_user_id' => $nurse->id,
            'shift_date' => $date,
            'starts_at' => $date.' 08:00',
            'ends_at' => $date.' 16:00',
            'shift_type' => 'day',
        ]);
        HospitalStaffRoster::create([
            'restaurant_id' => $restaurant->id,
            'staff_user_id' => $doctor->id,
            'shift_date' => $date,
            'starts_at' => $date.' 16:00',
            'ends_at' => $date.' 23:00',
            'shift_type' => 'evening',
        ]);

        $this->actingAs($nurse)->get(route('manager.staff-rosters.index'))
            ->assertOk()
            ->assertSee('08:00')
            ->assertDontSee('23:00');
        $receptionist = $this->user($restaurant, 'view-receptionist@example.com', 'staff', 'receptionist');
        $this->actingAs($receptionist)->get(route('manager.staff-rosters.index'))->assertForbidden();
    }

    public function test_rosters_are_tenant_isolated_and_dates_are_reasonable(): void
    {
        ModuleService::ensureDefaults();
        $first = $this->tenant('roster-one');
        $second = $this->tenant('roster-two');
        $manager = $this->user($first, 'isolation-manager@example.com', 'manager');
        $otherNurse = $this->user($second, 'isolation-nurse@example.com', 'staff', 'nurse');
        $date = now()->toDateString();
        $roster = HospitalStaffRoster::create([
            'restaurant_id' => $second->id,
            'staff_user_id' => $otherNurse->id,
            'shift_date' => $date,
            'starts_at' => $date.' 08:00',
            'ends_at' => $date.' 16:00',
            'shift_type' => 'day',
        ]);

        $this->actingAs($manager)->patch(route('manager.staff-rosters.cancel', $roster))->assertNotFound();
        $this->actingAs($manager)->post(route('manager.staff-rosters.store'), [
            'staff_user_id' => $this->user($first, 'date-nurse@example.com', 'staff', 'nurse')->id,
            'shift_date' => now()->addYears(2)->toDateString(),
            'starts_at' => now()->addYears(2)->toDateString().' 08:00',
            'ends_at' => now()->addYears(2)->toDateString().' 16:00',
            'shift_type' => 'day',
        ])->assertSessionHasErrors('shift_date');
    }
}
