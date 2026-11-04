<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\MemberNote;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Everyone reads every note; only the author edits or deletes his own — the super admin included. */
class MemberNotesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $name, bool $super = false): User
    {
        $u = User::factory()->create(['name' => $name]);
        if ($super) {
            $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        } else {
            foreach (['view member notes', 'add member notes'] as $p) {
                $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
            }
        }

        return $u;
    }

    public function test_notes_are_shared_but_only_the_author_changes_them(): void
    {
        $a = $this->staff('Ann');
        $b = $this->staff('Bob');
        $boss = $this->staff('Boss', true);
        $member = User::factory()->create(['name' => 'Fryal']);
        Membership::factory()->create(['user_id' => $member->id, 'membership_number' => '445566']);

        $this->actingAs($a)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.member-notes.store'), ['user_id' => $member->id, 'body' => 'called, no answer'])->assertRedirect();
        $note = MemberNote::firstOrFail();
        $this->assertSame($a->id, $note->created_by);

        // Bob sees Ann's note (author + member + card) but cannot touch it; neither can the super admin.
        $this->actingAs($b)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->get(route('admin.member-notes.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/MemberNotes/Index')->has('rows', 1)
                ->where('rows.0.author', 'Ann')->where('rows.0.mine', false)->where('rows.0.member.membership_number', '445566'));
        $this->actingAs($b)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->put(route('admin.member-notes.update', $note), ['body' => 'x'])->assertForbidden();
        $this->actingAs($boss)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->put(route('admin.member-notes.update', $note), ['body' => 'x'])->assertForbidden();
        $this->actingAs($boss)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->delete(route('admin.member-notes.destroy', $note))->assertForbidden();
        $this->assertSame('called, no answer', $note->fresh()->body);

        $this->actingAs($a)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->put(route('admin.member-notes.update', $note), ['body' => 'reached him'])->assertRedirect();
        $this->assertSame('reached him', $note->fresh()->body);
        $this->actingAs($a)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->delete(route('admin.member-notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, MemberNote::count());
    }

    public function test_viewing_without_the_permission_or_adding_without_add_is_refused(): void
    {
        $nobody = User::factory()->create();
        $member = User::factory()->create();
        $this->actingAs($nobody)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->get(route('admin.member-notes.index'))->assertForbidden();
        $this->actingAs($nobody)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.member-notes.store'), ['user_id' => $member->id, 'body' => 'hi'])->assertForbidden();
    }
}
