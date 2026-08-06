<?php

use App\Models\User;

it('promotes an existing user to admin by email', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->artisan('users:promote-admin', ['email' => 'jane@example.com'])
        ->assertExitCode(0);

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('is a no-op with a clear message when the user is already an admin', function () {
    $user = User::factory()->admin()->create(['email' => 'jane@example.com']);

    $this->artisan('users:promote-admin', ['email' => 'jane@example.com'])
        ->expectsOutputToContain('already an admin')
        ->assertExitCode(0);

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('fails clearly when no user matches the given email', function () {
    $this->artisan('users:promote-admin', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user found')
        ->assertExitCode(1);
});
