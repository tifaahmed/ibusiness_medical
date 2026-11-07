<?php

namespace Tests\Feature\Admin;

use App\Models\MembershipCard;
use App\Models\User;
use App\Support\CardPrint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The batch "print sheet editor": one Save keeps every card's position and size + the sheet settings, for all PDFs. */
class CardSheetEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $u;
    }

    private function batch(User $by): MembershipCard
    {
        return MembershipCard::create(['batch_name' => 'B', 'quantity' => 0, 'start_number' => 1, 'membership_ids' => [], 'created_by' => $by->id]);
    }

    public function test_default_is_todays_layout_and_the_page_opens(): void
    {
        $by = $this->admin(['view membership card patches', 'create membership card patches']);
        $card = $this->batch($by);

        $this->assertSame(['h_lines' => [], 'v_lines' => [], 'pin_dx' => 0.0, 'top_reserve' => 1.5, 'side_margin' => 0.25, 'pin_y' => 0.4, 'guides' => true, 'cards' => []], CardPrint::sheet());

        $props = $this->actingAs($by)->get(route('admin.membership-card-patches.sheet', $card->id))->assertOk()->viewData('page')['props'];
        $this->assertSame('Admin/MembershipCard/SheetEditor', $this->actingAs($by)->get(route('admin.membership-card-patches.sheet', $card->id))->viewData('page')['component']);
        $this->assertTrue($props['canSave']);
        $this->assertSame(1.5, (float) $props['cardPrint']['sheet']['top_reserve']);
    }

    public function test_one_save_keeps_every_cards_position_size_and_the_sheet_settings(): void
    {
        $by = $this->admin(['view membership card patches', 'create membership card patches']);
        $card = $this->batch($by);
        $post = fn (array $b) => $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.membership-card-patches.sheet.save', $card->id), $b);

        $post([
            'top_reserve' => 1.3, 'side_margin' => 0.4, 'pin_y' => 0.35, 'guides' => false,
            'cards' => [['dx' => 0.1, 'dy' => -0.05, 'w' => 8.4, 'h' => 5.4], ['dx' => 0, 'dy' => 0, 'w' => null, 'h' => null]],
        ])->assertSessionHasNoErrors();

        $s = CardPrint::sheet();
        $this->assertSame(1.3, $s['top_reserve']);
        $this->assertSame(0.4, $s['side_margin']);
        $this->assertFalse($s['guides']);
        $this->assertSame(['dx' => 0.1, 'dy' => -0.05, 'w' => 8.4, 'h' => 5.4], $s['cards'][0]);
        $this->assertSame(['dx' => 0.0, 'dy' => 0.0, 'w' => null, 'h' => null], $s['cards'][1]);

        // Every PDF reads it through the page props.
        $props = $this->actingAs($by)->get(route('admin.membership-card-patches.sheet', $card->id))->viewData('page')['props'];
        $this->assertSame(8.4, (float) $props['cardPrint']['sheet']['cards'][0]['w']);

        // Saved again, it replaces (one row), never piles up.
        $post(['top_reserve' => 1.5]);
        $this->assertSame(1, \App\Models\Setting::where('slug', CardPrint::SHEET)->count());
        $this->assertSame([], CardPrint::sheet()['cards']);
    }

    public function test_junk_is_clamped_and_a_view_only_admin_cannot_save(): void
    {
        $by = $this->admin(['view membership card patches', 'create membership card patches']);
        $card = $this->batch($by);
        $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.membership-card-patches.sheet.save', $card->id), ['top_reserve' => 99, 'side_margin' => -4, 'cards' => [['dx' => 1e9, 'w' => 0]]])->assertSessionHasNoErrors();
        $s = CardPrint::sheet();
        $this->assertSame(6.0, $s['top_reserve']);
        $this->assertSame(0.0, $s['side_margin']);
        $this->assertSame(21.0, $s['cards'][0]['dx']);
        $this->assertSame(1.0, $s['cards'][0]['w']);

        $viewer = $this->admin(['view membership card patches']);
        $this->actingAs($viewer)->get(route('admin.membership-card-patches.sheet', $card->id))->assertOk();
        $this->assertFalse($this->actingAs($viewer)->get(route('admin.membership-card-patches.sheet', $card->id))->viewData('page')['props']['canSave']);
        $this->actingAs($viewer)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.membership-card-patches.sheet.save', $card->id), ['top_reserve' => 2])->assertForbidden();
        $this->assertSame(6.0, CardPrint::sheet()['top_reserve']);                  // untouched
    }

    public function test_one_save_also_keeps_the_card_sizes_the_lines_and_the_pin(): void
    {
        $by = $this->admin(['view membership card patches', 'create membership card patches']);
        $card = $this->batch($by);
        $before = CardPrint::toArray();

        $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.membership-card-patches.sheet.save', $card->id), [
                'print' => ['width' => 8.6, 'height' => 5.4, 'back_width' => 6, 'back_height' => 3.8],
                'h_lines' => [0.2, 0, -0.1], 'v_lines' => [0.3], 'pin_dx' => 0.5, 'pin_y' => 0.5,
            ])->assertSessionHasNoErrors();

        $p = CardPrint::toArray();
        $this->assertSame([8.6, 5.4, 6.0, 3.8], [$p['width'], $p['height'], $p['back_width'], $p['back_height']]);
        $this->assertSame([0.2, 0.0, -0.1], $p['sheet']['h_lines']);
        $this->assertSame([0.3], $p['sheet']['v_lines']);
        $this->assertSame(0.5, $p['sheet']['pin_dx']);
        $this->assertNotSame($before['width'], $p['width']);

        // The back can never be bigger than the front; junk sizes are ignored, not stored.
        $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.membership-card-patches.sheet.save', $card->id), ['print' => ['width' => 'abc']])->assertSessionHasErrors('print.width');   // a non-number is refused with a message
        $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.membership-card-patches.sheet.save', $card->id), ['print' => ['back_width' => 99]])->assertSessionHasNoErrors();
        $p = CardPrint::toArray();
        $this->assertSame(8.6, $p['width']);                                       // untouched by the refused one
        $this->assertLessThanOrEqual($p['width'], $p['back_width']);               // the back is never bigger than the front
    }
}
