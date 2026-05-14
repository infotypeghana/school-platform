<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:100',
            'level'            => 'nullable|string|max:50',
            'section'          => 'nullable|string|max:50',
            'class_teacher_id' => [
                'nullable',
                Rule::exists('teachers', 'id')->where('tenant_id', app('currentTenant')->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'class_teacher_id.exists' => 'The selected teacher does not exist.',
        ];
    }
}
