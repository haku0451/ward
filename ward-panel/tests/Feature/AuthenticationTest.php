<?php

use App\Models\User;

test('guests can view the login page', function () {
    $this->get(route('login'))->assertOk();
});

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('users cannot authenticate with invalid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('viewers cannot create nodes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('nodes.store'), ['name' => 'edge-1'])
        ->assertForbidden();
});
