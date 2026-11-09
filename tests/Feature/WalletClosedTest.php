<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Owner decision 2026-11-09: no paying money into the wallet and no points → wallet. Nothing moves, whatever the admin settings say. */
class WalletClosedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function topup_and_convert_are_closed_and_move_nothing(): void
    {
        $user = User::factory()->create();
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed', 'points' => 5000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/topup', ['amount' => 100])->assertStatus(410)->assertJsonPath('reason', 'disabled');
        $this->postJson('/api/v1/loyalty/convert', ['egp' => 20, 'id' => 'a'])->assertStatus(410)->assertJsonPath('reason', 'disabled');

        $this->assertSame(0.0, WalletTransaction::balanceOf($user->id));
        $this->assertSame(5000, LoyaltyTransaction::balanceOf($user->id));
    }

    /** @test */
    public function the_summary_tells_clients_both_are_closed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/loyalty')->assertOk()
            ->assertJsonPath('data.wallet_enabled', false)
            ->assertJsonPath('data.topup_enabled', false);
    }
}
