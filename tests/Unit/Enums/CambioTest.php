<?php

use App\Enums\Cambio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

it('is a string-backed enum with exactly the manual and automatico cases', function () {
    expect(Cambio::Manual->value)->toBe('manual');
    expect(Cambio::Automatico->value)->toBe('automatico');
    expect(Cambio::cases())->toHaveCount(2);
});

it('casts a raw string attribute to a Cambio instance on an Eloquent model', function () {
    $model = new class () extends Model {
        protected $casts = [
            'cambio' => Cambio::class,
        ];
    };

    $model->cambio = 'manual';

    expect($model->cambio)->toBe(Cambio::Manual);
    expect($model->getAttributes()['cambio'])->toBe('manual');
});

it('validates a field against Cambio using Rule::enum', function () {
    $validator = Validator::make(
        ['cambio' => 'automatico'],
        ['cambio' => ['required', Rule::enum(Cambio::class)]],
    );

    expect($validator->passes())->toBeTrue();
});

it('fails Rule::enum validation for a value outside manual and automatico', function () {
    $validator = Validator::make(
        ['cambio' => 'cvt'],
        ['cambio' => ['required', Rule::enum(Cambio::class)]],
    );

    expect($validator->fails())->toBeTrue();
});
