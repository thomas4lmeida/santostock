<?php

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can list units', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/unidades')->assertOk();
});

test('admin can view create form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/unidades/create')->assertOk();
});

test('admin can create a unit', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $response = $this->actingAs($admin)->post('/unidades', [
        'name' => 'Quilograma',
        'abbreviation' => 'kg',
    ]);

    $response->assertRedirect('/unidades');
    $this->assertDatabaseHas('units', ['name' => 'Quilograma', 'abbreviation' => 'kg']);
});

test('admin can view a unit', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->get("/unidades/{$unit->id}")->assertOk();
});

test('admin can view edit form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->get("/unidades/{$unit->id}/edit")->assertOk();
});

test('admin can update a unit', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create(['name' => 'Metro', 'abbreviation' => 'm']);

    $response = $this->actingAs($admin)->put("/unidades/{$unit->id}", [
        'name' => 'Metro Linear',
        'abbreviation' => 'ml',
    ]);

    $response->assertRedirect('/unidades');
    $this->assertDatabaseHas('units', ['id' => $unit->id, 'name' => 'Metro Linear', 'abbreviation' => 'ml']);
});

test('admin can delete a unit', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->delete("/unidades/{$unit->id}")->assertRedirect('/unidades');

    $this->assertDatabaseMissing('units', ['id' => $unit->id]);
});

test('name is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/unidades', ['abbreviation' => 'un'])->assertSessionHasErrors('name');
});

test('abbreviation is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/unidades', ['name' => 'Unidade'])->assertSessionHasErrors('abbreviation');
});

test('name must be unique', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Unit::factory()->create(['name' => 'Unidade', 'abbreviation' => 'un']);

    $this->actingAs($admin)->post('/unidades', [
        'name' => 'Unidade',
        'abbreviation' => 'un',
    ])->assertSessionHasErrors('name');
});

test('name unique rule ignores current unit on update', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create(['name' => 'Unidade', 'abbreviation' => 'un']);

    $this->actingAs($admin)->put("/unidades/{$unit->id}", [
        'name' => 'Unidade',
        'abbreviation' => 'und',
    ])->assertRedirect('/unidades');

    $this->assertDatabaseHas('units', ['id' => $unit->id, 'abbreviation' => 'und']);
});
