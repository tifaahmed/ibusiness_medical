<?php

namespace Tests\Unit;

use App\Models\CardTemplate;
use App\Support\CardTemplateLayoutDefaults;
use PHPUnit\Framework\TestCase;

/**
 * The template editor's "Show on the card" switch: `layout.<field>.hidden`.
 * No database: the model is never saved.
 */
class CardTemplateHiddenFieldsTest extends TestCase
{
    private function template(string $kind, array $hide): CardTemplate
    {
        $layout = CardTemplateLayoutDefaults::layout();
        foreach ($hide as $field => $value) {
            $layout[$field]['hidden'] = $value;
        }

        $template = new CardTemplate;
        $template->kind = $kind;
        $template->layout = $layout;

        return $template;
    }

    public function test_a_field_the_admin_hid_is_not_drawn_and_keeps_its_box(): void
    {
        $template = $this->template(CardTemplate::KIND_SOFT, ['facebook' => true, 'phone' => '1', 'website' => false]);

        $this->assertSame(['facebook', 'phone'], $template->adminHiddenFields());
        $this->assertContains('facebook', $template->hidden_fields);
        $this->assertContains('phone', $template->hidden_fields);
        $this->assertNotContains('website', $template->hidden_fields);

        $drawn = $template->effectiveLayout();
        $this->assertArrayNotHasKey('facebook', $drawn);
        $this->assertArrayNotHasKey('phone', $drawn);
        $this->assertArrayHasKey('website', $drawn);
        // Hiding never throws the position away.
        $this->assertArrayHasKey('x', $template->layout['facebook']);
    }

    public function test_what_the_kind_hides_stays_hidden_whatever_the_switch_says(): void
    {
        $soft = $this->template(CardTemplate::KIND_SOFT, ['barcode' => false]);
        $this->assertContains('barcode', $soft->hidden_fields);

        $physical = $this->template(CardTemplate::KIND_PHYSICAL, ['full_name' => false]);
        $this->assertContains('full_name', $physical->hidden_fields);
        $this->assertSame([], $physical->adminHiddenFields());
    }

    public function test_the_back_can_be_switched_off_for_members(): void
    {
        $template = new CardTemplate;
        $this->assertTrue($template->showsBack());
        $this->assertTrue($template->has_back);

        $template->back_settings = ['enabled' => true, 'hidden' => true];
        $this->assertFalse($template->showsBack());
        $this->assertFalse($template->has_back);
        // Printing keeps its custom back: only what members see changes.
        $this->assertTrue($template->hasCustomBack());

        $template->back_settings = ['hidden' => false];
        $this->assertTrue($template->has_back);
    }
}
