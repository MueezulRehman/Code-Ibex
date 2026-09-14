<?php

namespace Tests\Feature;

use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleServiceDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_modules_and_business_types(): void
    {
        ModuleService::ensureDefaults();

        $this->assertDatabaseHas('modules', ['key' => 'inventory']);
        $this->assertDatabaseHas('modules', ['key' => 'stock']);
        $this->assertDatabaseHas('business_types', ['name' => 'Medical Store']);
        $this->assertDatabaseHas('business_types', ['name' => 'Other / Custom']);
        $this->assertDatabaseHas('business_types', ['name' => 'Hospital']);
        $this->assertDatabaseHas('business_types', ['name' => 'Professional Hospital']);
        $this->assertDatabaseHas('modules', ['key' => 'hospital-admissions']);
        $this->assertDatabaseHas('modules', ['key' => 'hospital-departments']);
        $this->assertDatabaseHas('modules', ['key' => 'hospital-wards-beds']);
        $this->assertDatabaseHas('modules', ['key' => 'hospital-billing']);
    }
}
