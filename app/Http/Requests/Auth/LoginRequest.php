<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Anyone may attempt to log in; there is no prior authentication or
     * ownership check to perform here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * The credentials to feed to `Auth::attempt()`, kept out of the
     * controller so it doesn't need to know the request's raw field names.
     *
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return $this->only('email', 'password');
    }
}
