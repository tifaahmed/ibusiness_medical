<?php

namespace App\Http\Controllers\Admin\QuickAdd;

use App\Actions\Membership\QuickAddMember;
use App\Http\Controllers\Controller;
use App\Models\Membership;
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
            'device' => ['required', Rule::in(QuickAdd::devices())],
            'member_kind' => ['required', Rule::in(Membership::kinds())],
            'wants_card' => ['nullable', 'boolean'],
        ]);

        $body = QuickAdd::message($data['member_kind'], $data['device'], $request->boolean('wants_card'));

        return response()->json($body === '' ? [] : ['body' => $body, 'days' => QuickAdd::trialDays($data['member_kind']), 'link' => QuickAdd::storeLink($data['device']) !== null, 'sending' => QuickAdd::smsEnabled()] + QuickAdd::size($body));
    }

    public function store(Request $request, QuickAddMember $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01\d{9}$/', Rule::unique(User::class, 'phone')->whereNull('deleted_at')],
            'device' => ['required', Rule::in(QuickAdd::devices())],
            'member_kind' => ['required', Rule::in(Membership::kinds())],
            'wants_card' => ['nullable', 'boolean'],
        ]);

        $result = $action->handle($data, $request->user(), $request);
        $sms = $result['sms'];

        $note = match (true) {
            $sms === null => 'The message could not be recorded.',
            $sms->status === 'sent' => 'Message sent.',
            $sms->status === 'failed' => 'The message was NOT sent (see SMS log).',
            default => 'The message is queued (sending is off).',
        };

        return back()->with('success', 'Member added. '.$note);
    }
}
