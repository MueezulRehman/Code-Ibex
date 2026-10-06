<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SuperAdminUserCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_issue_a_temporary_password_for_an_internal_user(): void
    {
        $temporaryPassword = 'ManagerTempPass123!';
        $superAdmin = User::factory()->create([
            'name' => 'Platform Admin',
            'phone' => '03001110001',
            'role' => 'super_admin',
        ]);
        $manager = User::factory()->create([
            'name' => 'Restaurant Manager',
            'phone' => '03001110002',
            'role' => 'manager',
        ]);

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.users.password.reset', $manager), [
                'password' => $temporaryPassword,
                'password_confirmation' => $temporaryPassword,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('temporary_password', function (array $temporaryPassword): bool {
            return $temporaryPassword['user_name'] === 'Restaurant Manager'
                && $temporaryPassword['user_phone'] === '03001110002'
                && $temporaryPassword['password'] === 'ManagerTempPass123!';
        });

        $manager->refresh();
        $this->assertTrue($manager->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $manager->password));
    }

    public function test_super_admin_cannot_set_a_weak_temporary_password(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110007',
            'role' => 'super_admin',
        ]);
        $manager = User::factory()->create([
            'phone' => '03001110008',
            'role' => 'manager',
        ]);
        $originalHash = $manager->password;

        $this->actingAs($superAdmin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.password.reset', $manager), [
                '_dialog_id' => 'user-dialog-'.$manager->id,
                'password' => 'weakpassword',
                'password_confirmation' => 'weakpassword',
            ])
            ->assertSessionHasErrors('password', null, 'resetPassword')
            ->assertSessionHas('_old_input._dialog_id', 'user-dialog-'.$manager->id);

        $manager->refresh();
        $this->assertSame($originalHash, $manager->password);
        $this->assertFalse($manager->must_change_password);
    }

    public function test_manager_cannot_access_super_admin_user_credentials(): void
    {
        $manager = User::factory()->create([
            'phone' => '03001110003',
            'role' => 'manager',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_user_access_lists_businesses_with_tenant_and_login_status(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110009',
            'role' => 'super_admin',
        ]);
        $withLogin = Restaurant::create([
            'name' => 'Business With Manager',
            'slug' => 'business-with-manager',
        ]);
        $withoutLogin = Restaurant::create([
            'name' => 'Business Without Manager',
            'slug' => 'business-without-manager',
        ]);
        User::factory()->create([
            'name' => 'Existing Business Manager',
            'phone' => '03001110010',
            'role' => 'manager',
            'restaurant_id' => $withLogin->id,
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->twice()
            ->andReturn(
                ['status' => 'ready', 'ready' => true, 'database' => 'tenant_with_login'],
                ['status' => 'not_configured', 'ready' => false, 'database' => null],
            );
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Business With Manager')
            ->assertSee('Business Without Manager')
            ->assertSee('Existing Business Manager')
            ->assertSee('tenant_with_login')
            ->assertSee('No internal login account')
            ->assertSee('Add manager')
            ->assertSee('Edit account')
            ->assertSee('Save account details')
            ->assertSee('At least one uppercase letter')
            ->assertSee('inset: 50% auto auto 50%')
            ->assertSee('Available when tenant is ready');
    }

    public function test_super_admin_can_add_manager_only_to_a_ready_tenant(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110011',
            'role' => 'super_admin',
        ]);
        $business = Restaurant::create([
            'name' => 'Ready Business',
            'slug' => 'ready-business',
            'db_connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturn(['status' => 'ready', 'ready' => true, 'database' => ':memory:']);
        $provisioner->shouldReceive('syncManagerAccount')
            ->once()
            ->with(
                Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)),
                Mockery::on(fn (User $candidate): bool => $candidate->phone === '03001110012'
                    && $candidate->role === 'manager'
                    && $candidate->must_change_password),
            );
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $password = 'NewManagerPass123!';
        $this->actingAs($superAdmin)
            ->post(route('admin.users.businesses.managers.store', $business), [
                'name' => 'New Business Manager',
                'phone' => '03001110012',
                'email' => 'new-manager@example.test',
                'password' => $password,
                'password_confirmation' => $password,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('temporary_password.password', $password);

        $manager = User::where('phone', '03001110012')->firstOrFail();
        $this->assertSame('manager', $manager->role);
        $this->assertSame($business->id, $manager->restaurant_id);
        $this->assertTrue($manager->must_change_password);
        $this->assertTrue(Hash::check($password, $manager->password));
    }

    public function test_manager_creation_password_errors_return_to_the_manager_modal(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110018',
            'role' => 'super_admin',
        ]);
        $business = Restaurant::create([
            'name' => 'Password Validation Business',
            'slug' => 'password-validation-business',
            'db_connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturn(['status' => 'ready', 'ready' => true, 'database' => ':memory:']);
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $this->actingAs($superAdmin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.businesses.managers.store', $business), [
                '_dialog_id' => 'manager-dialog-'.$business->id,
                'name' => 'Weak Password Manager',
                'phone' => '03001110019',
                'password' => 'weakpassword',
                'password_confirmation' => 'weakpassword',
            ])
            ->assertSessionHasErrors('password', null, 'managerCreate')
            ->assertSessionHas('_old_input._dialog_id', 'manager-dialog-'.$business->id);

        $this->assertDatabaseMissing('users', ['phone' => '03001110019']);
    }

    public function test_super_admin_can_edit_an_internal_account_from_the_modal_form_endpoint(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110016',
            'role' => 'super_admin',
        ]);
        $business = Restaurant::create([
            'name' => 'Editable Business',
            'slug' => 'editable-business',
            'db_connection' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
        ]);
        $manager = User::factory()->create([
            'name' => 'Old Manager Name',
            'phone' => '03001110017',
            'email' => 'old-manager@example.test',
            'role' => 'manager',
            'restaurant_id' => $business->id,
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->with(Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)))
            ->andReturn(['status' => 'ready', 'ready' => true, 'database' => ':memory:']);
        $provisioner->shouldReceive('syncManagerAccount')
            ->once()
            ->with(
                Mockery::on(fn (Restaurant $candidate): bool => $candidate->is($business)),
                Mockery::on(fn (User $candidate): bool => $candidate->id === $manager->id
                    && $candidate->name === 'Updated Manager Name'
                    && $candidate->phone === '03001110018'
                    && $candidate->email === 'updated-manager@example.test'),
            );
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.update', $manager), [
                'name' => 'Updated Manager Name',
                'phone' => '03001110018',
                'email' => 'updated-manager@example.test',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', 'Account details updated for Updated Manager Name.');

        $manager->refresh();
        $this->assertSame('Updated Manager Name', $manager->name);
        $this->assertSame('03001110018', $manager->phone);
        $this->assertSame('updated-manager@example.test', $manager->email);
    }

    public function test_super_admin_cannot_add_manager_when_tenant_database_is_not_ready(): void
    {
        $superAdmin = User::factory()->create([
            'phone' => '03001110013',
            'role' => 'super_admin',
        ]);
        $business = Restaurant::create([
            'name' => 'Not Ready Business',
            'slug' => 'not-ready-business',
        ]);

        $provisioner = Mockery::mock(TenantProvisioner::class);
        $provisioner->shouldReceive('inspectDatabase')
            ->once()
            ->andReturn(['status' => 'not_configured', 'ready' => false, 'database' => null]);
        $this->app->instance(TenantProvisioner::class, $provisioner);

        $this->actingAs($superAdmin)
            ->post(route('admin.users.businesses.managers.store', $business), [
                'name' => 'Blocked Manager',
                'phone' => '03001110014',
                'password' => 'BlockedManagerPass123!',
                'password_confirmation' => 'BlockedManagerPass123!',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['phone' => '03001110014']);
    }

    public function test_internal_user_with_temporary_password_is_sent_to_required_password_change_after_login(): void
    {
        $temporaryPassword = 'TemporaryPass123!';
        $manager = User::factory()->create([
            'phone' => '03001110006',
            'role' => 'manager',
            'password' => Hash::make($temporaryPassword),
        ]);
        $manager->forceFill(['must_change_password' => true])->save();

        $this->post(route('manager.login.attempt'), [
            'phone' => $manager->phone,
            'password' => $temporaryPassword,
        ])->assertRedirect(route('password.required.edit'));
    }

    public function test_user_with_temporary_password_must_set_a_new_password(): void
    {
        $temporaryPassword = 'TemporaryPass123!';
        $user = User::factory()->create([
            'phone' => '03001110004',
            'role' => 'super_admin',
            'password' => Hash::make($temporaryPassword),
        ]);
        $user->forceFill(['must_change_password' => true])->save();
        $this->assertTrue($user->fresh()->must_change_password);

        $response = $this->actingAs($user)->get(route('admin.users.index'));
        $this->assertSame(302, $response->status(), $response->content());
        $response->assertRedirect(route('password.required.edit'));

        $response = $this->actingAs($user)->get(route('password.required.edit'));
        $this->assertSame(200, $response->status(), $response->content());

        $this->actingAs($user)
            ->patch(route('password.required.update'), [
                'temporary_password' => $temporaryPassword,
                'password' => 'MyNewSecurePassword456!',
                'password_confirmation' => 'MyNewSecurePassword456!',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('MyNewSecurePassword456!', $user->password));
    }

    public function test_required_password_change_rejects_an_incorrect_temporary_password(): void
    {
        $user = User::factory()->create([
            'phone' => '03001110005',
            'role' => 'manager',
            'password' => Hash::make('TemporaryPass123!'),
        ]);
        $user->forceFill(['must_change_password' => true])->save();
        $this->assertTrue($user->fresh()->must_change_password);

        $response = $this->actingAs($user)->get(route('password.required.edit'));
        $this->assertSame(200, $response->status(), $response->content());

        $response = $this->actingAs($user)
            ->from(route('password.required.edit'))
            ->patch(route('password.required.update'), [
                'temporary_password' => 'IncorrectPass123!',
                'password' => 'MyNewSecurePassword456!',
                'password_confirmation' => 'MyNewSecurePassword456!',
            ]);

        $this->assertSame(302, $response->status(), $response->content());
        $response->assertSessionHasErrors('temporary_password');
    }
}
