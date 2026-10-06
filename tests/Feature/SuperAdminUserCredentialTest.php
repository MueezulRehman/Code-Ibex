<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminUserCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_issue_a_temporary_password_for_an_internal_user(): void
    {
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
            ->post(route('admin.users.password.reset', $manager));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('temporary_password', function (array $temporaryPassword): bool {
            return $temporaryPassword['user_name'] === 'Restaurant Manager'
                && $temporaryPassword['user_phone'] === '03001110002'
                && strlen($temporaryPassword['password']) === 32;
        });

        $manager->refresh();
        $this->assertTrue($manager->must_change_password);
        $this->assertTrue(Hash::check(session('temporary_password.password'), $manager->password));
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
