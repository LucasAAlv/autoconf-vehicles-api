<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function makeVehicle(User $owner): Vehicle
{
    $vehicle = new Vehicle([
        'placa'       => 'ABC1D23',
        'chassi'      => '9BWZZZ377VT004251',
        'marca'       => 'Volkswagen',
        'modelo'      => 'Gol',
        'versao'      => '1.0 MPI',
        'valor_venda' => '45000.99',
        'cor'         => 'Prata',
        'km'          => 12000,
        'cambio'      => Cambio::Manual,
        'combustivel' => Combustivel::Flex,
    ]);
    $vehicle->user_id = $owner->id;

    return $vehicle;
}

it('adds created_by and updated_by as nullable foreign key columns to vehicles', function () {
    expect(Schema::hasColumns('vehicles', ['created_by', 'updated_by']))->toBeTrue();

    $columns = collect(DB::select("
        select column_name, is_nullable
        from information_schema.columns
        where table_name = 'vehicles'
        and column_name in ('created_by', 'updated_by')
    "))->keyBy('column_name');

    expect($columns->get('created_by')->is_nullable)->toBe('YES')
        ->and($columns->get('updated_by')->is_nullable)->toBe('YES');
});

it('nullifies created_by and updated_by when the referenced user is deleted', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();

    $vehicle = makeVehicle($owner);
    $vehicle->save();

    $vehicle->created_by = $editor->id;
    $vehicle->updated_by = $editor->id;
    $vehicle->saveQuietly();

    $editor->delete();

    $vehicle->refresh();

    expect($vehicle->created_by)->toBeNull()
        ->and($vehicle->updated_by)->toBeNull()
        ->and(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeTrue();
});

it('does not mass assign created_by and updated_by', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'       => 'ABC1D23',
        'chassi'      => '9BWZZZ377VT004251',
        'marca'       => 'Volkswagen',
        'modelo'      => 'Gol',
        'versao'      => '1.0 MPI',
        'valor_venda' => '45000.99',
        'cor'         => 'Prata',
        'km'          => 12000,
        'cambio'      => Cambio::Manual,
        'combustivel' => Combustivel::Flex,
        'created_by'  => $attacker->id,
        'updated_by'  => $attacker->id,
    ]);

    expect($vehicle->created_by)->toBeNull()
        ->and($vehicle->updated_by)->toBeNull()
        ->and((new Vehicle())->getFillable())->not->toContain('created_by')
        ->and((new Vehicle())->getFillable())->not->toContain('updated_by');
});

it('sets created_by and updated_by to the authenticated user on creation', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();

    $this->actingAs($author);

    $vehicle = makeVehicle($owner);
    $vehicle->save();

    expect($vehicle->created_by)->toBe($author->id)
        ->and($vehicle->updated_by)->toBe($author->id);
});

it('only changes updated_by, leaving created_by untouched, on update', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($author);

    $vehicle = makeVehicle($owner);
    $vehicle->save();

    $this->actingAs($editor);

    $vehicle->km = 15000;
    $vehicle->save();

    expect($vehicle->created_by)->toBe($author->id)
        ->and($vehicle->updated_by)->toBe($editor->id);
});

it('exposes creator and updater relations resolving to the right users', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($author);

    $vehicle = makeVehicle($owner);
    $vehicle->save();

    $this->actingAs($editor);

    $vehicle->km = 20000;
    $vehicle->save();

    expect($vehicle->creator->is($author))->toBeTrue()
        ->and($vehicle->updater->is($editor))->toBeTrue();
});
