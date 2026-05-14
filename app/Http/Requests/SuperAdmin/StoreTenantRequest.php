<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate enforced by EnsureSuperAdmin middleware
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:tenants,email',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'logo'    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A school with this email address already exists.',
            'logo.max'     => 'The logo must not exceed 2 MB.',
            'logo.mimes'   => 'The logo must be a JPEG, PNG, or WebP image.',
        ];
    }
}
