<?php

namespace Tests\Feature\Api;

use App\Enums\Contact\ContactLogActionEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Enquiries forwarded from the Deilar storefront's public forms.
 *
 * The endpoint is key-gated because it writes AND because the caller speaks
 * for somebody else: the visitor's own IP and user agent arrive in the body,
 * since `$request->ip()` here is the storefront's server. Those two facts are
 * what most of these tests are about.
 */
class PartnerContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'partner-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.partner_api.key' => self::KEY]);
        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendEnquiry(array $payload = [], ?string $key = self::KEY)
    {
        return $this->withHeaders($key === null ? [] : ['X-Api-Key' => $key])
            ->postJson('/api/v1/partner/contact-messages', [
                'phone' => '01000000000',
                'message' => 'Please call me about the family card.',
                ...$payload,
            ]);
    }

    public function test_an_enquiry_is_stored_with_an_opening_log_entry(): void
    {
        $this->sendEnquiry(['name' => 'Mona'])->assertCreated();

        $enquiry = ContactMessage::sole();

        $this->assertSame('01000000000', $enquiry->phone);
        $this->assertSame('Mona', $enquiry->name);
        $this->assertSame(ContactStatusEnum::NEW, $enquiry->status);
        $this->assertSame(ContactSourceEnum::CONTACT_FORM, $enquiry->source);

        $log = $enquiry->logs()->sole();

        $this->assertSame(ContactLogActionEnum::RECEIVED, $log->action);
        $this->assertSame(ContactStatusEnum::NEW->value, $log->new_value);
        $this->assertNull($log->admin_id, 'A public form has no admin behind it.');
    }

    public function test_the_visitor_ip_is_taken_from_the_body_not_the_caller(): void
    {
        $this->sendEnquiry([
            'ip_address' => '196.221.0.42',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
            'locale' => 'ar',
            'referrer' => 'https://deilar.test/ar/contact',
        ])->assertCreated();

        $enquiry = ContactMessage::sole();

        /* The storefront's server address must never be what gets recorded —
           it is the same for every visitor and tells us nothing. */
        $this->assertSame('196.221.0.42', $enquiry->ip_address);
        $this->assertNotSame('127.0.0.1', $enquiry->ip_address);
        $this->assertSame('Mozilla/5.0 (iPhone)', $enquiry->user_agent);
        $this->assertSame('ar', $enquiry->locale);
        $this->assertSame('https://deilar.test/ar/contact', $enquiry->referrer);
    }

    public function test_a_join_request_keeps_its_commercial_register(): void
    {
        $this->sendEnquiry([
            'source' => ContactSourceEnum::JOIN_REQUEST->value,
            'commercial_register' => 'CR-99887',
        ])->assertCreated();

        $enquiry = ContactMessage::sole();

        $this->assertSame(ContactSourceEnum::JOIN_REQUEST, $enquiry->source);
        $this->assertSame('CR-99887', $enquiry->commercial_register);
    }

    public function test_the_inbox_is_told(): void
    {
        $this->sendEnquiry()->assertCreated();

        Mail::assertSent(ContactMessageReceived::class);
    }

    public function test_the_endpoint_is_closed_without_the_key(): void
    {
        $this->sendEnquiry(key: null)->assertUnauthorized();
        $this->sendEnquiry(key: 'wrong-key')->assertUnauthorized();

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_an_unset_key_fails_closed(): void
    {
        config(['services.partner_api.key' => null]);

        $this->sendEnquiry()->assertStatus(503);
    }

    public function test_the_payload_is_validated(): void
    {
        $this->sendEnquiry(['phone' => ''])->assertJsonValidationErrorFor('phone');
        $this->sendEnquiry(['message' => ''])->assertJsonValidationErrorFor('message');
        $this->sendEnquiry(['source' => 'somewhere_else'])->assertJsonValidationErrorFor('source');
        $this->sendEnquiry(['created_at' => now()->addYear()->toIso8601String()])
            ->assertJsonValidationErrorFor('created_at');
    }

    public function test_the_backfill_may_keep_an_enquiry_its_original_date(): void
    {
        /* The storefront's one-off copy of enquiries written before they lived
           here — an enquiry from March must not arrive dated today. */
        $this->sendEnquiry(['created_at' => '2026-03-04 09:15:00'])->assertCreated();

        $enquiry = ContactMessage::sole();

        $this->assertSame('2026-03-04 09:15:00', $enquiry->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-03-04 09:15:00',
            $enquiry->logs()->sole()->created_at->format('Y-m-d H:i:s'),
            'The opening log entry is dated with the enquiry, not with the import.',
        );
    }
}
