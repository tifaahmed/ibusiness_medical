<?php

namespace Tests\Feature\Api;

use App\Actions\Facilities\SearchDirectoryAction;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/api/v1/facilities/search`: one phrase, answered across the whole directory.
 *
 * The listing endpoint searches facility names and nothing else. This one is
 * what a visitor standing outside a clinic actually needs — the number off the
 * door, the street, or just the name of the town — so the tests here are mostly
 * about the fields that endpoint cannot reach, and about the two things a
 * grouped answer has to get right: the cap per group, and the honest total
 * behind it.
 *
 * Read by the Deilar storefront's search box; see `App\Actions\Facilities\
 * SearchFacilities` over there.
 */
class GuestFacilitySearchTest extends TestCase
{
    use RefreshDatabase;

    private Governorate $cairo;

    private City $nasrCity;

    private FacilityType $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cairo = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->nasrCity = $this->city($this->cairo, 'Nasr City', 'مدينة نصر');
        $this->clinic = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    public function test_a_facility_is_found_by_its_name(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');

        $items = $this->groupFrom($this->search('Nile'), 'facility');

        $this->assertSame([$facility->id], array_column($items, 'id'));
        $this->assertSame('Nile Clinic', $items[0]['name']);
        $this->assertSame('Clinic', $items[0]['type_name']);
    }

    /*
     * The one field the listing endpoint has never been able to search. A
     * street name is often all somebody remembers about the place they are
     * standing in front of.
     */
    public function test_a_branch_is_found_by_its_address(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');
        $branch = $this->branch($facility, 'Nasr City', 'فرع مدينة نصر', '12 Abbas El Akkad', '١٢ عباس العقاد');

        $items = $this->groupFrom($this->search('Abbas'), 'branch');

        $this->assertSame([$branch->id], array_column($items, 'id'));
        $this->assertSame('Nile Clinic', $items[0]['facility_name']);
        $this->assertSame($facility->slug, $items[0]['facility_slug']);
        $this->assertSame('Nasr City', $items[0]['city']);
    }

    /*
     * A number is copied off a card or a shopfront with whatever spacing it was
     * printed with, and stored with whatever spacing it was typed with. Digits
     * are the only thing the two are guaranteed to have in common.
     */
    public function test_a_branch_is_found_by_a_phone_number_however_it_is_spaced(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');
        $branch = $this->branch($facility, 'Nasr City', 'فرع مدينة نصر', '12 Abbas El Akkad', '١٢ عباس العقاد', ['0100 000 0000']);

        foreach (['01000000000', '0100 000', '0100-000'] as $typed) {
            $items = $this->groupFrom($this->search($typed), 'branch');

            $this->assertSame([$branch->id], array_column($items, 'id'), "Searching {$typed} should find the branch.");
        }
    }

    /* Too short a fragment would match half the numbers in the country. */
    public function test_a_one_or_two_digit_fragment_is_not_a_phone_search(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');
        $this->branch($facility, 'Nasr City', 'فرع مدينة نصر', '12 Abbas El Akkad', '١٢ عباس العقاد', ['0100 000 0000']);

        $this->assertSame([], $this->groupFrom($this->search('01'), 'branch'));
    }

    public function test_a_city_is_found_and_says_how_many_facilities_are_in_it(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');
        $other = $this->facility('Delta Labs', 'معامل الدلتا');

        /* The second facility reaches the city through a branch rather than
           through its own record — both count as "somewhere I can walk into". */
        $other->forceFill(['city_id' => null, 'governorate_id' => null])->save();
        $this->branch($other, 'Nasr City', 'فرع مدينة نصر', '5 Tahrir St', '٥ شارع التحرير');

        $items = $this->groupFrom($this->search('Nasr'), 'city');

        $this->assertSame([$this->nasrCity->id], array_column($items, 'id'));
        $this->assertSame('Cairo', $items[0]['governorate']);
        $this->assertSame(2, $items[0]['facility_count']);
        $this->assertNotNull($facility->id);
    }

    public function test_a_governorate_and_a_facility_type_are_their_own_groups(): void
    {
        $this->facility('Nile Clinic', 'عيادة النيل');

        $this->assertSame(
            [$this->cairo->id],
            array_column($this->groupFrom($this->search('Cairo'), 'governorate'), 'id'),
        );

        $types = $this->groupFrom($this->search('Clinic'), 'facility_type');

        $this->assertSame([$this->clinic->id], array_column($types, 'id'));
        $this->assertSame(1, $types[0]['facility_count']);
    }

    /*
     * Nobody reaches for the hamza on a phone keyboard, and the same town is
     * spelled both ways in the data itself.
     */
    public function test_hamza_and_taa_marbuta_are_folded(): void
    {
        $ismailia = $this->city($this->cairo, 'Ismailia', 'الإسماعيلية');

        $facility = $this->facility('Ismailia Clinic', 'عيادة الإسماعيلية');
        $facility->forceFill(['city_id' => $ismailia->id])->save();

        $this->assertSame(
            [$ismailia->id],
            array_column($this->groupFrom($this->search('الاسماعيليه'), 'city'), 'id'),
        );
    }

    /*
     * A place is only ever offered as a filter, so one holding nothing is a
     * suggestion whose only possible outcome is an empty grid — the same rule
     * the listing endpoint draws its city dropdown by.
     */
    public function test_a_place_with_nothing_in_it_is_not_offered(): void
    {
        $this->city($this->cairo, 'Nasr Town', 'مدينة نصر الجديدة');
        $emptyType = FacilityType::create(['name' => ['en' => 'Nasr Pharmacy', 'ar' => 'صيدلية نصر']]);
        $emptyGovernorate = Governorate::create(['name' => ['en' => 'Nasr Valley', 'ar' => 'وادي نصر']]);

        $this->facility('Nile Clinic', 'عيادة النيل');

        /* "Nasr" matches the hosting city, the empty one, the empty type and
           the empty governorate — only the hosting city may come back. */
        $results = $this->search('Nasr');

        $this->assertSame(
            [$this->nasrCity->id],
            array_column($this->groupFrom($results, 'city'), 'id'),
        );
        $this->assertSame([], $this->groupFrom($results, 'facility_type'));
        $this->assertSame([], $this->groupFrom($results, 'governorate'));
        $this->assertNotNull($emptyType->id);
        $this->assertNotNull($emptyGovernorate->id);
    }

    /* An Arabic page still gets English typed into it, and the other way. */
    public function test_a_name_written_in_the_other_language_is_still_found(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');

        $items = $this->groupFrom($this->search('النيل', 'en'), 'facility');

        $this->assertSame([$facility->id], array_column($items, 'id'));
        /* Found through the Arabic name, but read back in the language asked
           for: the row is rendered on an English page. */
        $this->assertSame('Nile Clinic', $items[0]['name']);
    }

    /*
     * A second word narrows. Matching any word instead of every word turns a
     * more specific search into a longer list, which is the opposite of what
     * typing more is for — and the two words may land in different fields.
     */
    public function test_every_word_has_to_match_somewhere(): void
    {
        $facility = $this->facility('Nile Clinic', 'عيادة النيل');
        $this->branch($facility, 'Downtown', 'وسط البلد', '12 Abbas El Akkad', '١٢ عباس العقاد');

        $other = $this->facility('Delta Clinic', 'عيادة الدلتا');
        $this->branch($other, 'Downtown', 'وسط البلد', '5 Tahrir St', '٥ شارع التحرير');

        $branches = $this->groupFrom($this->search('Downtown Abbas'), 'branch');

        $this->assertCount(1, $branches);
        $this->assertSame('Nile Clinic', $branches[0]['facility_name']);
    }

    /*
     * The cap is what keeps a suggestion box a suggestion box; the total is
     * what lets the consumer offer the rest honestly rather than pretending
     * five is all there is.
     */
    public function test_a_group_is_capped_and_reports_the_full_total(): void
    {
        foreach (range(1, 7) as $index) {
            $this->facility("Nile Clinic {$index}", "عيادة النيل {$index}");
        }

        $response = $this->getJson('/api/v1/facilities/search?q=Nile&per_group=3')->assertOk();

        $group = collect($response->json('groups'))->firstWhere('type', 'facility');

        $this->assertCount(3, $group['items']);
        $this->assertSame(7, $group['total']);
    }

    public function test_a_group_with_nothing_in_it_is_left_out(): void
    {
        $this->facility('Nile Clinic', 'عيادة النيل');

        $types = collect($this->search('Nile')['groups'] ?? [])->pluck('type')->all();

        $this->assertSame(['facility'], $types);
    }

    public function test_a_term_shorter_than_two_characters_is_not_a_search(): void
    {
        $this->facility('Nile Clinic', 'عيادة النيل');

        $this->assertSame([], $this->search('N')['groups']);
        $this->assertSame([], $this->search(' ')['groups']);
    }

    public function test_per_group_cannot_be_pushed_past_the_ceiling(): void
    {
        $this->getJson('/api/v1/facilities/search?q=Nile&per_group='.(SearchDirectoryAction::MAX_PER_GROUP + 1))
            ->assertStatus(422);
    }

    /**
     * @return array<string, mixed>
     */
    private function search(string $term, string $locale = 'en'): array
    {
        return $this->getJson(
            '/api/v1/facilities/search?q='.rawurlencode($term),
            ['X-Locale' => $locale],
        )->assertOk()->json();
    }

    /**
     * @param  array<string, mixed>  $results
     * @return list<array<string, mixed>>
     */
    private function groupFrom(array $results, string $type): array
    {
        foreach ($results['groups'] ?? [] as $group) {
            if ($group['type'] === $type) {
                return $group['items'];
            }
        }

        return [];
    }

    private function city(Governorate $governorate, string $en, string $ar): City
    {
        return City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => $en, 'ar' => $ar],
        ]);
    }

    private function facility(string $en, string $ar): Facility
    {
        $facility = Facility::create([
            'name' => ['en' => $en, 'ar' => $ar],
            'facility_type_id' => $this->clinic->id,
        ]);

        /* Not fillable on the model — the admin screens set these past the
           guard too. */
        $facility->forceFill([
            'governorate_id' => $this->cairo->id,
            'city_id' => $this->nasrCity->id,
        ])->save();

        return $facility->refresh();
    }

    /**
     * @param  list<string>  $phones
     */
    private function branch(
        Facility $facility,
        string $nameEn,
        string $nameAr,
        string $addressEn,
        string $addressAr,
        array $phones = ['0100000000'],
    ): FacilityBranch {
        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => $nameEn, 'ar' => $nameAr],
            'address' => ['en' => $addressEn, 'ar' => $addressAr],
            'phone' => $phones,
            'governorate_id' => $this->cairo->id,
            'city_id' => $this->nasrCity->id,
        ]);
    }
}
