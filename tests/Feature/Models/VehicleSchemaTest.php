<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the vehicles table with the expected columns', function () {
    foreach ([
        'id',
        'placa',
        'chassi',
        'marca',
        'modelo',
        'versao',
        'valor_venda',
        'cor',
        'km',
        'cambio',
        'combustivel',
        'user_id',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('vehicles', $column))->toBeTrue("Expected column [{$column}] to exist.");
    }
});

it('creates created_by and updated_by as nullable columns', function () {
    foreach (['created_by', 'updated_by'] as $column) {
        expect(Schema::hasColumn('vehicles', $column))->toBeTrue("Expected column [{$column}] to exist.");
    }
});

it('has foreign keys from created_by and updated_by to users that set null on delete', function () {
    $foreignKeys = collect(Schema::getForeignKeys('vehicles'));

    foreach (['created_by', 'updated_by'] as $column) {
        $foreignKey = $foreignKeys->first(fn ($fk) => in_array($column, $fk['columns'], true));

        expect($foreignKey)->not->toBeNull()
            ->and($foreignKey['foreign_table'])->toBe('users')
            ->and($foreignKey['on_delete'])->toBe('set null');
    }
});

it('stores valor_venda as a decimal(15,2) column', function () {
    $column = collect(DB::select("
        select numeric_precision, numeric_scale
        from information_schema.columns
        where table_name = 'vehicles' and column_name = 'valor_venda'
    "))->first();

    expect($column->numeric_precision)->toBe(15)
        ->and($column->numeric_scale)->toBe(2);
});

it('stores km as an unsigned-checked integer column', function () {
    $column = collect(DB::select("
        select data_type
        from information_schema.columns
        where table_name = 'vehicles' and column_name = 'km'
    "))->first();

    expect($column->data_type)->toBe('integer');
});

it('rejects a negative km at the database level', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Volkswagen',
        'modelo'       => 'Gol',
        'versao'       => '1.0 MPI',
        'valor_venda'  => '45000.99',
        'cor'          => 'Prata',
        'km'           => -1,
        'cambio'       => Cambio::Manual,
        'combustivel'  => Combustivel::Flex,
    ]);
    $vehicle->user_id = $user->id;
    $vehicle->save();
})->throws(QueryException::class);

it('indexes the filterable and sortable columns', function () {
    $indexedColumns = collect(Schema::getIndexes('vehicles'))
        ->pluck('columns')
        ->flatten()
        ->unique()
        ->all();

    foreach (['placa', 'chassi', 'marca', 'modelo', 'km', 'valor_venda'] as $column) {
        expect($indexedColumns)->toContain($column);
    }
});

it('has a foreign key from user_id to users that cascades on delete', function () {
    $foreignKeys = collect(Schema::getForeignKeys('vehicles'));

    $userForeignKey = $foreignKeys->first(fn ($fk) => in_array('user_id', $fk['columns'], true));

    expect($userForeignKey)->not->toBeNull()
        ->and($userForeignKey['foreign_table'])->toBe('users')
        ->and($userForeignKey['on_delete'])->toBe('cascade');
});
