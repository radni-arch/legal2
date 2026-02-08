<?php

namespace Tests\Unit\Policies;

use App\Models\LegalCase;
use App\Models\TextractJob;
use App\Models\User;
use App\Policies\TextractJobPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TextractJobPolicyTest extends TestCase
{
    protected TextractJobPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TextractJobPolicy;

        // Register LegalCasePolicy for cascading permissions
        Gate::policy(\App\Models\LegalCase::class, \App\Policies\LegalCasePolicy::class);
    }

    /**
     * Create a User instance in memory (no database) with the given role and id.
     */
    protected function makeUser(string $role = 'lawyer', int $id = null): User
    {
        static $nextId = 1;
        $user = User::factory()->make([
            'role' => $role,
        ]);
        $user->id = $id ?? $nextId++;

        return $user;
    }

    /**
     * Create a LegalCase instance in memory with the given owner id.
     */
    protected function makeCase(int $userId, int $id = null): LegalCase
    {
        static $nextId = 1;
        $case = LegalCase::factory()->make([
            'user_id' => $userId,
        ]);
        $case->id = $id ?? $nextId++;
        // Preload empty assignedUsers to avoid DB queries
        $case->setRelation('assignedUsers', new Collection());

        return $case;
    }

    /**
     * Create a TextractJob instance in memory with optional case association.
     */
    protected function makeJob(?int $caseId = null, ?LegalCase $case = null, array $extra = []): TextractJob
    {
        static $nextId = 1;
        $attrs = array_merge(['case_id' => $caseId], $extra);
        $job = TextractJob::factory()->make($attrs);
        $job->id = $nextId++;

        if ($case) {
            $job->setRelation('case', $case);
        }

        return $job;
    }

    /** @test */
    public function lawyers_can_view_any_textract_jobs(): void
    {
        $lawyer = $this->makeUser('lawyer');

        $this->assertTrue($this->policy->viewAny($lawyer));
    }

    /** @test */
    public function can_view_job_if_can_view_its_case(): void
    {
        $user = $this->makeUser('lawyer', 10);
        $case = $this->makeCase($user->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertTrue($this->policy->view($user, $job));
    }

    /** @test */
    public function cannot_view_job_if_cannot_view_its_case(): void
    {
        $owner = $this->makeUser('lawyer', 20);
        $other = $this->makeUser('lawyer', 21);
        $case = $this->makeCase($owner->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertFalse($this->policy->view($other, $job));
    }

    /** @test */
    public function lawyers_can_view_jobs_without_case_association(): void
    {
        $lawyer = $this->makeUser('lawyer');
        $job = $this->makeJob(null);

        $this->assertTrue($this->policy->view($lawyer, $job));
    }

    /** @test */
    public function lawyers_can_create_textract_jobs(): void
    {
        $lawyer = $this->makeUser('lawyer');

        $this->assertTrue($this->policy->create($lawyer));
    }

    /** @test */
    public function can_update_job_if_can_update_its_case(): void
    {
        $user = $this->makeUser('lawyer', 30);
        $case = $this->makeCase($user->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertTrue($this->policy->update($user, $job));
    }

    /** @test */
    public function cannot_update_job_if_cannot_update_its_case(): void
    {
        $owner = $this->makeUser('lawyer', 40);
        $other = $this->makeUser('lawyer', 41);
        $case = $this->makeCase($owner->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertFalse($this->policy->update($other, $job));
    }

    /** @test */
    public function lawyers_can_update_jobs_without_case_association(): void
    {
        $lawyer = $this->makeUser('lawyer');
        $job = $this->makeJob(null);

        $this->assertTrue($this->policy->update($lawyer, $job));
    }

    /** @test */
    public function can_delete_job_if_can_delete_its_case(): void
    {
        $user = $this->makeUser('lawyer', 50);
        $case = $this->makeCase($user->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertTrue($this->policy->delete($user, $job));
    }

    /** @test */
    public function cannot_delete_job_if_cannot_delete_its_case(): void
    {
        $owner = $this->makeUser('lawyer', 60);
        $other = $this->makeUser('lawyer', 61);
        $case = $this->makeCase($owner->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertFalse($this->policy->delete($other, $job));
    }

    /** @test */
    public function only_admin_can_delete_jobs_without_case_association(): void
    {
        $admin = $this->makeUser('admin', 70);
        $lawyer = $this->makeUser('lawyer', 71);
        $job = $this->makeJob(null);

        $this->assertTrue($this->policy->delete($admin, $job));
        $this->assertFalse($this->policy->delete($lawyer, $job));
    }

    /** @test */
    public function can_edit_content_if_can_update_job(): void
    {
        $user = $this->makeUser('lawyer', 80);
        $case = $this->makeCase($user->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertTrue($this->policy->editContent($user, $job));
    }

    /** @test */
    public function can_retry_failed_jobs_if_can_update(): void
    {
        $user = $this->makeUser('lawyer', 90);
        $case = $this->makeCase($user->id);
        $failedJob = $this->makeJob($case->id, $case, ['status' => 'failed']);
        $succeededJob = $this->makeJob($case->id, $case, ['status' => 'succeeded']);

        $this->assertTrue($this->policy->retry($user, $failedJob));
        $this->assertFalse($this->policy->retry($user, $succeededJob));
    }

    /** @test */
    public function admin_can_perform_all_actions(): void
    {
        $admin = $this->makeUser('admin', 100);
        $lawyer = $this->makeUser('lawyer', 101);
        $case = $this->makeCase($lawyer->id);
        $job = $this->makeJob($case->id, $case);

        $this->assertTrue($this->policy->view($admin, $job));
        $this->assertTrue($this->policy->update($admin, $job));
        $this->assertTrue($this->policy->delete($admin, $job));
        $this->assertTrue($this->policy->editContent($admin, $job));
    }
}
