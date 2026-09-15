<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Restaurant;
use App\Services\ModuleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_modules_page(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'super@example.com',
            'phone' => '03001234567',
        ]);

        $response = $this->actingAs($user)->get(route('admin.modules.index'));

        $response->assertStatus(200);
    }

    public function test_super_admin_can_open_business_types_page(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'super2@example.com',
            'phone' => '03007654321',
        ]);

        $response = $this->actingAs($user)->get(route('admin.business-types.index'));

        $response->assertStatus(200);
    }

    public function test_super_admin_can_see_professional_hospital_bundle_and_locked_roadmap_modules(): void
    {
        ModuleService::ensureDefaults();
        $type = BusinessType::where('name', 'Professional Hospital')->firstOrFail();
        $restaurant = Restaurant::create([
            'business_type_id' => $type->id,
            'name' => 'Professional Hospital Demo',
            'slug' => 'professional-hospital-demo-test',
            'status' => 'active',
            'enabled_modules' => ModuleService::professionalHospitalModuleKeys(),
        ]);
        $user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'hospital-super@example.com',
            'phone' => '03007654322',
        ]);

        $this->actingAs($user)
            ->get(route('admin.restaurants.index'))
            ->assertOk()
            ->assertSee('Professional Hospital')
            ->assertSee('Hospital bundle');

        $this->actingAs($user)
            ->get(route('admin.restaurants.edit', $restaurant))
            ->assertOk()
            ->assertSee('Hospital Emergency Department')
            ->assertSee('Unavailable');
    }
}
