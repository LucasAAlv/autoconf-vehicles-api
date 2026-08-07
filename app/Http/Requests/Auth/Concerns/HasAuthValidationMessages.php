<?php

namespace App\Http\Requests\Auth\Concerns;

/**
 * Shared between `RegisterRequest` and `LoginRequest`: both validate an
 * `email`/`password` pair, so the Portuguese messages that surface through
 * the 422 `errors` member are identical for either request. Mirrors
 * `HasVehicleValidationMessages`.
 */
trait HasAuthValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O campo nome é obrigatório.',
            'name.string' => 'O campo nome deve ser um texto.',

            'email.required' => 'O campo e-mail é obrigatório.',
            'email.string' => 'O campo e-mail deve ser um texto.',
            'email.email' => 'O campo e-mail deve ser um endereço de e-mail válido.',
            'email.unique' => 'Já existe uma conta cadastrada com esse e-mail.',

            'password.required' => 'O campo senha é obrigatório.',
            'password.string' => 'O campo senha deve ser um texto.',
            'password.confirmed' => 'A confirmação de senha não corresponde.',
            // `Password::defaults()` reports a length failure under the
            // `password.min` key regardless of the underlying rule object.
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ];
    }
}
