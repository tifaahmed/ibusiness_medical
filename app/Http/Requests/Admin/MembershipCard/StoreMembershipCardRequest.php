<?php

namespace App\Http\Requests\Admin\MembershipCard;

use App\Enums\User\UserPermissionEnum;
use App\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMembershipCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Partner-locked admins cannot pick another partner — overwrite the
        // submitted partner_id with their own so a tampered request cannot
        // bypass the UI lock. Mixed-scope admins (also holding `manage own
        // membership card patches`) keep free choice.
        if ($this->partnerLocked()) {
            $this->merge([
                'partner_id' => Auth::user()?->partner_id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'batch_name' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'display_prefix' => ['nullable', 'string', 'max:32'],
            // Group lengths for the printed number, written the way the result
            // reads: '3-3-3', '1-3-3-2'. Decoration only — see the migration.
            'display_groups' => ['nullable', 'string', 'max:32', 'regex:/^\d+([^0-9]+\d+)*$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'start_number' => ['required', 'integer', 'min:1'],
            'partner_id' => [
                $this->partnerLocked() ? 'required' : 'nullable',
                'integer',
                'exists:partners,id',
                ...($this->partnerLocked() && Auth::user()?->partner_id !== null
                    ? [Rule::in([(int) Auth::user()->partner_id])]
                    : []),
            ],
            'card_template_id' => ['nullable', 'integer', 'exists:card_templates,id'],
            'layout_overrides' => ['nullable', 'array'],
            // qr / barcode / partner / contact are the four placeable elements
            // on the blank card artwork.
            ...collect(['qr', 'barcode', 'partner', 'contact'])
                ->flatMap(fn (string $el) => [
                    "layout_overrides.$el" => ['nullable', 'array'],
                    "layout_overrides.$el.x" => ['nullable', 'numeric'],
                    "layout_overrides.$el.y" => ['nullable', 'numeric'],
                    "layout_overrides.$el.scale" => ['nullable', 'numeric'],
                ])
                ->all(),
            'layout_overrides.contact_text' => ['nullable', 'array'],
            'layout_overrides.contact_text.facebook' => ['nullable', 'string', 'max:120'],
            'layout_overrides.contact_text.website' => ['nullable', 'string', 'max:120'],
            'layout_overrides.contact_text.phone' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $prefix = (string) ($this->input('prefix') ?? '');
            $start = (int) $this->input('start_number');
            $qty = (int) $this->input('quantity');

            $numbers = [];
            for ($i = 0; $i < $qty; $i++) {
                $numbers[] = $prefix.(string) ($start + $i);
            }

            // Check both live and soft-deleted rows so we never collide with
            // a number that's been archived but still owns the slug.
            $existing = Membership::withTrashed()
                ->whereIn('membership_number', $numbers)
                ->pluck('membership_number')
                ->all();

            if (! empty($existing)) {
                $sample = array_slice($existing, 0, 5);
                $more = count($existing) > 5 ? ' (+'.(count($existing) - 5).' more)' : '';
                $v->errors()->add(
                    'start_number',
                    'Some codes in this range are already used: '.implode(', ', $sample).$more.'. Pick a different prefix or start number.'
                );
            }
        });
    }

    /**
     * True when partner is the ONLY narrowing scope on the card area for
     * this admin — mirrors the membership-area logic.
     */
    private function partnerLocked(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::VIEW_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_MEMBERSHIP_CARD_PATCHES)) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::VIEW_OWN_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_OWN_MEMBERSHIP_CARD_PATCHES)) {
            return false;
        }

        return $user->hasPermissionTo(UserPermissionEnum::VIEW_PARTNER_MEMBERSHIP_CARD_PATCHES)
            || $user->hasPermissionTo(UserPermissionEnum::CREATE_PARTNER_MEMBERSHIP_CARD_PATCHES);
    }
}
