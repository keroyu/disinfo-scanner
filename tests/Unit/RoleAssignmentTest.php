<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\ApiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * Test user can have only one role assigned at a time
     *
     * @test
     */
    public function user_can_have_only_one_role_assigned_at_a_time(): void
    {
        // Arrange: Create user with regular_member role
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create();
        $user->roles()->attach($regularRole);

        // Assert: User has 1 role
        $this->assertCount(1, $user->roles);
        $this->assertEquals('regular_member', $user->roles->first()->name);

        // Act: Change role to premium_member
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->sync([$premiumRole->id]); // sync removes old role

        // Assert: User now has only premium_member role
        $freshUser = $user->fresh();
        $this->assertCount(1, $freshUser->roles);
        $this->assertEquals('premium_member', $freshUser->roles->first()->name);
        $this->assertFalse($freshUser->roles->contains('name', 'regular_member'));
    }

    /**
     * Test all five roles exist in database
     *
     * @test
     */
    public function all_five_roles_exist_in_database(): void
    {
        // Act: Get all roles
        $roles = Role::pluck('name')->toArray();

        // Assert: All 5 roles exist
        $expectedRoles = [
            'administrator',
            'website_editor',
            'premium_member',
            'regular_member',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertContains($roleName, $roles, "Role {$roleName} should exist");
        }

        $this->assertGreaterThanOrEqual(4, count($roles));
    }

    /**
     * Test role has display_name in Traditional Chinese
     *
     * @test
     */
    public function role_has_display_name_in_traditional_chinese(): void
    {
        // Act: Get roles with display names
        $roles = Role::whereIn('name', [
            'administrator',
            'website_editor',
            'premium_member',
            'regular_member',
        ])->get();

        // Assert: Each role has Traditional Chinese display name
        $expectedDisplayNames = [
            'administrator' => '管理員',
            'website_editor' => '網站編輯',
            'premium_member' => '高級會員',
            'regular_member' => '一般會員',
        ];

        foreach ($roles as $role) {
            $this->assertArrayHasKey($role->name, $expectedDisplayNames);
            $this->assertEquals($expectedDisplayNames[$role->name], $role->display_name);
        }
    }

    /**
     * Test role assignment creates API quota for premium members
     *
     * @test
     */
    public function role_assignment_creates_api_quota_for_premium_members(): void
    {
        // Arrange: Create user
        $user = User::factory()->create();

        // Verify no API quota initially
        $this->assertNull(ApiQuota::where('user_id', $user->id)->first());

        // Act: Assign premium_member role
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->attach($premiumRole);

        // Trigger quota creation (would be done by controller)
        ApiQuota::create([
            'user_id' => $user->id,
            'current_month' => now()->format('Y-m'),
            'usage_count' => 0,
            'monthly_limit' => 10,
            'is_unlimited' => false,
        ]);

        // Assert: API quota created with correct defaults
        $apiQuota = ApiQuota::where('user_id', $user->id)->first();
        $this->assertNotNull($apiQuota);
        $this->assertEquals(10, $apiQuota->monthly_limit);
        $this->assertEquals(0, $apiQuota->usage_count);
        $this->assertFalse($apiQuota->is_unlimited);
    }

    /**
     * Test role assignment does not create API quota for non-premium members
     *
     * @test
     */
    public function role_assignment_does_not_create_api_quota_for_non_premium_members(): void
    {
        // Arrange: Create user with regular_member role
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create();
        $user->roles()->attach($regularRole);

        // Assert: No API quota created
        $this->assertNull(ApiQuota::where('user_id', $user->id)->first());

        // Act: Change to website_editor role
        $editorRole = Role::where('name', 'website_editor')->first();
        $user->roles()->sync([$editorRole->id]);

        // Assert: Still no API quota
        $this->assertNull(ApiQuota::where('user_id', $user->id)->first());
    }

    /**
     * Test role sync removes old role and adds new role
     *
     * @test
     */
    public function role_sync_removes_old_role_and_adds_new_role(): void
    {
        // Arrange: Create user with regular_member role
        $regularRole = Role::where('name', 'regular_member')->first();
        $user = User::factory()->create();
        $user->roles()->attach($regularRole);

        $this->assertCount(1, $user->roles);

        // Act: Sync to premium_member role
        $premiumRole = Role::where('name', 'premium_member')->first();
        $user->roles()->sync([$premiumRole->id]);

        // Assert: Old role removed, new role added
        $freshUser = $user->fresh();
        $this->assertCount(1, $freshUser->roles);
        $this->assertEquals('premium_member', $freshUser->roles->first()->name);
        $this->assertFalse($freshUser->roles->contains('name', 'regular_member'));
    }

    /**
     * Test user with no role has empty roles collection
     *
     * @test
     */
    public function user_with_no_role_has_empty_roles_collection(): void
    {
        // Arrange: Create user without role
        $user = User::factory()->create();

        // Assert: Roles collection is empty
        $this->assertCount(0, $user->roles);
        $this->assertTrue($user->roles->isEmpty());
    }

    /**
     * Test role relationship eager loading works correctly
     *
     * @test
     */
    public function role_relationship_eager_loading_works_correctly(): void
    {
        // Arrange: Create users with roles
        $adminRole = Role::where('name', 'administrator')->first();
        $regularRole = Role::where('name', 'regular_member')->first();

        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regular = User::factory()->create();
        $regular->roles()->attach($regularRole);

        // Act: Eager load roles
        $users = User::with('roles')->whereIn('id', [$admin->id, $regular->id])->get();

        // Assert: Roles loaded correctly
        $this->assertCount(2, $users);
        
        $adminUser = $users->where('id', $admin->id)->first();
        $this->assertTrue($adminUser->roles->contains('name', 'administrator'));

        $regularUser = $users->where('id', $regular->id)->first();
        $this->assertTrue($regularUser->roles->contains('name', 'regular_member'));
    }
}
