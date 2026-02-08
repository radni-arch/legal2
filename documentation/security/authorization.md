# Authorization System

Comprehensive authorization system for AI Legal War Machine using Laravel's policy-based authorization.

## Table of Contents

- [Roles](#roles)
- [Policies](#policies)
- [Controller Authorization](#controller-authorization)
- [Usage Examples](#usage-examples)
- [Testing](#testing)
- [Architecture](#architecture)

---

## Roles

The system supports five distinct roles with hierarchical permissions:

### Role Hierarchy

```
Admin (Full Access)
  ↓
Lawyer (Case Management + Research)
  ↓
Assistant (Limited Case Access)
  ↓
Client (View Only - Own Cases)
  ↓
Viewer (Read Only - Public Data)
```

### Role Definitions

**Admin**
- Full access to all resources
- Can create/update/delete any content
- Can ingest laws and court decisions
- Can manage all users and teams
- Bypasses all authorization checks

**Lawyer**
- Can manage own cases and documents
- Can view all court decisions and laws (public data)
- Can create and run AI research agents
- Can analyze evidence and generate motions
- Can access all professional tools

**Assistant**
- Can view cases they're assigned to
- Can view documents for accessible cases
- Limited editing capabilities
- Cannot delete cases
- Cannot run agents independently

**Client**
- Can view only own cases
- Read-only access to case documents
- Cannot modify anything
- Cannot access professional tools

**Viewer**
- Read-only access to public data (laws, court decisions)
- Cannot view private cases
- Cannot access professional tools
- Useful for research purposes

---

## Policies

### BasePolicy

All policies extend `BasePolicy` which provides:

```php
protected function isAdmin(User $user): bool
protected function isLawyer(User $user): bool
public function before(User $user, string $ability): ?bool
```

**Admin Bypass**: The `before()` method automatically grants all permissions to admin users.

### LegalCasePolicy

**Case Access Permissions**:

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can view case lists |
| `view` | Owner, assigned users, or team members |
| `create` | Lawyers can create cases |
| `update` | Owner or assigned users |
| `delete` | Owner only |
| `forceDelete` | Admin only |
| `restore` | Owner or admin |

**Access Patterns**:

```php
// Owner access
$user->id === $case->user_id

// Assigned user access
$case->assignedUsers->contains($user)

// Team access
$user->team_id && $user->team_id === $case->team_id
```

### CaseDocumentPolicy

**Cascading Permissions**: Document permissions inherit from parent case permissions.

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can view document lists |
| `view` | Can view if can view parent case |
| `create` | Lawyers can create documents |
| `update` | Can update if can update parent case |
| `delete` | Can delete if can delete parent case |

**Example**:
```php
public function view(User $user, CaseDocument $document): bool
{
    return $user->can('view', $document->case);
}
```

### CourtDecisionPolicy

**Public Data Model**: Court decisions are public but admin-controlled.

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can view decision lists |
| `view` | Anyone can view (public data) |
| `create` | Admin only (data ingestion) |
| `update` | Admin only |
| `delete` | Admin only |

### LawPolicy

**Public Data Model**: Laws are public but admin-controlled.

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can view law lists |
| `view` | Anyone can view (public data) |
| `create` | Admin only (data ingestion) |
| `update` | Admin only |
| `delete` | Admin only |

### AgentRunPolicy

**AI Research Agent Control**:

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can monitor agents |
| `view` | Lawyers can view run details |
| `create` | Lawyers can trigger research |
| `update` | Admin only (system operations) |
| `delete` | Admin only |
| `cancel` | Lawyers can cancel running/paused agents |
| `resume` | Lawyers can resume paused agents |

**Lifecycle Controls**:
```php
public function cancel(User $user, AgentRun $run): bool
{
    return $this->isLawyer($user)
        && in_array($run->status, ['running', 'paused']);
}

public function resume(User $user, AgentRun $run): bool
{
    return $this->isLawyer($user) && $run->canBeResumed();
}
```

### TextractJobPolicy

**Cascading Case-Based Permissions**:

| Ability | Rule |
|---------|------|
| `viewAny` | Lawyers can view job lists |
| `view` | Cascade from case or lawyer access |
| `create` | Lawyers can upload PDFs |
| `update` | Cascade from case or lawyer access |
| `delete` | Cascade from case (owner only) or admin for orphaned jobs |
| `editContent` | Can edit if can update job |
| `retry` | Can retry failed jobs if can update |

**Jobs with Case Association**:
```php
if ($job->case_id && $job->case) {
    return $user->can('view', $job->case);
}
```

**Jobs without Case**:
- Lawyers can view/update
- Admin only can delete

---

## Controller Authorization

### Authorization Patterns

**Pattern 1: Direct Resource Authorization**
```php
// AgentController
public function deleteResearch(int $id)
{
    $run = AgentRun::findOrFail($id);
    $this->authorize('delete', $run);

    $run->delete();
}
```

**Pattern 2: Class-Based Authorization**
```php
// AgentController
public function startResearch(Request $request)
{
    $this->authorize('create', AgentRun::class);

    // Create agent run...
}
```

**Pattern 3: Cascading Authorization**
```php
// EvidenceController
public function analyzeEvidence(Request $request, string $caseId)
{
    $case = LegalCase::findOrFail($caseId);
    $this->authorize('view', $case);

    // Analyze evidence for this case...
}
```

### Controllers with Authorization

**AgentController** (7 methods):
- `startResearch()` - create
- `getResearch($id)` - view
- `listResearch()` - viewAny
- `deleteResearch($id)` - delete
- `dashboard()` - viewAny
- `viewRun($id)` - view

**EvidenceController** (6 methods):
- All methods load case and authorize against it
- Read operations: `authorize('view', $case)`
- Write operations: `authorize('update', $case)`

### Route Protection

All API routes are protected via middleware:

```php
// routes/api.php
Route::prefix('agent')->middleware('api.token')->group(function () {
    Route::post('research/start', [AgentController::class, 'startResearch']);
    // ... authorization enforced in controller
});
```

**Middleware Stack**:
1. `api.token` - API token authentication
2. `throttle:60,1` - Rate limiting (60 req/min)
3. Controller authorization via `$this->authorize()`

---

## Usage Examples

### In Controllers

```php
use App\Models\LegalCase;

class CaseController extends Controller
{
    public function show(LegalCase $case)
    {
        $this->authorize('view', $case);

        return view('cases.show', compact('case'));
    }

    public function update(Request $request, LegalCase $case)
    {
        $this->authorize('update', $case);

        $case->update($request->validated());

        return redirect()->back();
    }
}
```

### In Blade Views

```blade
@can('view', $case)
    <a href="{{ route('cases.show', $case) }}">View Case</a>
@endcan

@can('update', $case)
    <button>Edit Case</button>
@endcan

@can('delete', $case)
    <form method="POST" action="{{ route('cases.destroy', $case) }}">
        @csrf
        @method('DELETE')
        <button>Delete Case</button>
    </form>
@endcan
```

### In PHP Code

```php
// Check permission
if ($user->can('view', $case)) {
    $documents = $case->documents;
}

// Check role
if ($user->isLawyer()) {
    $runs = AgentRun::all();
}

if ($user->isAdmin()) {
    // Admin operations
}

// Throw exception if unauthorized
$user->authorize('delete', $case);
```

### In API Requests

```bash
# Authenticated request with API token
curl -X POST https://api.example.com/api/agent/research/start \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "objective": "Research evidence admissibility under Croatian law"
  }'

# Response if unauthorized (403 Forbidden)
{
  "message": "This action is unauthorized."
}
```

---

## Testing

### Policy Unit Tests

Located in `tests/Unit/Policies/`:

```php
use Tests\TestCase;
use App\Models\User;
use App\Models\LegalCase;
use App\Policies\LegalCasePolicy;

class LegalCasePolicyTest extends TestCase
{
    /** @test */
    public function owner_can_view_their_case()
    {
        $user = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $user->id]);

        $policy = new LegalCasePolicy();

        $this->assertTrue($policy->view($user, $case));
    }
}
```

### Integration Tests

Located in `tests/Feature/Authorization/`:

```php
use Tests\TestCase;
use App\Models\User;
use App\Models\LegalCase;

class ControllerAuthorizationTest extends TestCase
{
    /** @test */
    public function non_owner_cannot_analyze_evidence_for_case()
    {
        $owner = User::factory()->create(['role' => 'lawyer']);
        $other = User::factory()->create(['role' => 'lawyer']);
        $case = LegalCase::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)
            ->postJson("/api/evidence/analyze/{$case->id}", [
                'evidence' => [/*...*/]
            ]);

        $response->assertForbidden(); // 403
    }
}
```

### Test Coverage

**Policy Tests**: 62 tests
- LegalCasePolicy: 15 tests
- CaseDocumentPolicy: 12 tests
- PublicDataPolicy (Court Decisions + Laws): 24 tests
- AgentRunPolicy: 11 tests
- TextractJobPolicy: 14 tests

**Integration Tests**: 18 tests
- AgentController authorization: 6 tests
- EvidenceController authorization: 10 tests
- General authorization: 2 tests

---

## Architecture

### Policy Registration

**File**: `app/Providers/AuthServiceProvider.php`

```php
protected $policies = [
    LegalCase::class => LegalCasePolicy::class,
    CaseDocument::class => CaseDocumentPolicy::class,
    CourtDecision::class => CourtDecisionPolicy::class,
    Law::class => LawPolicy::class,
    AgentRun::class => AgentRunPolicy::class,
    TextractJob::class => TextractJobPolicy::class,
];
```

### HasRoles Trait

**File**: `app/Traits/HasRoles.php`

Provides role management methods for User model:

```php
trait HasRoles
{
    public function hasRole(string $role): bool
    public function hasAnyRole(array $roles): bool
    public function isAdmin(): bool
    public function isLawyer(): bool
    public function isAssistant(): bool
    public function scopeWithRole($query, string $role)
    public function scopeWithAnyRole($query, array $roles)
    public static function getAvailableRoles(): array
}
```

### Database Schema

**users table**:
```sql
role VARCHAR DEFAULT 'lawyer'
team_id BIGINT UNSIGNED NULLABLE
```

**cases table**:
```sql
user_id BIGINT UNSIGNED NULLABLE  -- Case owner
team_id BIGINT UNSIGNED NULLABLE  -- Team access
```

**case_user table** (pivot):
```sql
case_id VARCHAR  -- ULID
user_id BIGINT UNSIGNED
-- Enables assigned user access
```

### Authorization Flow

```
┌─────────────────────┐
│  HTTP Request       │
│  with API Token     │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Middleware         │
│  api.token          │
│  throttle           │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Controller         │
│  $this->authorize() │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Policy             │
│  BasePolicy::before │
│  (admin bypass)     │
└──────────┬──────────┘
           │
           ↓
┌─────────────────────┐
│  Policy Method      │
│  view/update/delete │
└──────────┬──────────┘
           │
           ├─ true  → Allow
           └─ false → 403 Forbidden
```

---

## Common Authorization Scenarios

### Scenario 1: Case Owner

```php
$lawyer = User::create(['role' => 'lawyer', 'email' => 'john@law.hr']);
$case = LegalCase::create(['user_id' => $lawyer->id]);

$lawyer->can('view', $case);    // true
$lawyer->can('update', $case);  // true
$lawyer->can('delete', $case);  // true
```

### Scenario 2: Assigned User

```php
$owner = User::create(['role' => 'lawyer']);
$assigned = User::create(['role' => 'assistant']);
$case = LegalCase::create(['user_id' => $owner->id]);
$case->assignedUsers()->attach($assigned->id);

$assigned->can('view', $case);    // true
$assigned->can('update', $case);  // true
$assigned->can('delete', $case);  // false (owner only)
```

### Scenario 3: Team Member

```php
$owner = User::create(['role' => 'lawyer', 'team_id' => 1]);
$teammate = User::create(['role' => 'lawyer', 'team_id' => 1]);
$case = LegalCase::create(['user_id' => $owner->id, 'team_id' => 1]);

$teammate->can('view', $case);    // true (team access)
$teammate->can('update', $case);  // false (not assigned)
$teammate->can('delete', $case);  // false (owner only)
```

### Scenario 4: Admin Override

```php
$admin = User::create(['role' => 'admin']);
$case = LegalCase::create(['user_id' => 999]); // Different owner

$admin->can('view', $case);    // true (admin bypass)
$admin->can('update', $case);  // true
$admin->can('delete', $case);  // true
$admin->can('forceDelete', $case); // true
```

---

## Security Best Practices

1. **Always authorize in controllers**: Never rely solely on middleware
2. **Use policies consistently**: Don't mix authorization logic
3. **Test authorization thoroughly**: Include edge cases and admin bypass
4. **Avoid role checks in controllers**: Use policies instead
5. **Document permission requirements**: Clear API documentation
6. **Log authorization failures**: Monitor for suspicious access attempts
7. **Review policies regularly**: Ensure they match business requirements

---

## Troubleshooting

### Common Issues

**Issue**: "This action is unauthorized" (403)

**Solution**: Check:
1. User has correct role (`$user->role`)
2. User is case owner or assigned (`$case->user_id`, `$case->assignedUsers`)
3. User is in same team (`$user->team_id === $case->team_id`)
4. Policy is registered in `AuthServiceProvider`
5. Controller has `$this->authorize()` call

**Issue**: Admin cannot perform action

**Solution**: Verify:
1. `$user->role === 'admin'`
2. `BasePolicy::before()` is returning `true`
3. Policy extends `BasePolicy`

**Issue**: Authorization not working in tests

**Solution**:
1. Use `actingAs($user)` to authenticate
2. Ensure policies are registered
3. Check user has correct role in factory

---

## Files and Locations

```
app/
├── Http/
│   └── Controllers/
│       ├── AgentController.php          (7 authorize calls)
│       └── EvidenceController.php       (6 authorize calls)
├── Models/
│   ├── User.php                         (HasRoles trait)
│   └── LegalCase.php                    (user/team relationships)
├── Policies/
│   ├── BasePolicy.php                   (foundation)
│   ├── LegalCasePolicy.php              (case permissions)
│   ├── CaseDocumentPolicy.php           (document permissions)
│   ├── CourtDecisionPolicy.php          (public data)
│   ├── LawPolicy.php                    (public data)
│   ├── AgentRunPolicy.php               (AI agents)
│   └── TextractJobPolicy.php            (OCR pipeline)
├── Providers/
│   └── AuthServiceProvider.php          (policy registration)
└── Traits/
    └── HasRoles.php                     (role management)

database/
└── migrations/
    ├── *_add_authorization_fields_to_users_table.php
    ├── *_add_authorization_fields_to_cases_table.php
    └── *_create_case_user_pivot_table.php

tests/
├── Unit/
│   └── Policies/
│       ├── LegalCasePolicyTest.php      (15 tests)
│       ├── CaseDocumentPolicyTest.php   (12 tests)
│       ├── PublicDataPolicyTest.php     (24 tests)
│       ├── AgentRunPolicyTest.php       (11 tests)
│       └── TextractJobPolicyTest.php    (14 tests)
└── Feature/
    └── Authorization/
        └── ControllerAuthorizationTest.php  (18 tests)

docs/
└── AUTHORIZATION.md                     (this file)
```

---

## Summary

The AI Legal War Machine authorization system provides:

- ✅ **7 Policies** covering all major resources
- ✅ **5 Roles** with hierarchical permissions
- ✅ **80 Tests** ensuring authorization correctness
- ✅ **13+ Controllers** with authorization enforcement
- ✅ **Admin Bypass** for system administration
- ✅ **Team & Assignment** based access control
- ✅ **Public Data** access for laws and court decisions
- ✅ **Lifecycle Controls** for AI agents
- ✅ **Cascading Permissions** for related resources

This system ensures that all sensitive operations are properly authorized while maintaining flexibility for different user roles and access patterns.
