<?php

use App\Models\User;

it('defaults new users to is_admin false', function () {
    $user = User::factory()->create()->fresh();

    expect($user->is_admin)->toBeFalse()
        ->and(User::query()->whereKey($user->id)->value('is_admin'))->toBeFalse();
});

it('creates an admin user via the admin factory state', function () {
    $user = User::factory()->admin()->create();

    expect($user->is_admin)->toBeTrue()
        ->and(User::query()->whereKey($user->id)->value('is_admin'))->toBeTrue();
});

it('hides is_admin from the default array and json serialization', function () {
    $user = User::factory()->admin()->create();

    expect($user->toArray())->not->toHaveKey('is_admin')
        ->and($user->toJson())->not->toContain('is_admin')
        ->and($user->is_admin)->toBeTrue();
});
