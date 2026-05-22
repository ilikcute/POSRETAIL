<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userId = $this->route('user');
        $targetId = is_object($userId) ? $userId->id : (int) $userId;

        return $this->user()->can('edit users') || $this->user()->id === $targetId;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'required', 'string', 'exists:roles,name'],
            'station_id' => ['nullable', 'integer', 'exists:stations,id'],
            'is_active' => ['boolean'],
        ];
    }
}
