<?php

namespace App\Http\Controllers\Admin\QuickAdd;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller;
use App\Models\MemberExtraPhone;
use App\Models\Membership;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\PublicMembershipUrl;
use App\Support\QuickAdd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the admin sees right after a quick add: a page with the next steps (edit, view, back, WhatsApp), the WhatsApp page
 * (message + card picture + app links + which number to send to) and the member's extra numbers, which are notes only.
 */
class QuickAddAfterController extends Controller
{
    use ProvidesCardTemplates;
    use ScopesByMembershipCreator;

    private function member(string $slug): array
    {
        $user = User::where('slug', $slug)->firstOrFail();
        $this->assertCanManageUser($user);
        $card = Membership::query()->where('user_id', $user->id)->orderByDesc('is_active')->orderByDesc('id')->firstOrFail();

        return [$user, $card];
    }

    private function summary(User $user, Membership $card): array
    {
        $sms = SmsMessage::query()->where('membership_id', $card->id)->latest('id')->first();

        return [
            'name' => $user->name,
            'phone' => $user->phone,
            'slug' => $user->slug,
            'membership_number' => $card->membership_number,
            'member_kind' => $card->member_kind,
            'device' => $card->quick_device,
            'wants_card' => (bool) $card->quick_wants_card,
            'expiration_date' => $card->expiration_date?->format('Y-m-d'),
            'card_link' => PublicMembershipUrl::forSlug($user->slug),
            'sms' => $sms ? ['body' => $sms->body, 'status' => $sms->status, 'characters' => $sms->characters, 'segments' => $sms->segments] : null,
            'links' => ['android' => QuickAdd::storeLink(QuickAdd::DEVICE_ANDROID), 'iphone' => QuickAdd::storeLink(QuickAdd::DEVICE_IPHONE)],
        ];
    }

    private function extraPhones(User $user): array
    {
        return MemberExtraPhone::query()->where('user_id', $user->id)->orderBy('id')->get()
            ->map(fn (MemberExtraPhone $p) => ['id' => $p->id, 'phone' => $p->phone, 'note' => $p->note, 'wa' => $p->whatsappDigits()])->all();
    }

    public function done(string $user): Response
    {
        [$u, $card] = $this->member($user);

        return Inertia::render('Admin/User/Member/QuickAdd/DoneView', [
            'member' => $this->summary($u, $card),
            'extra_phones' => $this->extraPhones($u),
        ]);
    }

    public function whatsapp(Request $request, string $user): Response
    {
        [$u, $card] = $this->member($user);
        $summary = $this->summary($u, $card);
        $me = $request->user();

        return Inertia::render('Admin/User/Member/QuickAdd/WhatsAppView', [
            'member' => $summary,
            'message' => QuickAdd::whatsappMessage([
                'name' => (string) $u->name,
                'number' => (string) $card->membership_number,
                'card_link' => $summary['card_link'],
                'kind' => (string) ($card->member_kind ?: 'individual'),
                'wants_card' => (bool) $card->quick_wants_card,
            ]),
            'extra_phones' => $this->extraPhones($u),
            'wa_own' => MemberExtraPhone::toWhatsappDigits((string) $u->phone),
            'card_templates' => $this->cardTemplatesByStatus(),
            'can_edit_message' => $me->can(UserPermissionEnum::EDIT_WHATSAPP_MESSAGE),
            'can_edit_templates' => $me->can(UserPermissionEnum::EDIT_WHATSAPP_TEMPLATES),
        ]);
    }

    public function storePhone(Request $request, string $user): RedirectResponse
    {
        [$u] = $this->member($user);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32', function ($attr, $value, $fail) {
                $digits = preg_replace('/\D+/', '', \App\Support\PhoneNumbers::foldDigits((string) $value)) ?? '';
                if (strlen($digits) < 8 || strlen($digits) > 15) {
                    $fail('Enter a valid phone number (8 to 15 digits).');
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = trim($data['phone']);
        // Notes may repeat across members, but the same number twice on ONE member adds nothing.
        $exists = MemberExtraPhone::query()->where('user_id', $u->id)->get()
            ->contains(fn ($p) => $p->whatsappDigits() === MemberExtraPhone::toWhatsappDigits($phone));
        if (! $exists) {
            MemberExtraPhone::create(['user_id' => $u->id, 'phone' => $phone, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
        }

        return back()->with('success', $exists ? 'That number is already saved for this member.' : 'Number saved.');
    }

    public function destroyPhone(string $user, int $phone): RedirectResponse
    {
        [$u] = $this->member($user);
        // Soft delete, and only this member's own row.
        MemberExtraPhone::query()->where('user_id', $u->id)->whereKey($phone)->firstOrFail()->delete();

        return back()->with('success', 'Number removed.');
    }
}
