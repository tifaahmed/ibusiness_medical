<?php

namespace Tests\Feature\Admin;

use App\Enums\Contact\ContactLogActionEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Enums\User\UserPermissionEnum;
use App\Models\ContactMessage;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The enquiry inbox in the admin: who may open it, who may change one, and
 * that every change leaves a trail.
 *
 * The read side deliberately admits the viewer role's `view contact messages`,
 * so "can open it" and "can change it" are two different questions throughout.
 */
class ContactMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissions
     */
    private function adminWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(implode('+', $permissions).'-role', 'web');

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user->assignRole($role);

        return $user;
    }

    private function enquiry(array $overrides = []): ContactMessage
    {
        return ContactMessage::create([
            'phone' => '01000000000',
            'message' => 'Please call me about the family card.',
            'source' => ContactSourceEnum::CONTACT_FORM->value,
            'status' => ContactStatusEnum::NEW->value,
            ...$overrides,
        ]);
    }

    public function test_the_inbox_is_closed_to_an_admin_with_no_contact_permission(): void
    {
        /* `manage products` gets them into the admin area and no further. */
        $user = $this->adminWith([UserPermissionEnum::MANAGE_PRODUCTS]);

        $this->actingAs($user)->get(route('admin.contact-messages.index'))->assertForbidden();
        $this->actingAs($user)
            ->get(route('admin.contact-messages.show', $this->enquiry()))
            ->assertForbidden();
    }

    public function test_view_permission_opens_the_inbox_but_grants_no_changes(): void
    {
        $user = $this->adminWith([UserPermissionEnum::VIEW_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->get(route('admin.contact-messages.index'))
            ->assertOk()
            /* The page is told, so it hides the Save and Delete controls
               rather than offering buttons that can only answer 403. */
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($user)
            ->get(route('admin.contact-messages.show', $enquiry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => ContactStatusEnum::CLOSED->value])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.contact-messages.destroy', $enquiry))
            ->assertForbidden();

        $this->assertSame(ContactStatusEnum::NEW, $enquiry->fresh()->status);
    }

    public function test_the_dedicated_manage_permission_grants_the_writes(): void
    {
        /* The permission existed on the role screen but was enforced nowhere
           until now — this is the test that says it means something. */
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)->get(route('admin.contact-messages.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManage', true));

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => ContactStatusEnum::IN_PROGRESS->value])
            ->assertRedirect();

        $this->assertSame(ContactStatusEnum::IN_PROGRESS, $enquiry->fresh()->status);
    }

    public function test_manage_memberships_still_reaches_the_inbox(): void
    {
        /* Kept deliberately: the admins who could work the inbox before it had
           a permission of its own must not lose it. */
        $user = $this->adminWith([UserPermissionEnum::MANAGE_MEMBERSHIPS]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => ContactStatusEnum::RESOLVED->value])
            ->assertRedirect();

        $this->assertSame(ContactStatusEnum::RESOLVED, $enquiry->fresh()->status);
    }

    public function test_a_status_change_is_logged_and_stamps_the_reply_date(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => ContactStatusEnum::RESOLVED->value])
            ->assertRedirect();

        $log = $enquiry->logs()->where('action', ContactLogActionEnum::STATUS_CHANGED)->sole();

        $this->assertSame(ContactStatusEnum::NEW->value, $log->old_value);
        $this->assertSame(ContactStatusEnum::RESOLVED->value, $log->new_value);
        $this->assertSame($user->id, $log->admin_id);
        $this->assertNotNull($enquiry->fresh()->replied_at);
    }

    public function test_assigning_a_salesperson_is_logged_by_name(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();
        $sales = Sales::create(['name' => ['en' => 'Mona Adel', 'ar' => 'منى عادل']]);

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['sales_id' => $sales->id])
            ->assertRedirect();

        $this->assertSame($sales->id, $enquiry->fresh()->sales_id);

        $log = $enquiry->logs()->where('action', ContactLogActionEnum::SALES_ASSIGNED)->sole();

        /* By NAME, so the trail still reads correctly once that salesperson is
           deleted — unlike the status, which is logged by value. */
        $this->assertSame('Mona Adel', $log->new_value);
        $this->assertNull($log->old_value);
    }

    public function test_a_note_is_logged_with_what_it_replaced(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry(['admin_notes' => 'Called once, no answer.']);

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['admin_notes' => 'Called twice, no answer.'])
            ->assertRedirect();

        $log = $enquiry->logs()->where('action', ContactLogActionEnum::NOTE_UPDATED)->sole();

        $this->assertSame('Called once, no answer.', $log->old_value);
        $this->assertSame('Called twice, no answer.', $log->new_value);
    }

    public function test_a_change_that_changes_nothing_records_nothing(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => ContactStatusEnum::NEW->value])
            ->assertRedirect();

        /* Only the opening `received` entry: re-saving the same status must
           not bury the real changes under a column of noise. */
        $this->assertSame(0, $enquiry->logs()->where('action', ContactLogActionEnum::STATUS_CHANGED)->count());
    }

    public function test_opening_an_enquiry_stamps_it_read_without_moving_the_status(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)->get(route('admin.contact-messages.show', $enquiry))->assertOk();

        $enquiry->refresh();

        $this->assertNotNull($enquiry->read_at);
        /* An enquiry somebody glanced at is still new work until it is picked
           up — reading is not a pipeline stage. */
        $this->assertSame(ContactStatusEnum::NEW, $enquiry->status);
    }

    public function test_an_enquiry_can_be_deleted(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->delete(route('admin.contact-messages.destroy', $enquiry))
            ->assertRedirect(route('admin.contact-messages.index'));

        $this->assertSoftDeleted($enquiry);
    }

    public function test_several_can_be_moved_at_once_and_each_is_logged(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $first = $this->enquiry();
        $second = $this->enquiry(['phone' => '01111111111']);

        $this->actingAs($user)
            ->postJson(route('admin.contact-messages.bulk-update'), [
                'ids' => [$first->id, $second->id],
                'action' => ContactStatusEnum::CLOSED->value,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(ContactStatusEnum::CLOSED, $first->fresh()->status);
        $this->assertSame(ContactStatusEnum::CLOSED, $second->fresh()->status);

        /* A bulk change leaves the same trail as the individual ones would. */
        $this->assertSame(1, $first->logs()->where('action', ContactLogActionEnum::STATUS_CHANGED)->count());
        $this->assertSame(1, $second->logs()->where('action', ContactLogActionEnum::STATUS_CHANGED)->count());
    }

    public function test_the_list_filters_by_status_and_by_source(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $this->enquiry(['source' => ContactSourceEnum::JOIN_REQUEST->value, 'commercial_register' => 'CR-1']);
        $this->enquiry(['phone' => '01222222222', 'status' => ContactStatusEnum::CLOSED->value]);

        $this->actingAs($user)
            ->get(route('admin.contact-messages.index', ['source' => ContactSourceEnum::JOIN_REQUEST->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages.data', 1)
                ->where('messages.data.0.commercial_register', 'CR-1')
            );

        $this->actingAs($user)
            ->get(route('admin.contact-messages.index', ['status' => ContactStatusEnum::CLOSED->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('messages.data', 1));
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $user = $this->adminWith([UserPermissionEnum::MANAGE_CONTACT_MESSAGES]);
        $enquiry = $this->enquiry();

        $this->actingAs($user)
            ->put(route('admin.contact-messages.update', $enquiry), ['status' => 'archived'])
            ->assertSessionHasErrors('status');
    }
}
