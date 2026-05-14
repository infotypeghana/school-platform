<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:150'],
            'address'       => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'primary_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo'          => ['nullable', 'image', 'max:2048'],
            'remove_logo'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Primary colour must be a valid hex colour (e.g. #3b82f6).',
            'logo.image'          => 'Logo must be an image file (JPG, PNG, GIF, etc.).',
            'logo.max'            => 'Logo may not be larger than 2 MB.',
        ];
    }
}
