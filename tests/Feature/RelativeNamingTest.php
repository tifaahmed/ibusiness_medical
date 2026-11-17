<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Who may be added and how the name is built (2026-11-17): man / woman rules, father by grandfather name, a woman needs her husband first. */
class RelativeNamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The add routes are rate limited (10 a minute); this test adds more than that on purpose.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function card(string $gender, string $name): array
    {
        $user = User::factory()->create(['name' => $name, 'gender' => $gender, 'phone' => '0106'.random_int(1000000, 9999999)]);
        $card = Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_FULL, 'expiration_date' => now()->addMonths(6)]);
        Sanctum::actingAs($user);

        return [$user, $card];
    }

    private function member(string $relationship, string $name)
    {
        return $this->post('/api/v1/family/members', [
            'name' => $name, 'relationship' => $relationship, 'national_id' => (string) random_int(30000000000000, 30999999999999),
            'id_front' => UploadedFile::fake()->image('f.jpg'), 'id_back' => UploadedFile::fake()->image('b.jpg'), 'photo' => UploadedFile::fake()->image('p.jpg'),
        ], ['Accept' => 'application/json']);
    }

    private function child(string $first, string $rel = 'son')
    {
        return $this->postJson('/api/v1/family/children', ['first_name' => $first, 'relationship' => $rel, 'date_of_birth' => '2015-01-01']);
    }

    private function names(Membership $card): array
    {
        return $card->relatives()->with('user')->get()->map(fn ($r) => $r->user->name)->all();
    }

    /** @test */
    public function a_father_adds_any_number_of_children_with_his_name_and_a_wife_a_mother_and_his_father(): void
    {
        [, $card] = $this->card('male', 'Ahmed Ali Hassan');

        foreach (range('a', 'q') as $i) {
            $this->child('Kid'.str_repeat($i, 2))->assertCreated();
        }
        $this->assertCount(17, $card->relatives()->get());

        $this->member('wife', 'Mona Samy Said')->assertCreated();
        $this->member('wife', 'Dina Samy Said')->assertStatus(422);
        $this->member('mother', 'Fatma Ali Hassan')->assertCreated();
        $this->member('husband', 'Some Man Name')->assertStatus(422);

        // The father is typed with the grandfather's name only: his first name is the member's SECOND name.
        $this->member('father', 'Hassan')->assertCreated();
        $this->assertContains('Ali Hassan', $this->names($card));
        $this->assertContains('Kidaa Ahmed Ali Hassan', $this->names($card));
    }

    /** @test */
    public function a_mother_adds_her_father_and_her_mother_but_children_only_after_her_husband(): void
    {
        [, $card] = $this->card('female', 'Mona Samy Said');

        $this->child('Omar')->assertStatus(422);
        $this->member('wife', 'Someone Else Name')->assertStatus(422);

        $this->member('father', 'Said')->assertCreated();
        $this->assertContains('Samy Said', $this->names($card));
        $this->member('mother', 'Laila Abbas Said')->assertCreated();
        $this->member('mother', 'Another Mother Name')->assertStatus(422);

        $this->member('husband', 'Ahmed Ali Hassan')->assertCreated();
        $this->child('Omar')->assertCreated();
        $this->assertContains('Omar Ahmed Ali Hassan', $this->names($card));
    }
}
