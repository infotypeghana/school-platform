<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'address'     => 'nullable|string|max:500',
            'status'      => 'required|in:active,trial,grace,locked,suspended',
            'logo'        => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'   => 'Invalid status. Must be one of: active, trial, grace, locked, suspended.',
            'logo.max'    => 'The logo must not exceed 2 MB.',
            'logo.mimes'  => 'The logo must be a JPEG, PNG, or WebP image.',
        ];
    }
}
