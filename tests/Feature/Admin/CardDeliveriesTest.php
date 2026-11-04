<?php

namespace Tests\Feature\Admin;

use App\Models\CardDelivery;
use App\Models\DeliveryCourier;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** A member can have several card hand-overs, each with a courier, photos and notes; one table lists them all. */
class CardDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(bool $manage): User
    {
        $u = User::factory()->create(['name' => $manage ? 'Maker' : 'Reader']);
        $u->givePermissionTo(Permission::findOrCreate('view card deliveries', 'web'));
        if ($manage) {
            $u->givePermissionTo(Permission::findOrCreate('manage card deliveries', 'web'));
        }

        return $u;
    }

    public function test_record_edit_note_and_list_deliveries(): void
    {
        Storage::fake('public');
        $maker = $this->staff(true);
        $reader = $this->staff(false);
        $member = User::factory()->create(['name' => 'Fryal']);
        Membership::factory()->create(['user_id' => $member->id, 'membership_number' => '778899']);
        $courier = DeliveryCourier::create(['name' => 'Hassan', 'phone' => '0100', 'created_by' => $maker->id]);

        // two separate hand-overs for the same member
        foreach ([1, 2] as $n) {
            $this->actingAs($maker)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.card-deliveries.store'), [
                'user_id' => $member->id, 'courier_id' => $courier->id, 'status' => 'delivered', 'cards_count' => $n,
                'delivered_at' => '2026-11-0'.$n, 'notes' => "round {$n}", 'photos' => [UploadedFile::fake()->image("c{$n}.jpg")],
            ])->assertRedirect();
        }
        $this->assertSame(2, CardDelivery::where('user_id', $member->id)->count());
        $first = CardDelivery::orderBy('id')->first();
        $this->assertCount(1, $first->getMedia(CardDelivery::PHOTOS));
        $this->assertSame($maker->id, $first->created_by);

        $this->actingAs($maker)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.card-deliveries.update', $first), ['status' => 'failed', 'cards_count' => 1, 'courier_id' => $courier->id, 'notes' => 'nobody home'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('failed', $first->fresh()->status);
        $this->actingAs($maker)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.card-deliveries.notes.store', $first), ['body' => 'will retry tomorrow'])->assertRedirect();
        $this->assertSame('Maker', $first->fresh()->noteLines()->first()->author->name);

        $this->actingAs($reader)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->get(route('admin.card-deliveries.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/CardDeliveries/Index')->has('rows', 2)->where('canManage', false)
                ->where('rows.1.member.membership_number', '778899')->where('rows.1.courier.name', 'Hassan')->where('rows.1.note_lines.0.body', 'will retry tomorrow'));
        $this->actingAs($reader)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->get(route('admin.card-deliveries.index', ['status' => 'failed']))->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    }

    public function test_reader_cannot_write_and_courier_with_history_is_not_deleted(): void
    {
        $reader = $this->staff(false);
        $maker = $this->staff(true);
        $member = User::factory()->create();
        $courier = DeliveryCourier::create(['name' => 'Hassan']);
        $this->actingAs($reader)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.card-deliveries.store'), ['user_id' => $member->id, 'status' => 'delivered', 'cards_count' => 1])->assertForbidden();
        $this->actingAs($reader)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.delivery-couriers.store'), ['name' => 'X'])->assertForbidden();

        CardDelivery::create(['user_id' => $member->id, 'courier_id' => $courier->id, 'status' => 'delivered', 'cards_count' => 1]);
        $this->actingAs($maker)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->delete(route('admin.delivery-couriers.destroy', $courier))->assertRedirect();
        $this->assertNotNull($courier->fresh());
    }
}
