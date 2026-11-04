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
    use \App\Http\Controllers\Concerns\ScopesByMembershipCreator;

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
            // May this admin rewrite the text for THIS customer / the general templates (settings page)?
            'can_edit_message' => $request->user()->can(\App\Enums\User\UserPermissionEnum::EDIT_QUICK_ADD_MESSAGE),
            'can_edit_templates' => $request->user()->can(\App\Enums\User\UserPermissionEnum::EDIT_QUICK_ADD_TEMPLATES),
        ]);
    }

    /** Why this phone cannot be added (who holds it), or null when it is free. One wording for the live check and the save. */
    public static function takenMessage(string $phone): ?string
    {
        $holder = User::query()->where('phone', $phone)->first();
        if ($holder === null) {
            return null;
        }
        $card = Membership::query()->where('user_id', $holder->id)->orderByDesc('is_active')->orderByDesc('id')->first();

        return 'This phone number is already registered to '.($holder->name ?: 'another member')
            .($card ? ' (card '.$card->membership_number.($card->isRelative() ? ', relative' : '').')' : '')
            .'. A phone can belong to one member only, so it cannot be added again.';
    }

    /** Live check while typing: asked as soon as 11 digits are in. */
    public function phoneCheck(Request $request): \Illuminate\Http\JsonResponse
    {
        $phone = (string) $request->query('phone', '');
        if (! preg_match('/^01\d{9}$/', $phone)) {
            return response()->json(['valid' => false, 'taken' => false, 'message' => null]);
        }
        $why = self::takenMessage($phone);

        // Who holds it, so the page can offer a button to their profile — only when this admin is allowed to open that member.
        $member = null;
        if ($why !== null && ($holder = User::query()->where('phone', $phone)->first())) {
            $card = Membership::query()->where('user_id', $holder->id)->orderByDesc('is_active')->orderByDesc('id')->first();
            $visible = true;
            try {
                $this->assertCanManageUser($holder);
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                $visible = false;
            }
            $member = [
                'name' => $holder->name,
                'number' => $card?->membership_number,
                'relative' => (bool) $card?->isRelative(),
                'url' => $visible && $holder->slug ? route('admin.user.membership.show', $holder->slug) : null,
            ];
        }

        return response()->json(['valid' => true, 'taken' => $why !== null, 'message' => $why, 'member' => $member]);
    }

    public function store(Request $request, QuickAddMember $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01\d{9}$/', function ($attribute, $value, $fail) {
                if ($why = self::takenMessage((string) $value)) {
                    $fail($why);
                }
            }],
            'device' => ['required', Rule::in(QuickAdd::devices())],
            'member_kind' => ['required', Rule::in(Membership::kinds())],
            'wants_card' => ['nullable', 'boolean'],
            'sales_id' => ['nullable', 'integer', 'exists:sales,id'],
            'message_override' => ['nullable', 'string', 'max:500'],
        ]);

        // The text for this one customer may be rewritten only with its own permission; without it the field is refused, never ignored silently.
        $data['message_override'] = trim((string) ($data['message_override'] ?? ''));
        if ($data['message_override'] !== '' && ! $request->user()->can(\App\Enums\User\UserPermissionEnum::EDIT_QUICK_ADD_MESSAGE)) {
            return back()->withErrors(['message_override' => 'You do not have permission to edit the message for a customer.'])->withInput();
        }

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

        // Next steps live on their own page: edit, view, back, send on WhatsApp.
        return redirect()->route('admin.user.membership.quick-add.done', $result['membership']->user->slug)
            ->with('success', 'Member added. '.$note);
    }
}
