<?php

namespace Tests\Feature\Authorization;

use App\Models\DocumentGenerationRun;
use App\Models\User;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentGenerationAuditPolicyTest extends TestCase
{
    use UsesTestDatabase;

    public function test_owner_can_view_audit(): void
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $run = DocumentGenerationRun::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($owner->can('viewAudit', $run));
    }

    public function test_admin_can_view_any_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $run = DocumentGenerationRun::factory()->create();

        $this->assertTrue($admin->can('viewAudit', $run));
    }

    public function test_user_cannot_view_other_users_audit(): void
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $run = DocumentGenerationRun::factory()->create();

        $this->assertFalse($user->can('viewAudit', $run));
    }
}
