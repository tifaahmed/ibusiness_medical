<?php

namespace App\Http\Controllers\Admin\QuickAdd;

use App\Actions\Membership\QuickAddMember;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Sales;
use App\Models\User;
use App\Support\QuickAdd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickAddMemberController extends Controller
{
    /** The exact text the customer would receive for these choices (same builder as the real send), plus its size. */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'device' => ['nullable', Rule::in(QuickAdd::devices())],
            'member_kind' => ['nullable', Rule::in(Membership::kinds())],
            'wants_card' => ['nullable', 'boolean'],
        ]);
        $device = $data['device'] ?? null;
        $kind = $data['member_kind'] ?? null;

        // Always answers (even with nothing chosen yet): the page shows the message from the first moment, placeholders where a choice is missing.
        $body = QuickAdd::message($kind, $device, $request->boolean('wants_card'));

        return response()->json([
            'body' => $body,
            'days' => $kind ? QuickAdd::trialDays($kind) : null,
            'link' => $device ? QuickAdd::storeLink($device) !== null : null,
            'sending' => QuickAdd::smsEnabled(),
            'complete' => $kind !== null && $device !== null,
        ] + QuickAdd::size($body));
    }

    /** The full-page form (replaces the old pop-up). A rep linked to this admin is filled in and locked. */
    public function create(Request $request): \Inertia\Response
    {
        $mine = Sales::where('admin_user_id', $request->user()->id)->first();

        return \Inertia\Inertia::render('Admin/User/Member/QuickAdd/QuickAddView', [
            'locked_sales' => $mine ? ['value' => $mine->id, 'label' => $mine->displayName()] : null,
            'sales_options' => $mine ? [] : Sales::pickerOptions(),
        ]);
    }

    public function store(Request $request, QuickAddMember $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01\d{9}$/', function ($attribute, $value, $fail) {
                // Say WHO already holds the number, so sales can tell the customer (or find the card) instead of guessing.
                $holder = User::query()->where('phone', $value)->first();
                if ($holder === null) {
                    return;
                }
                $card = Membership::query()->where('user_id', $holder->id)->orderByDesc('is_active')->orderByDesc('id')->first();
                $fail('This phone number is already registered to '.($holder->name ?: 'another member')
                    .($card ? ' (card '.$card->membership_number.($card->isRelative() ? ', relative' : '').')' : '')
                    .'. A phone can belong to one member only, so it cannot be added again.');
            }],
            'device' => ['required', Rule::in(QuickAdd::devices())],
            'member_kind' => ['required', Rule::in(Membership::kinds())],
            'wants_card' => ['nullable', 'boolean'],
            'sales_id' => ['nullable', 'integer', 'exists:sales,id'],
        ]);

        // An admin tied to a rep always files under THAT rep: whatever the browser sent is ignored.
        $mine = Sales::where('admin_user_id', $request->user()->id)->value('id');
        $data['sales_id'] = $mine ?: ($data['sales_id'] ?? null);

        $result = $action->handle($data, $request->user(), $request);
        $sms = $result['sms'];

        $note = match (true) {
            $sms === null => 'The message could not be recorded.',
            $sms->status === 'sent' => 'Message sent.',
            $sms->status === 'failed' => 'The message was NOT sent (see SMS log).',
            default => 'The message is queued (sending is off).',
        };

        return redirect()->route('admin.user.membership.edit', $result['membership']->user_id)
            ->with('success', 'Member added. '.$note);
    }
}
