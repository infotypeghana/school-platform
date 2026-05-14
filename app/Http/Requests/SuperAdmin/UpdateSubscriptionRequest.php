<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'        => ['required', Rule::in([
                Subscription::STATUS_TRIAL,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_GRACE,
                Subscription::STATUS_LOCKED,
                Subscription::STATUS_SUSPENDED,
            ])],
            'plan_id'       => ['nullable', 'string', 'max:50'],
            'amount'        => ['required', 'numeric', 'min:0'],
            'end_date'      => ['required', 'date'],
            'grace_ends_at' => ['nullable', 'date'],
            'is_trial'      => ['nullable', 'boolean'],
        ];
    }
}
