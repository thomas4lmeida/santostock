<?php

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can list teams', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/equipes')->assertOk();
});

test('admin can view create form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/equipes/create')->assertOk();
});

test('admin can create a team', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $users = User::factory(2)->create();

    $response = $this->actingAs($admin)->post('/equipes', [
        'name' => 'Equipe Alpha',
        'description' => 'Time responsável pela operação',
        'user_ids' => $users->pluck('id')->toArray(),
    ]);

    $response->assertRedirect('/equipes');
    $this->assertDatabaseHas('teams', ['name' => 'Equipe Alpha']);

    $team = Team::where('name', 'Equipe Alpha')->first();
    expect($team->users()->count())->toBe(2);
});

test('admin can view a team', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $team = Team::factory()->create();

    $this->actingAs($admin)->get("/equipes/{$team->id}")->assertOk();
});

test('admin can view edit form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $team = Team::factory()->create();

    $this->actingAs($admin)->get("/equipes/{$team->id}/edit")->assertOk();
});

test('admin can update a team', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $team = Team::factory()->create(['name' => 'Equipe Antiga']);
    $newUsers = User::factory(2)->create();

    $response = $this->actingAs($admin)->put("/equipes/{$team->id}", [
        'name' => 'Equipe Nova',
        'description' => 'Descrição atualizada',
        'user_ids' => $newUsers->pluck('id')->toArray(),
    ]);

    $response->assertRedirect('/equipes');
    $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Equipe Nova']);
    expect($team->fresh()->users()->count())->toBe(2);
});

test('admin can delete a team', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $team = Team::factory()->create();
    $users = User::factory(2)->create();
    $team->users()->sync($users->pluck('id')->toArray());

    $this->actingAs($admin)->delete("/equipes/{$team->id}")->assertRedirect('/equipes');

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    $this->assertDatabaseMissing('team_user', ['team_id' => $team->id]);
});

test('name is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/equipes', [])->assertSessionHasErrors('name');
});

test('name must be unique', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Team::factory()->create(['name' => 'Equipe Duplicada']);

    $this->actingAs($admin)->post('/equipes', [
        'name' => 'Equipe Duplicada',
    ])->assertSessionHasErrors('name');
});
