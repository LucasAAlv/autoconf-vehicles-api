<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a valid, persisted user with a hashed password', function () {
    $user = User::factory()->create([
        'password' => 'plain-text-password',
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->exists)->toBeTrue()
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and($user->name)->not->toBeEmpty()
        ->and($user->email)->not->toBeEmpty()
        ->and($user->password)->not->toBe('plain-text-password')
        ->and(Hash::check('plain-text-password', $user->password))->toBeTrue();
});
