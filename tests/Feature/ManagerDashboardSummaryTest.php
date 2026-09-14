<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_dashboard_shows_period_income_and_expense_summary(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Cafe',
            'slug' => 'test-cafe',
            'status' => 'active',
            'enabled_modules' => ['menu'],
        ]);

        RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $manager = User::create([
            'name' => 'Manager One',
            'email' => 'manager-one@example.com',
            'phone' => '03000000001',
            'role' => 'manager',
            'restaurant_id' => $restaurant->id,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'web')
            ->get('/manager/dashboard')
            ->assertStatus(200)
            ->assertSee('Business Performance')
            ->assertSee('Daily')
            ->assertDontSee('Inpatient operations');
    }
}
