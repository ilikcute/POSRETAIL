<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteCashPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'station_id' => 'required|exists:stations,id',
            'pull_amount' => 'required|numeric|min:1',
            'supervisor_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ];
    }
}
