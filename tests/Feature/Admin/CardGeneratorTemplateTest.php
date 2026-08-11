<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\CardLayout;
use App\Models\CardTemplate;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The card generator and the batch-create page both draw on the card templates,
 * picking between them by whether the card carries a partner. Both therefore
 * have to be handed both designs.
 */
class CardGeneratorTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            [
                'manage memberships',
                'manage card templates',
                'create membership card patches',
                'view membership card patches',
            ],
        ));
        $user->assignRole($role);

        return $user;
    }

    private function seedTemplates(): void
    {
        CardTemplate::create([
            'name' => ['en' => 'Partner design', 'ar' => 'تصميم الشريك'],
            'status' => 'with_partner',
            'card_empty' => 'images/cards/deilar-card-blank.png',
        ]);
        CardTemplate::create([
            'name' => ['en' => 'Plain design', 'ar' => 'تصميم بسيط'],
            'status' => 'no_partner',
            'card_empty' => 'images/cards/deilar-card-blank.png',
        ]);
    }

    public function test_card_generator_receives_both_designs(): void
    {
        $this->seedTemplates();

        $this->actingAs($this->admin())
            ->get('/admin/card-generator?policy=7446')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CardGenerator/Index')
                ->where('templates.with_partner.status', 'with_partner')
                ->where('templates.no_partner.status', 'no_partner')
                // The renderer needs the artwork and the field boxes.
                ->where('templates.with_partner.card_empty_url', '/images/cards/deilar-card-blank.png')
                ->has('templates.with_partner.layout.partner_logo')
                ->where('templates.no_partner.hidden_fields', ['partner_logo'])
                ->where('initial.policy', '7446'));
    }

    public function test_batch_create_receives_both_designs(): void
    {
        $this->seedTemplates();

        $this->actingAs($this->admin())
            ->get('/admin/membership-card-patches/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/MembershipCard/Create')
                ->where('cardTemplates.with_partner.status', 'with_partner')
                ->where('cardTemplates.no_partner.status', 'no_partner'));
    }

    public function test_missing_designs_come_back_as_nulls_rather_than_missing_keys(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/card-generator')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('templates.with_partner', null)
                ->where('templates.no_partner', null));
    }

    public function test_a_generated_card_stores_its_own_layout_and_values(): void
    {
        $this->seedTemplates();
        $template = CardTemplate::where('status', 'with_partner')->first();

        $membership = Membership::factory()->create(['membership_number' => 'MEM-9001']);

        $layout = ['slogan' => ['x' => 0.11, 'y' => 0.51, 'width' => 0.23, 'height' => 0.05, 'font_size' => 22]];
        $fieldValues = ['slogan' => 'One card only', 'phone' => '01000000000'];

        $this->actingAs($this->admin())
            ->postJson('/api/memberships/MEM-9001/card-layout', [
                'card_template_id' => $template->id,
                'layout' => $layout,
                'field_values' => $fieldValues,
            ])
            ->assertOk();

        $saved = CardLayout::where('membership_id', $membership->id)->firstOrFail();

        $this->assertSame($template->id, $saved->card_template_id);
        // MySQL JSON columns normalise key order, so compare by content.
        $this->assertEquals($layout, $saved->layout);
        $this->assertEquals($fieldValues, $saved->field_values);
    }
}
