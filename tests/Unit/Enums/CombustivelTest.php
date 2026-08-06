<?php

use App\Enums\Combustivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

it('is a string-backed enum with exactly the six known fuel types', function () {
    expect(Combustivel::Gasolina->value)->toBe('gasolina');
    expect(Combustivel::Alcool->value)->toBe('alcool');
    expect(Combustivel::Flex->value)->toBe('flex');
    expect(Combustivel::Diesel->value)->toBe('diesel');
    expect(Combustivel::Hibrido->value)->toBe('hibrido');
    expect(Combustivel::Eletrico->value)->toBe('eletrico');
    expect(Combustivel::cases())->toHaveCount(6);
});

it('casts a raw string attribute to a Combustivel instance on an Eloquent model', function () {
    $model = new class () extends Model {
        protected $casts = [
            'combustivel' => Combustivel::class,
        ];
    };

    $model->combustivel = 'flex';

    expect($model->combustivel)->toBe(Combustivel::Flex);
    expect($model->getAttributes()['combustivel'])->toBe('flex');
});

it('validates a field against Combustivel using Rule::enum', function () {
    $validator = Validator::make(
        ['combustivel' => 'eletrico'],
        ['combustivel' => ['required', Rule::enum(Combustivel::class)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails Rule::enum validation for a value outside the six known fuel types', function () {
    $validator = Validator::make(
        ['combustivel' => 'gnv'],
        ['combustivel' => ['required', Rule::enum(Combustivel::class)]],
    );

    expect($validator->fails())->toBeTrue();
});
