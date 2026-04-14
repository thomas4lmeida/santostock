<?php

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
});

test('creating a team with user_ids attaches exactly those users', function () {
    $users = User::factory(3)->create();

    $this->actingAs($this->admin)->post('/equipes', [
        'name' => 'Equipe Sync',
        'user_ids' => [$users[0]->id, $users[1]->id],
    ]);

    $team = Team::where('name', 'Equipe Sync')->first();
    expect($team->users()->pluck('users.id')->sort()->values()->toArray())
        ->toBe([$users[0]->id, $users[1]->id]);
});

test('updating with new user_ids syncs (removes absent, adds new)', function () {
    $users = User::factory(3)->create();
    $team = Team::factory()->create();
    $team->users()->sync([$users[0]->id, $users[1]->id]);

    $this->actingAs($this->admin)->put("/equipes/{$team->id}", [
        'name' => $team->name,
        'user_ids' => [$users[1]->id, $users[2]->id],
    ]);

    expect($team->fresh()->users()->pluck('users.id')->sort()->values()->toArray())
        ->toBe([$users[1]->id, $users[2]->id]);
});

test('updating with empty user_ids detaches all users', function () {
    $users = User::factory(2)->create();
    $team = Team::factory()->create();
    $team->users()->sync($users->pluck('id')->toArray());

    $this->actingAs($this->admin)->put("/equipes/{$team->id}", [
        'name' => $team->name,
        'user_ids' => [],
    ]);

    expect($team->fresh()->users()->count())->toBe(0);
});

test('deleting a user removes them from team_user pivot', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->users()->sync([$user->id]);

    $user->delete();

    $this->assertDatabaseMissing('team_user', ['user_id' => $user->id]);
});

test('deleting a team cascades pivot rows', function () {
    $users = User::factory(2)->create();
    $team = Team::factory()->create();
    $team->users()->sync($users->pluck('id')->toArray());

    $team->delete();

    $this->assertDatabaseMissing('team_user', ['team_id' => $team->id]);
});
