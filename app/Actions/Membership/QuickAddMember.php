<?php

namespace App\Actions\Membership;

use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use App\Services\Sms\SmsOutbox;
use App\Support\QuickAdd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A salesperson registers a customer on the phone: name, phone, Android/iPhone, individual/company, wants a card or not.
 *
 * Builds the member through the ordinary trial path ({@see StartTrialCard}: trial claim, number, log), then stamps it
 * for good as "quick added" by that admin, sets the trial length for its kind (7 days individuals, 30 companies — settings)
 * and queues the welcome SMS ({@see SmsOutbox}: it only leaves when the admin's sending switch is on).
 * The customer signs in to the app with the phone number, so no membership number is sent or needed.
 */
class QuickAddMember
{
    public function __construct(private readonly StartTrialCard $trial, private readonly SmsOutbox $outbox) {}

    /**
     * @param  array{name: string, phone: string, device: string, member_kind: string, wants_card?: bool}  $data
     * @return array{membership: Membership, sms: \App\Models\SmsMessage|null}
     */
    public function handle(array $data, User $admin, ?Request $request = null): array
    {
        $membership = DB::transaction(function () use ($data, $admin, $request) {
            $user = User::create([
                'name' => trim($data['name']),
                'phone' => $data['phone'],
                'password' => Hash::make(Str::random(40)),
            ]);

            $card = $this->trial->handle($user, $data['name'], $request);
            $days = QuickAdd::trialDays($data['member_kind']);

            $card->forceFill([
                'member_kind' => $data['member_kind'],
                'source' => Membership::SOURCE_QUICK_ADD,
                'expiration_date' => now()->addDays($days),
                'created_by' => $admin->id,
                'quick_added_at' => now(),
                'quick_added_by' => $admin->id,
                'quick_device' => $data['device'],
                'quick_wants_card' => (bool) ($data['wants_card'] ?? false),
            ])->save();

            MemberLog::record(
                membershipId: $card->id,
                adminId: $admin->id,
                action: MemberLog::ACTION_CREATED,
                oldValues: null,
                newValues: ['quick_add' => true, 'member_kind' => $data['member_kind'], 'device' => $data['device'], 'wants_card' => (bool) ($data['wants_card'] ?? false), 'trial_days' => $days],
                request: $request,
            );

            return $card->refresh();
        });

        $sms = null;
        try {
            $sms = $this->outbox->submit(
                (string) $membership->user->phone,
                QuickAdd::message($data['member_kind'], $data['device'], (bool) ($data['wants_card'] ?? false)),
                'quick_add',
                ['user_id' => $membership->user_id, 'membership_id' => $membership->id, 'created_by' => $admin->id],
            );
        } catch (\Throwable $e) {
            /* The member exists already; a failed message must not undo or hide that. */
            Log::error('Quick add: could not record the welcome SMS.', ['membership_id' => $membership->id, 'error' => $e->getMessage()]);
        }

        return ['membership' => $membership, 'sms' => $sms];
    }
}
