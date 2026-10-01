<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Suggest with AI" on the facility banner card. The provider is always faked:
 * these assert what the application does with an answer — which it cleans up
 * (colour format, unreadable text) and which it refuses (too long for the
 * ribbon, wrong language).
 */
class FacilityBannerSuggestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function fakeAi(array ...$payloads): void
    {
        $sequence = Http::sequence();
        foreach ($payloads as $payload) {
            $sequence->push(['candidates' => [['content' => ['parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]]]], 200);
        }

        Http::fake(['*' => $sequence]);
    }

    private function ask(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(route('admin.facility.banner.suggest'), [
            'name' => ['ar' => 'مستشفى رسال', 'en' => 'Rasal Hospital'],
            'facility_type' => 'مستشفى - Hospital',
            'discount_percent' => 20,
            'description' => ['ar' => '<p>خصم 20% على الكشف</p>', 'en' => ''],
        ]);
    }

    public function test_the_suggestion_comes_back_with_eight_digit_colours(): void
    {
        $this->fakeAi([
            'message_ar' => 'خصم 20%',
            'message_en' => '20% off',
            'bg_color' => '#DC2626',
            'text_color' => '#fff',
            'shadow_color' => '#00000040',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('values.message_ar', 'خصم 20%')
            ->assertJsonPath('values.message_en', '20% OFF')
            ->assertJsonPath('values.bg_color', '#dc2626ff')
            ->assertJsonPath('values.text_color', '#ffffffff')
            ->assertJsonPath('values.shadow_color', '#00000040');

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'Discount for members: 20%'));
    }

    public function test_unreadable_text_is_swapped_for_black_or_white(): void
    {
        $this->fakeAi([
            'message_ar' => 'جديد',
            'message_en' => 'New',
            'bg_color' => '#fde047',
            'text_color' => '#fef08a',
        ]);

        $this->ask()
            ->assertOk()
            ->assertJsonPath('values.text_color', '#000000ff')
            ->assertJsonPath('values.shadow_color', '#00000033');
    }

    public function test_a_message_too_long_for_the_ribbon_is_retried_then_refused(): void
    {
        $tooLong = [
            'message_ar' => 'خصم عشرين بالمئة على جميع الخدمات الطبية',
            'message_en' => 'Twenty percent off all services',
            'bg_color' => '#dc2626',
            'text_color' => '#ffffff',
        ];
        $this->fakeAi($tooLong, $tooLong);

        $this->ask()->assertStatus(422);

        Http::assertSentCount(2);
    }

    public function test_a_bad_first_answer_is_replaced_by_a_good_second(): void
    {
        $this->fakeAi(
            ['message_ar' => '20% OFF', 'message_en' => '20% OFF', 'bg_color' => '#dc2626', 'text_color' => '#ffffff'],
            ['message_ar' => 'خصم 20%', 'message_en' => '20% OFF', 'bg_color' => 'red', 'text_color' => '#ffffff'],
        );

        // Both answers are unusable: wrong language, then a colour name.
        $this->ask()->assertStatus(422);

        $this->fakeAi(
            ['message_ar' => '20% OFF', 'message_en' => '20% OFF', 'bg_color' => '#dc2626', 'text_color' => '#ffffff'],
            ['message_ar' => 'خصم 20%', 'message_en' => '20% OFF', 'bg_color' => '#2563eb', 'text_color' => '#ffffff'],
        );

        $this->ask()->assertOk()->assertJsonPath('values.bg_color', '#2563ebff');
    }

    public function test_without_a_key_the_admin_is_told_rather_than_given_a_500(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake();

        $this->ask()->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'GEMINI_API_KEY'));

        Http::assertNothingSent();
    }
}
