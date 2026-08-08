<?php

namespace Tests\Feature\Admin\User\Membership;

use App\Models\User;
use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreMembershipTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create();
    }

    /** @test */
    public function it_can_create_a_member_with_valid_data(): void
    {
        $membershipData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'membership_number' => 'MEM-12345',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertRedirect(route('admin.user.membership.list'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('memberships', [
            'membership_number' => 'MEM-12345',
        ]);
    }

    /** @test */
    public function it_can_create_a_member_with_avatar(): void
    {
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $membershipData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'membership_number' => 'MEM-67890',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
            'avatar' => $avatar,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertRedirect(route('admin.user.membership.list'));

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->avatar);
    }

    /** @test */
    public function it_can_create_a_member_with_family_members(): void
    {
        $photo = UploadedFile::fake()->image('family.jpg');

        $membershipData = [
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'membership_number' => 'MEM-11111',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
            'family_members' => [
                [
                    'name' => 'Alice Smith',
                    'relationship' => 'wife',
                    'date_of_birth' => '1990-05-15',
                    'phone' => '+1234567890',
                    'email' => 'alice@example.com',
                    'is_active' => true,
                    'photo' => $photo,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertRedirect(route('admin.user.membership.list'));

        $user = User::where('email', 'bob@example.com')->first();
        $this->assertNotNull($user);
        $this->assertCount(1, $user->membership->familyMembers);
        $this->assertEquals('Alice Smith', $user->membership->familyMembers->first()->name);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'registration_date', 'expiration_date']);
    }

    /** @test */
    public function it_validates_email_uniqueness(): void
    {
        // Create a user first
        User::factory()->create(['email' => 'existing@example.com']);

        $membershipData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'membership_number' => 'MEM-99999',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function it_validates_membership_number_uniqueness(): void
    {
        // Create a membership first
        $user = User::factory()->create();
        Membership::factory()->create([
            'user_id' => $user->id,
            'membership_number' => 'EXISTING-123',
        ]);

        $membershipData = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'membership_number' => 'EXISTING-123',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertSessionHasErrors(['membership_number']);
    }

    /** @test */
    public function it_validates_expiration_date_is_after_registration_date(): void
    {
        $membershipData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'membership_number' => 'MEM-88888',
            'registration_date' => '2025-01-01',
            'expiration_date' => '2024-01-01', // Before registration date
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertSessionHasErrors(['expiration_date']);
    }

    /** @test */
    public function it_validates_family_member_required_fields(): void
    {
        $membershipData = [
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'membership_number' => 'MEM-77777',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
            'family_members' => [
                [
                    // Missing name and relationship
                    'date_of_birth' => '1990-05-15',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertSessionHasErrors([
            'family_members.0.name',
            'family_members.0.relationship',
        ]);
    }

    /** @test */
    public function it_validates_family_member_relationship(): void
    {
        $membershipData = [
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'membership_number' => 'MEM-66666',
            'registration_date' => '2024-01-01',
            'expiration_date' => '2025-01-01',
            'is_active' => true,
            'family_members' => [
                [
                    'name' => 'Child Name',
                    'relationship' => 'invalid-relationship',
                    'is_active' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.user.membership.store'), $membershipData);

        $response->assertSessionHasErrors(['family_members.0.relationship']);
    }
}
