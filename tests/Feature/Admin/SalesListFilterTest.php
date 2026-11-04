<?php

namespace Tests\Feature\Admin;

use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** /admin/sales filters: linked admin (yes/no/which one), search by admin, sort, and the admin on the show page. */
class SalesListFilterTest extends TestCase
{
    use RefreshDatabase;

    private function ids($res): array
    {
        return collect($res->viewData('page')['props']['sales']['data'])->pluck('id')->all();
    }

    public function test_filters_by_linked_admin(): void
    {
        $me = User::factory()->create();
        $me->givePermissionTo(Permission::findOrCreate('manage sales', 'web'));
        $ann = User::factory()->create(['name' => 'Ann Admin', 'email' => 'ann@example.com']);
        $linked = Sales::create(['name' => ['ar' => 'مربوط', 'en' => 'Linked'], 'admin_user_id' => $ann->id]);
        $free = Sales::create(['name' => ['ar' => 'حر', 'en' => 'Free']]);

        $get = fn (array $q) => $this->actingAs($me)->get(route('admin.sales.list', $q))->assertOk();

        $this->assertEqualsCanonicalizing([$linked->id, $free->id], $this->ids($get([])));
        $this->assertSame([$linked->id], $this->ids($get(['admin' => 'yes'])));
        $this->assertSame([$free->id], $this->ids($get(['admin' => 'no'])));
        $this->assertSame([$linked->id], $this->ids($get(['admin_id' => $ann->id])));
        $this->assertSame([$linked->id], $this->ids($get(['search' => 'ann@example'])));
        $this->assertSame([$free->id], $this->ids($get(['has_image' => 'no', 'admin' => 'no'])));

        $this->actingAs($me)->get(route('admin.sales.show', $linked->id))->assertOk()
            ->assertSee('Ann Admin');
    }

    public function test_the_edit_page_always_lists_the_admin_already_linked(): void
    {
        $me = User::factory()->create();
        $me->givePermissionTo(Permission::findOrCreate('manage sales', 'web'));
        // An account with NO role at all, linked to the rep: it must still be in the select's options so its name shows.
        $ghost = User::factory()->create(['name' => 'مصطفى']);
        $sale = Sales::create(['name' => ['ar' => 'س', 'en' => 'S'], 'admin_user_id' => $ghost->id]);

        $options = $this->actingAs($me)->get(route('admin.sales.edit', $sale->id))->assertOk()->viewData('page')['props']['adminOptions'];

        $row = collect($options)->firstWhere('value', $ghost->id);
        $this->assertNotNull($row);
        $this->assertStringStartsWith('مصطفى', $row['label']);                    // the name is intact (no cut-off letter)
    }
}
