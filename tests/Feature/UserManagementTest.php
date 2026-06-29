<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_users_crud(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $worker = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@email.com',
            'role' => UserRole::Trabajador,
        ]);

        $this->actingAs($admin);

        // 1. Index / List Users
        $this->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Usuarios/Index')
                ->has('users', 2)
                ->where('users.1.name', 'Original Name')
            );

        // 2. Create User
        $this->post('/usuarios', [
            'name' => 'New Worker',
            'email' => 'newworker@email.com',
            'password' => 'secret123',
            'role' => 'trabajador',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'New Worker',
            'email' => 'newworker@email.com',
            'role' => 'trabajador',
        ]);

        // 3. Edit User
        $this->put("/usuarios/{$worker->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@email.com',
            'role' => 'admin', // Promote to admin
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', [
            'id' => $worker->id,
            'name' => 'Updated Name',
            'email' => 'updated@email.com',
            'role' => 'admin',
        ]);

        // 4. Change Password
        $this->put("/usuarios/{$worker->id}/password", [
            'password' => 'newpassword123',
        ])->assertRedirect(route('usuarios.index'));

        // 5. Delete User (Try self delete first - should fail)
        $this->delete("/usuarios/{$admin->id}")
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        // Delete worker - should succeed
        $this->delete("/usuarios/{$worker->id}")
            ->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseMissing('users', ['id' => $worker->id]);
    }

    public function test_worker_is_blocked_from_user_management(): void
    {
        $trabajador = User::factory()->create([
            'role' => UserRole::Trabajador,
        ]);

        $otherUser = User::factory()->create();

        $this->actingAs($trabajador);

        // Blocked from index (redirects to dashboard)
        $this->get('/usuarios')
            ->assertRedirect(route('dashboard'));

        // Blocked from actions (returns 403)
        $this->postJson('/usuarios', [
            'name' => 'Attempt',
            'email' => 'attempt@email.com',
            'password' => 'secret123',
            'role' => 'trabajador',
        ])->assertStatus(403);

        $this->putJson("/usuarios/{$otherUser->id}", [
            'name' => 'Attempt Update',
            'email' => 'attempt@email.com',
            'role' => 'trabajador',
        ])->assertStatus(403);

        $this->putJson("/usuarios/{$otherUser->id}/password", [
            'password' => 'attemptpassword123',
        ])->assertStatus(403);

        $this->deleteJson("/usuarios/{$otherUser->id}")
            ->assertStatus(403);
    }
}
