<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit roles');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleId = $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['sometimes', 'required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
