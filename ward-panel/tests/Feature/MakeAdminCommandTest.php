<?php

use App\Enums\UserRole;
use App\Models\User;

test('it creates an administrator from options', function () {
    $this->artisan('make:admin', [
        '--name' => 'Ward Admin',
        '--email' => 'ops@ward.test',
        '--password' => 'password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ops@ward.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ward Admin')
        ->and($user->isAdmin())->toBeTrue();
});

test('it rejects a duplicate administrator email', function () {
    User::factory()->admin()->create([
        'email' => 'ops@ward.test',
    ]);

    $this->artisan('make:admin', [
        '--name' => 'Another',
        '--email' => 'ops@ward.test',
        '--password' => 'password',
    ])->assertFailed();

    expect(User::query()->where('email', 'ops@ward.test')->count())->toBe(1);
});

test('it promotes an existing viewer to administrator', function () {
    $user = User::factory()->create([
        'email' => 'viewer@ward.test',
        'role' => UserRole::Viewer,
    ]);

    $this->artisan('make:admin', [
        '--name' => 'Promoted Admin',
        '--email' => 'viewer@ward.test',
        '--password' => 'password',
    ])->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue()
        ->and($user->fresh()->name)->toBe('Promoted Admin');
});
