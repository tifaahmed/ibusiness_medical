<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public guest product endpoints that the Deilar storefront reads.
 *
 * The catalogue is filtered, sorted and paginated here so the storefront and
 * this application cannot disagree about what a search returns.
 */
class ProductsApiTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $en, string $ar, array $attributes = []): Product
    {
        return Product::create([
            'name' => ['en' => $en, 'ar' => $ar],
            'short_subject' => ['en' => $en.' subject', 'ar' => $ar],
            ...$attributes,
        ]);
    }

    /**
     * The sidebar is a set of facets, not a fixed list: an option is only worth
     * offering if a currently-matching product carries it.
     *
     * Each facet is counted with its OWN dimension left out, so picking a
     * category narrows the tags beside it without collapsing the category list
     * to the one already picked.
     */
    public function test_the_filter_options_narrow_to_the_products_on_show(): void
    {
        $devices = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);
        $consumables = ProductType::create(['name' => ['en' => 'Consumables', 'ar' => 'مستهلكات']]);

        $bestSeller = Tag::create(['name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'], 'color' => '#111111']);
        $sterile = Tag::create(['name' => ['en' => 'Sterile', 'ar' => 'معقم'], 'color' => '#222222']);
        /* Tags are shared with facilities and services, so one no product
           carries must never reach a shop sidebar. */
        Tag::create(['name' => ['en' => 'Clinic only', 'ar' => 'للعيادات فقط'], 'color' => '#333333']);

        $monitor = $this->product('Monitor', 'شاشة', [
            'new_price' => 750,
            'product_type_id' => $devices->id,
        ]);
        $monitor->tags()->attach($bestSeller);

        $gloves = $this->product('Gloves', 'قفازات', [
            'new_price' => 50,
            'product_type_id' => $consumables->id,
        ]);
        $gloves->tags()->attach([$bestSeller->id, $sterile->id]);

        $unfiltered = $this->getJson('/api/v1/products?lang=en')->assertOk();

        $this->assertSame(['Best seller', 'Sterile'], $this->names($unfiltered, 'tags'));
        $this->assertNotContains('Clinic only', $this->names($unfiltered, 'tags'));

        /*
         * Filtered to Devices, only the tags a device carries survive — Sterile
         * belongs to a consumable and drops out.
         */
        $filtered = $this->getJson("/api/v1/products?lang=en&product_type_id={$devices->id}")->assertOk();

        $this->assertSame(['Best seller'], $this->names($filtered, 'tags'));
        $filtered->assertJsonPath('tags.0.products_count', 1);

        /*
         * The category list is NOT narrowed by the chosen category, or picking
         * one would leave nothing to switch to.
         */
        $this->assertContains('Devices', $this->names($filtered, 'product_types'));
        $this->assertContains('Consumables', $this->names($filtered, 'product_types'));

        /* The mirror image: choosing a tag narrows the categories instead. */
        $byTag = $this->getJson("/api/v1/products?lang=en&tag_id={$sterile->id}")->assertOk();

        $this->assertSame(['Consumables'], $this->names($byTag, 'product_types'));
        $this->assertContains('Best seller', $this->names($byTag, 'tags'));
    }

    /**
     * A search narrows the sidebar too — the options describe the results, not
     * the catalogue.
     */
    public function test_a_search_narrows_the_filter_options(): void
    {
        $devices = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);
        $consumables = ProductType::create(['name' => ['en' => 'Consumables', 'ar' => 'مستهلكات']]);

        $this->product('Monitor', 'شاشة', ['product_type_id' => $devices->id]);
        $this->product('Gloves', 'قفازات', ['product_type_id' => $consumables->id]);

        $response = $this->getJson('/api/v1/products?lang=en&search=Gloves')->assertOk();

        $this->assertSame(['Consumables'], $this->names($response, 'product_types'));
    }

    /**
     * The names of one filter facet, sorted so a test asserts on the set
     * rather than on the count ordering.
     *
     * @return list<string>
     */
    private function names(\Illuminate\Testing\TestResponse $response, string $facet): array
    {
        return collect($response->json($facet))
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    public function test_the_catalogue_lists_products_with_its_filter_options(): void
    {
        $type = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);
        $tag = Tag::create(['name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'], 'icon' => '🔥', 'color' => '#ff0000']);

        $product = $this->product('Blood pressure monitor', 'جهاز قياس الضغط', [
            'old_price' => 1000,
            'new_price' => 750,
            'product_type_id' => $type->id,
        ]);
        $product->tags()->attach($tag);

        $response = $this->getJson('/api/v1/products?lang=en')->assertOk();

        $response->assertJsonPath('products.data.0.name', 'Blood pressure monitor')
            ->assertJsonPath('products.data.0.price', '750.00')
            ->assertJsonPath('products.data.0.discount_percent', 25)
            ->assertJsonPath('products.data.0.product_type.name', 'Devices')
            ->assertJsonPath('products.data.0.tags.0.name', 'Best seller')
            ->assertJsonPath('products.data.0.tags.0.icon', '🔥')
            ->assertJsonPath('products.meta.total', 1)
            ->assertJsonPath('product_types.0.products_count', 1)
            ->assertJsonPath('tags.0.products_count', 1)
            ->assertJsonPath('price_range.min', 750)
            ->assertJsonPath('price_range.max', 750)
            ->assertJsonPath('product_names.0.slug', $product->slug);
    }

    /**
     * Tag names are translatable like every other name in the catalogue, so an
     * Arabic storefront must get the Arabic tag rather than the English one it
     * was filed under.
     */
    public function test_a_tag_name_follows_the_requested_language(): void
    {
        $tag = Tag::create([
            'name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'],
            'icon' => '🔥',
            'color' => '#ff0000',
        ]);

        $this->product('Blood pressure monitor', 'جهاز قياس الضغط', ['new_price' => 750])
            ->tags()->attach($tag);

        $this->getJson('/api/v1/products?lang=ar')
            ->assertOk()
            ->assertJsonPath('products.data.0.tags.0.name', 'الأكثر مبيعًا')
            ->assertJsonPath('products.data.0.tags.0.icon', '🔥')
            ->assertJsonPath('tags.0.name', 'الأكثر مبيعًا');

        $this->getJson('/api/v1/products?lang=en')
            ->assertOk()
            ->assertJsonPath('products.data.0.tags.0.name', 'Best seller')
            ->assertJsonPath('tags.0.name', 'Best seller');
    }

    /**
     * A product that was never marked down carries one price, and quoting a
     * discount off it would invent a saving that does not exist.
     */
    public function test_a_product_without_a_markdown_reports_no_discount(): void
    {
        $this->product('Thermometer', 'ترمومتر', ['old_price' => 200]);

        $this->getJson('/api/v1/products?lang=en')
            ->assertOk()
            ->assertJsonPath('products.data.0.price', '200.00')
            ->assertJsonPath('products.data.0.discount_percent', null);
    }

    public function test_the_catalogue_filters_by_category_tag_and_price(): void
    {
        $devices = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);
        $supplies = ProductType::create(['name' => ['en' => 'Supplies', 'ar' => 'مستلزمات']]);
        $tag = Tag::create(['name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا']]);

        $monitor = $this->product('Monitor', 'مونيتور', ['new_price' => 900, 'product_type_id' => $devices->id]);
        $monitor->tags()->attach($tag);
        $this->product('Gloves', 'قفازات', ['new_price' => 50, 'product_type_id' => $supplies->id]);

        $this->getJson('/api/v1/products?lang=en&product_type_id='.$devices->id)
            ->assertOk()
            ->assertJsonCount(1, 'products.data')
            ->assertJsonPath('products.data.0.name', 'Monitor');

        $this->getJson('/api/v1/products?lang=en&tag_id='.$tag->id)
            ->assertOk()
            ->assertJsonCount(1, 'products.data')
            ->assertJsonPath('products.data.0.name', 'Monitor');

        $this->getJson('/api/v1/products?lang=en&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'products.data')
            ->assertJsonPath('products.data.0.name', 'Gloves');
    }

    public function test_the_catalogue_searches_across_the_arabic_letter_forms(): void
    {
        $this->product('X-ray film', 'أفلام أشعة');

        $this->getJson('/api/v1/products?lang=ar&search='.urlencode('اشعة'))
            ->assertOk()
            ->assertJsonCount(1, 'products.data')
            ->assertJsonPath('products.data.0.name', 'أفلام أشعة');
    }

    /**
     * A product with no price at all is neither the cheapest nor the dearest;
     * heading the "low to high" page with it makes the sort look broken.
     */
    public function test_sorting_by_price_puts_unpriced_products_last(): void
    {
        $this->product('Dear', 'غالي', ['new_price' => 900]);
        $this->product('Cheap', 'رخيص', ['new_price' => 50]);
        $this->product('Unpriced', 'بدون سعر');

        $names = $this->getJson('/api/v1/products?lang=en&sort=price_asc')
            ->assertOk()
            ->json('products.data.*.name');

        $this->assertSame(['Cheap', 'Dear', 'Unpriced'], $names);
    }

    /**
     * How a storefront re-prices a basket: one call naming what is in it,
     * rather than a request per line.
     */
    public function test_the_catalogue_can_be_asked_for_named_products_only(): void
    {
        $first = $this->product('Monitor', 'مونيتور', ['new_price' => 900]);
        $this->product('Gloves', 'قفازات', ['new_price' => 50]);
        $third = $this->product('Mask', 'كمامة', ['new_price' => 20]);

        $names = $this->getJson('/api/v1/products?lang=en&slugs[]='.$first->slug.'&slugs[]='.$third->slug)
            ->assertOk()
            ->assertJsonCount(2, 'products.data')
            ->json('products.data.*.name');

        sort($names);

        $this->assertSame(['Mask', 'Monitor'], $names);
    }

    public function test_a_product_page_returns_its_gallery_and_related_products(): void
    {
        $type = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);

        $product = $this->product('Monitor', 'مونيتور', [
            'description' => ['en' => '<p>Reads in ten seconds.</p>', 'ar' => 'يقرأ في عشر ثوان'],
            'new_price' => 900,
            'product_type_id' => $type->id,
        ]);
        $product->galleries()->create(['image_path' => 'products/monitor-side.jpg', 'sort_order' => 1]);

        $sibling = $this->product('Stethoscope', 'سماعة طبية', ['product_type_id' => $type->id]);

        $response = $this->getJson('/api/v1/products/'.$product->slug.'?lang=en')->assertOk();

        $response->assertJsonPath('product.name', 'Monitor')
            ->assertJsonPath('product.description', '<p>Reads in ten seconds.</p>')
            ->assertJsonPath('product.product_type.name', 'Devices')
            ->assertJsonCount(1, 'product.gallery')
            ->assertJsonPath('related.0.slug', $sibling->slug);
    }

    public function test_an_unknown_product_slug_is_a_404(): void
    {
        $this->getJson('/api/v1/products/nothing-here')->assertNotFound();
    }

    /**
     * The three storefront switches, which are independent on purpose: a
     * product can be listed but not openable, openable but not listed, or
     * readable but not sellable.
     */
    public function test_a_hidden_product_is_not_in_the_listing(): void
    {
        $this->product('Monitor', 'شاشة', ['new_price' => 750]);
        $this->product('Withdrawn', 'مسحوب', ['new_price' => 100, 'is_visible' => false]);

        $response = $this->getJson('/api/v1/products')->assertOk();

        $slugs = array_column($response->json('products.data'), 'slug');

        $this->assertContains('monitor', $slugs);
        $this->assertNotContains('withdrawn', $slugs);
    }

    /**
     * The sidebar counts and the price slider are drawn from the same
     * catalogue, so a hidden product must not put a count against a category
     * nobody can reach or stretch the slider past the cheapest thing on sale.
     */
    public function test_a_hidden_product_counts_towards_nothing(): void
    {
        $devices = ProductType::create(['name' => ['en' => 'Devices', 'ar' => 'أجهزة']]);
        $tag = Tag::create(['name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'], 'color' => '#111111']);

        $hidden = $this->product('Withdrawn', 'مسحوب', [
            'new_price' => 10,
            'product_type_id' => $devices->id,
            'is_visible' => false,
        ]);
        $hidden->tags()->attach($tag);

        $this->product('Monitor', 'شاشة', ['new_price' => 750]);

        $response = $this->getJson('/api/v1/products')->assertOk();

        $this->assertSame([], $response->json('product_types'));
        $this->assertSame([], $response->json('tags'));
        $this->assertEquals(750, $response->json('price_range.min'));
        $this->assertNotContains('withdrawn', array_column($response->json('product_names'), 'slug'));
    }

    /**
     * Hiding a product from the shop window must not empty the baskets of the
     * people who put it there while it was up — a storefront re-pricing a
     * basket asks by name, which is not browsing.
     */
    public function test_a_hidden_product_is_still_priced_when_asked_for_by_slug(): void
    {
        $this->product('Withdrawn', 'مسحوب', ['new_price' => 100, 'is_visible' => false]);

        $response = $this->getJson('/api/v1/products?slugs[]=withdrawn')->assertOk();

        $this->assertSame(['withdrawn'], array_column($response->json('products.data'), 'slug'));
    }

    /** A product that may not be opened does not exist to a visitor. */
    public function test_an_inaccessible_product_is_a_404(): void
    {
        $this->product('Teaser', 'تشويق', ['new_price' => 100, 'is_accessible' => false]);

        $this->getJson('/api/v1/products/teaser')->assertNotFound();
    }

    /**
     * Hidden is not the same as closed. An unlisted link handed to one
     * customer is exactly this combination, and it has to keep working.
     */
    public function test_a_hidden_product_can_still_be_opened_by_its_link(): void
    {
        $this->product('Unlisted', 'غير مدرج', ['new_price' => 100, 'is_visible' => false]);

        $this->getJson('/api/v1/products/unlisted')
            ->assertOk()
            ->assertJsonPath('product.slug', 'unlisted');
    }

    /**
     * The storefront cannot enforce what it does not know: it has to draw a
     * card without a link and a page without a buy button.
     */
    public function test_the_two_switches_the_storefront_draws_are_shipped(): void
    {
        $this->product('Monitor', 'شاشة', [
            'new_price' => 750,
            'is_purchasable' => false,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('products.data.0.is_accessible', true)
            ->assertJsonPath('products.data.0.is_purchasable', false);

        $this->getJson('/api/v1/products/monitor')
            ->assertOk()
            ->assertJsonPath('product.is_purchasable', false);
    }

    /** Everything is on by default, so an existing catalogue does not change. */
    public function test_a_product_is_live_everywhere_unless_said_otherwise(): void
    {
        $product = $this->product('Monitor', 'شاشة', ['new_price' => 750]);

        $this->assertTrue($product->fresh()->is_visible);
        $this->assertTrue($product->fresh()->is_accessible);
        $this->assertTrue($product->fresh()->is_purchasable);
    }
}
