<?php

namespace App\Http\Requests\Admin\MemberPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_id' => 'required|exists:memberships,id',
            'amount' => 'required|numeric|min:0',
            'type' => 'nullable|string|in:commission,profit,free',
            'months_paid' => 'required|integer|min:1',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
