<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Contact\ContactStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Membership;
use App\Models\NationalIdChangeRequest;
use App\Models\TrialClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An admin's answer to a member's national-ID change request (it arrives in the contact inbox).
 * Approving is the ONLY thing that changes the ID on a card.
 */
class NationalIdChangeController extends Controller
{
    public function approve(Request $request, NationalIdChangeRequest $change): RedirectResponse
    {
        abort_unless($change->isPending(), 409, 'This request was already answered.');

        $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);

        /* The number must still be free: someone else may have been issued it since the request was filed. */
        $taken = TrialClaim::query()->where('national_id', $change->new_national_id)->where('user_id', '!=', $change->user_id)->exists()
            || Membership::withTrashed()->where('national_id', $change->new_national_id)->where('user_id', '!=', $change->user_id)->exists();

        if ($taken) {
            return back()->with('error', 'This national ID now belongs to another card, so the change cannot be approved.');
        }

        DB::transaction(function () use ($change, $request): void {
            $membership = Membership::withTrashed()->find($change->membership_id)
                ?? Membership::withTrashed()->where('user_id', $change->user_id)->latest('id')->first();

            $membership?->forceFill(['national_id' => $change->new_national_id])->save();

            /* Keep "one trial per national ID" pointing at the ID the card really holds now. */
            if ($change->current_national_id) {
                TrialClaim::query()->where('user_id', $change->user_id)->where('national_id', $change->current_national_id)
                    ->update(['national_id' => $change->new_national_id]);
            }

            $change->update([
                'status' => NationalIdChangeRequest::APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $request->input('review_note'),
            ]);

            $this->closeInboxMessage($change);
        });

        return back()->with('success', 'National ID changed.');
    }

    public function reject(Request $request, NationalIdChangeRequest $change): RedirectResponse
    {
        abort_unless($change->isPending(), 409, 'This request was already answered.');

        $request->validate(['review_note' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($change, $request): void {
            $change->update([
                'status' => NationalIdChangeRequest::REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $request->input('review_note'),
            ]);

            $this->closeInboxMessage($change);
        });

        return back()->with('success', 'Request rejected.');
    }

    /** The ID-card photo ("front" or "back"), straight from the private disk — admins only. */
    public function image(NationalIdChangeRequest $change, string $side): StreamedResponse
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = $side === 'front' ? $change->front_path : $change->back_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function closeInboxMessage(NationalIdChangeRequest $change): void
    {
        if ($change->contact_message_id) {
            ContactMessage::query()->whereKey($change->contact_message_id)->update(['status' => ContactStatusEnum::RESOLVED->value]);
        }
    }
}
