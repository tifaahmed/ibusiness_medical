<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\City;
use App\Models\Governorate;
use App\Services\AreaNameFixer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Re-spacing the census's run-together Arabic area names and adding English —
 * entirely behind Http::fake(): nothing here may reach the real Gemini API.
 */
class AreaNameFixerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key', 'services.gemini.base_url' => 'https://gemini.test/v1beta']);
    }

    private function areas(array $names): array
    {
        $gov = Governorate::create(['name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية']]);
        $city = City::create(['governorate_id' => $gov->id, 'name' => ['en' => 'Cleopatra', 'ar' => 'كليوباترا']]);

        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = Area::create(['governorate_id' => $gov->id, 'city_id' => $city->id, 'name' => ['ar' => $name], 'pcode' => 'EG99000'.$i, 'slug' => 'eg99000'.$i]);
        }

        return $rows;
    }

    private function fakeGemini(array $byIndex, array $areas): void
    {
        $payload = [];
        foreach ($byIndex as $i => $answer) {
            $payload[(string) $areas[$i]->id] = $answer;
        }

        Http::fake(['gemini.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]]],
        ])]);
    }

    public function test_same_letters_ignores_spaces_and_the_spelling_variants_the_census_mixes(): void
    {
        $this->assertTrue(AreaNameFixer::sameLetters('مصطفيكاملوبولوكلي', 'مصطفى كامل وبولوكلي'));
        $this->assertTrue(AreaNameFixer::sameLetters('ابوالمطامير', 'أبو المطامير'));
        $this->assertTrue(AreaNameFixer::sameLetters('الجزيره', 'الجزيرة'));
        $this->assertFalse(AreaNameFixer::sameLetters('مصطفيكاملوبولوكلي', 'مصطفى كامل'), 'a dropped word is a rewrite');
        $this->assertFalse(AreaNameFixer::sameLetters('الرمل', 'الرمل الاول'), 'an added word is a rewrite');
        $this->assertFalse(AreaNameFixer::sameLetters('', ''));
    }

    public function test_it_respaces_the_arabic_and_saves_the_english(): void
    {
        $areas = $this->areas(['مصطفيكاملوبولوكلي']);
        $this->fakeGemini([['ar' => 'مصطفى كامل وبولوكلي', 'en' => 'Mostafa Kamel & Bolokly']], $areas);

        $fixer = app(AreaNameFixer::class);
        $proposals = $fixer->propose(Area::with('city.governorate')->get());
        $this->assertTrue($proposals[$areas[0]->id]['ar_changed']);
        $this->assertTrue($fixer->apply($areas[0], $proposals[$areas[0]->id]));

        $fresh = $areas[0]->fresh();
        $this->assertSame('مصطفى كامل وبولوكلي', $fresh->getTranslation('name', 'ar'));
        $this->assertSame('Mostafa Kamel & Bolokly', $fresh->getTranslation('name', 'en'));
        $this->assertSame('eg990000', $fresh->slug, 'the slug is the pcode and does not follow the name');
    }

    public function test_an_arabic_rewrite_is_rejected_but_the_english_is_still_used(): void
    {
        $areas = $this->areas(['الرمل']);
        $this->fakeGemini([['ar' => 'حي الرمل الأول', 'en' => 'El Raml']], $areas);

        $proposal = app(AreaNameFixer::class)->propose(Area::with('city.governorate')->get())[$areas[0]->id];

        $this->assertTrue($proposal['ar_rejected']);
        $this->assertFalse($proposal['ar_changed']);
        $this->assertSame('الرمل', $proposal['ar']);
        $this->assertSame('El Raml', $proposal['en']);
    }

    public function test_english_left_in_arabic_is_not_used(): void
    {
        $areas = $this->areas(['الرمل']);
        $this->fakeGemini([['ar' => 'الرمل', 'en' => 'الرمل']], $areas);

        $proposal = app(AreaNameFixer::class)->propose(Area::with('city.governorate')->get())[$areas[0]->id];

        $this->assertNull($proposal['en']);
        $this->assertFalse($proposal['ar_changed']);
    }

    public function test_the_command_fills_only_areas_without_english_and_dry_writes_nothing(): void
    {
        $areas = $this->areas(['الرمل', 'كرموز']);
        $areas[1]->setTranslation('name', 'en', 'Already Named')->save();
        $this->fakeGemini([['ar' => 'الرمل', 'en' => 'El Raml']], $areas);

        $this->artisan('areas:fix-names', ['--dry' => true])->assertSuccessful();
        $this->assertNull($areas[0]->fresh()->getTranslation('name', 'en', false) ?: null);

        $this->artisan('areas:fix-names')->assertSuccessful();
        $this->assertSame('El Raml', $areas[0]->fresh()->getTranslation('name', 'en'));
        $this->assertSame('Already Named', $areas[1]->fresh()->getTranslation('name', 'en'), 'an edited name is left alone');

        Http::assertSentCount(2);
    }
}
