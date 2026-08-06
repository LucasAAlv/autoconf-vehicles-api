<?php

namespace App\Http\Requests\VehicleImage;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleImageRequest extends FormRequest
{
    /**
     * Always authorizes: ownership/admin authorization is `VehiclePolicy`'s
     * job, applied in the controller via `$this->authorize('manageImages', ...)`
     * — the same split already used by `StoreVehicleRequest`. This request
     * is validation-only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `mimes` is spelled out explicitly rather than relying solely on the
     * `image` rule: `image` alone also accepts `svg`, which can carry
     * embedded scripts and is deliberately excluded here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'É necessário enviar ao menos um arquivo.',
            'files.array' => 'O campo files deve ser uma lista de arquivos.',
            'files.min' => 'É necessário enviar ao menos um arquivo.',

            'files.*.required' => 'Um dos arquivos enviados está vazio.',
            'files.*.image' => 'Cada arquivo enviado deve ser uma imagem.',
            'files.*.mimes' => 'Cada imagem deve ser de um dos seguintes tipos: jpeg, jpg, png, gif ou webp.',
            'files.*.max' => 'Cada imagem deve ter no máximo 2MB.',
        ];
    }
}
