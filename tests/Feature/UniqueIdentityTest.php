<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A phone and a National ID belong to ONE live record, whichever door the
 * write comes through. Run on a throwaway database (see AGENTS.md).
 */
class UniqueIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $phone): User
    {
        return User::factory()->create(['phone' => $phone]);
    }

    private function card(User $user, string $nid, string $number): Membership
    {
        return Membership::create(['user_id' => $user->id, 'national_id' => $nid, 'membership_number' => $number]);
    }

    public function test_a_second_user_cannot_take_a_used_phone(): void
    {
        $this->user('01012345678');

        $this->expectException(ValidationException::class);
        $this->user('01012345678');
    }

    public function test_a_phone_cannot_be_changed_onto_a_used_one(): void
    {
        $this->user('01012345678');
        $other = $this->user('01099999999');

        try {
            $other->update(['phone' => '01012345678']);
            $this->fail('Duplicate phone was saved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }
    }

    public function test_a_deleted_users_phone_can_be_reused(): void
    {
        $this->user('01012345678')->delete();

        $this->assertNotNull($this->user('01012345678')->id);
    }

    public function test_a_second_card_cannot_take_a_used_national_id(): void
    {
        $this->card($this->user('01011111111'), '29901011234567', '1001');

        $this->expectException(ValidationException::class);
        $this->card($this->user('01022222222'), '29901011234567', '1002');
    }

    public function test_a_legacy_duplicate_stays_editable_until_its_id_is_touched(): void
    {
        $a = $this->card($this->user('01011111111'), '29901011234567', '1001');
        $b = $this->card($this->user('01022222222'), '29901019999999', '1002');
        // Imported data: force the clash past the model, as the old import did.
        Membership::query()->whereKey($b->id)->update(['national_id' => '29901011234567']);

        $b->refresh()->update(['job_title' => ['en' => 'Nurse']]);

        $this->assertSame('29901011234567', $b->fresh()->national_id);
        $this->assertSame('29901011234567', $a->fresh()->national_id);
    }
}
