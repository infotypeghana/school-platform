<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate enforced by EnsureSchoolAdmin middleware
    }

    public function rules(): array
    {
        return [
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'admission_number' => 'nullable|string|max:50',
            'school_class_id'  => [
                'required',
                // Scoped to current tenant — prevents assigning a student to another
                // tenant's class via a crafted POST (the global exists:school_classes,id
                // rule queries the table directly, bypassing HasTenantScope).
                Rule::exists('school_classes', 'id')->where('tenant_id', app('currentTenant')->id),
            ],
            'date_of_birth'    => 'nullable|date|before:today',
            'gender'           => 'nullable|in:male,female',
            'guardian_name'    => 'nullable|string|max:150',
            'guardian_phone'   => 'nullable|string|max:20',
            'guardian_email'   => 'nullable|email|max:100',
            'address'          => 'nullable|string|max:255',
            'admission_date'   => 'nullable|date',
            'status'           => 'required|in:active,graduated,withdrawn,suspended',
            'photo'            => 'nullable|image|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'school_class_id.required' => 'Please select a class for the student.',
            'school_class_id.exists'   => 'The selected class does not exist.',
            'date_of_birth.before'     => 'Date of birth must be in the past.',
        ];
    }
}
