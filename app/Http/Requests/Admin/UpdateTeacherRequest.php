<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'staff_id'       => 'nullable|string|max:50',
            'email'          => [
                'nullable', 'email', 'max:150',
                // Unique per tenant, ignoring the teacher being updated.
                Rule::unique('teachers', 'email')
                    ->where('tenant_id', app('currentTenant')->id)
                    ->ignore($this->route('teacher')),
            ],
            'phone'          => 'nullable|string|max:20',
            'gender'         => 'nullable|in:male,female',
            'qualification'  => 'nullable|string|max:150',
            'specialization' => 'nullable|string|max:150',
            'joined_date'    => 'nullable|date',
            'status'         => 'required|in:active,inactive',
            'photo'          => 'nullable|image|max:2048',
            'remove_photo'   => 'nullable|boolean',
        ];
    }
}
