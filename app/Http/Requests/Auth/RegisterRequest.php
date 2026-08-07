<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Auth\Concerns\HasAuthValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use HasAuthValidationMessages;

    /**
     * Anyone may register a new account; there is no prior authentication or
     * ownership check to perform here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string'],
            'email'    => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * The fields that are safe to mass-assign onto a new `User`, i.e. the
     * ones this request itself validates. `is_admin` is deliberately never
     * listed here, even if a caller sneaks it into the payload: `rules()`
     * above never declares it, so it is not part of the validated data
     * regardless of what this method returns, and a self-registration
     * endpoint must never be able to grant admin privileges.
     *
     * @return array{name: string, email: string, password: string}
     */
    public function accountData(): array
    {
        return $this->validated();
    }
}
