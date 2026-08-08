<?php

namespace App\Http\Requests\Admin\MembershipUsage;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_id'      => 'required|exists:memberships,id',
            'facility_id'        => 'required|exists:facilities,id',
            'facility_branch_id' => 'nullable|exists:facility_branches,id',
            'facility_type_id'   => 'required|exists:facility_types,id',
            'amount'             => 'required|numeric|min:0',
            'description'        => 'nullable|string|max:1000',
            'gallery'            => 'nullable|array',
            'gallery.*'          => 'image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
        ];
    }
}
