<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:100',
            'status' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $this->route('id'),
            'password' => 'sometimes|nullable|min:6',
        ];
    }

    public function messages()
    {
        return [
            'name.string' => 'El nombre debe ser un texto.',
            'name.max' => 'El nombre no debe exceder los 100 caracteres.',

            'status.string' => 'El estado debe ser un texto.',
            'status.max' => 'El estado no debe exceder los 100 caracteres.',

            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Este correo electrónico ya está en uso por otro usuario.',

            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }
}
