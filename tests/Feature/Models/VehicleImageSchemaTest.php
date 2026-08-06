<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function makeVehicleForImages(User $owner): Vehicle
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
    $vehicle->save();

    return $vehicle;
}

it('creates the vehicle_images table with the expected columns', function () {
    foreach ([
        'id',
        'vehicle_id',
        'path',
        'is_cover',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('vehicle_images', $column))->toBeTrue("Expected column [{$column}] to exist.");
    }
});

it('stores is_cover as a boolean column', function () {
    $column = collect(DB::select("
        select data_type
        from information_schema.columns
        where table_name = 'vehicle_images' and column_name = 'is_cover'
    "))->first();

    expect($column->data_type)->toBe('boolean');
});

it('has a foreign key from vehicle_id to vehicles that cascades on delete', function () {
    $foreignKeys = collect(Schema::getForeignKeys('vehicle_images'));

    $vehicleForeignKey = $foreignKeys->first(fn ($fk) => in_array('vehicle_id', $fk['columns'], true));

    expect($vehicleForeignKey)->not->toBeNull()
        ->and($vehicleForeignKey['foreign_table'])->toBe('vehicles')
        ->and($vehicleForeignKey['on_delete'])->toBe('cascade');
});

it('rejects a second cover image for the same vehicle at the database level', function () {
    $user = User::factory()->create();
    $vehicle = makeVehicleForImages($user);

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/first.jpg',
        'is_cover'   => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/second.jpg',
        'is_cover'   => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

it('allows multiple non-cover images for the same vehicle', function () {
    $user = User::factory()->create();
    $vehicle = makeVehicleForImages($user);

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/first.jpg',
        'is_cover'   => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/second.jpg',
        'is_cover'   => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(VehicleImage::where('vehicle_id', $vehicle->id)->count())->toBe(2);
});

it('allows a second cover image once it belongs to a different vehicle', function () {
    $user = User::factory()->create();
    $firstVehicle = makeVehicleForImages($user);

    $secondVehicle = new Vehicle([
        'placa'       => 'XYZ9A87',
        'chassi'      => '1HGCM82633A004352',
        'marca'       => 'Fiat',
        'modelo'      => 'Argo',
        'versao'      => '1.3',
        'valor_venda' => '60000.00',
        'cor'         => 'Branco',
        'km'          => 500,
        'cambio'      => Cambio::Automatico,
        'combustivel' => Combustivel::Gasolina,
    ]);
    $secondVehicle->user_id = $user->id;
    $secondVehicle->save();

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $firstVehicle->id,
        'path'       => 'vehicles/1/cover.jpg',
        'is_cover'   => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vehicle_images')->insert([
        'vehicle_id' => $secondVehicle->id,
        'path'       => 'vehicles/2/cover.jpg',
        'is_cover'   => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(VehicleImage::where('is_cover', true)->count())->toBe(2);
});

it('deletes vehicle images when the owning vehicle is deleted', function () {
    $user = User::factory()->create();
    $vehicle = makeVehicleForImages($user);

    VehicleImage::create([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/cover.jpg',
        'is_cover'   => true,
    ]);

    $vehicle->delete();

    expect(VehicleImage::where('vehicle_id', $vehicle->id)->count())->toBe(0);
});

it('exposes the images relation on the vehicle model', function () {
    $user = User::factory()->create();
    $vehicle = makeVehicleForImages($user);

    VehicleImage::create([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/cover.jpg',
        'is_cover'   => true,
    ]);

    expect($vehicle->images)->toHaveCount(1)
        ->and($vehicle->images->first())->toBeInstanceOf(VehicleImage::class);
});

it('belongs to a vehicle and casts is_cover to boolean', function () {
    $user = User::factory()->create();
    $vehicle = makeVehicleForImages($user);

    $image = VehicleImage::create([
        'vehicle_id' => $vehicle->id,
        'path'       => 'vehicles/1/cover.jpg',
        'is_cover'   => 1,
    ]);

    expect($image->is_cover)->toBeBool()
        ->and($image->is_cover)->toBeTrue()
        ->and($image->vehicle)->toBeInstanceOf(Vehicle::class)
        ->and($image->vehicle->id)->toBe($vehicle->id);
});
