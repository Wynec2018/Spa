<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'contacto' => ['required', 'string', 'min:1', 'max:160'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Selecciona una sucursal.',
            'branch_id.uuid' => 'La sucursal seleccionada no es válida.',
            'contacto.required' => 'Escribe tu contacto.',
            'contacto.string' => 'El contacto debe ser texto.',
            'contacto.min' => 'El contacto no puede estar vacío.',
            'contacto.max' => 'El contacto no debe superar 160 caracteres.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.max' => 'La contraseña no debe superar 200 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => is_string($this->input('branch_id')) ? trim($this->input('branch_id')) : $this->input('branch_id'),
            'contacto' => is_string($this->input('contacto')) ? trim($this->input('contacto')) : $this->input('contacto'),
        ]);
    }
}
