# Intensive Hardening & Monitoring Guide

**Status**: 2/15 services hardened ✅ | 13/15 remaining ⏳

**Completion Date**: Started 2025-11-09

---

## Progress Tracker

### ✅ Completed Services (2/15)

1. **SearchExecutorService** (412 lines) - ✅ COMPLETE
   - Methods hardened: `execute()`, `executeAction()`, `searchCorpus()`
   - Commit: `deb937c`

2. **QualityAssessorService** (430 lines) - ✅ PARTIAL (1/6 methods)
   - Methods hardened: `assess()`
   - Remaining: `isComplete()`, `evaluateIteration()`, `synthesizeFinalOutput()`, `setQualityThreshold()`, `setCompletenessThreshold()`
   - Commit: `025859d`

### ⏳ Remaining Services (13/15)

3. **LegalReasoning/ConflictResolver** (437 lines)
4. **Collaboration/LegalTeamOrchestrator** (440 lines)
5. **LegalReasoning/CitationAnalyzer** (450 lines)
6. **GraphQueryHelper** (461 lines)
7. **VectorStoreManagementService** (464 lines)
8. **ConfigValidator** (466 lines)
9. **AI/OpenAIAnalysisService** (478 lines)
10. **LegalReasoning/ImpactAnalyzer** (505 lines)
11. **LegalReasoning/StrategyBuilder** (511 lines)
12. **LegalReasoning/ArgumentGenerator** (516 lines)
13. **EoglasnaService** (540 lines)
14. **CaseVectorStoreService** (616 lines)
15. **LegalReasoning/RiskAssessor** (626 lines)

---

## Hardening Checklist

For each service, apply the following pattern to **all public methods** (except simple getters/setters):

### 1. ✅ Add Required Imports

At the top of each service file, add:

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
```

Add appropriate custom exception imports:
- `use App\Exceptions\AnalysisException;` - for analysis operations
- `use App\Exceptions\SearchException;` - for search operations
- `use App\Exceptions\GraphException;` - for graph operations
- `use App\Exceptions\VectorStoreException;` - for vector store operations
- `use App\Exceptions\AgentException;` - for agent operations
- `use App\Exceptions\IngestException;` - for ingestion operations

### 2. ✅ Add Correlation ID & Timing

At the start of each public method:

```php
public function yourMethod($param1, $param2): ReturnType
{
    $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
    $startTime = microtime(true);

    Log::withContext(['correlation_id' => $correlationId]);

    Log::info('ServiceName: yourMethod initiated', [
        'param1' => $param1,
        'param2_length' => strlen($param2), // or other relevant metric
        'user_id' => auth()->id(),
    ]);

    try {
        // Existing method body goes here...
```

### 3. ✅ Add Completion Logging

Before each return statement in the try block:

```php
        $duration = (microtime(true) - $startTime) * 1000;

        Log::info('ServiceName: yourMethod completed', [
            'result_count' => count($result), // or other relevant metric
            'duration_ms' => round($duration, 2),
        ]);

        return $result;
```

### 4. ✅ Add Specific Exception Catching

After the try block, catch specific exceptions before generic:

```php
    } catch (YourSpecificException $e) {
        Log::error('ServiceName: yourMethod failed with YourSpecificException', [
            'param1' => $param1,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'exception_class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        throw $e; // Re-throw or handle gracefully

    } catch (\Exception $e) {
        Log::error('ServiceName: yourMethod failed with unexpected exception', [
            'param1' => $param1,
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new YourSpecificException(
            'Operation failed: ' . $e->getMessage(),
            YourSpecificException::UNEXPECTED_ERROR,
            $e // Exception chaining
        );
    }
}
```

---

## Complete Pattern Example

### Before Hardening

```php
<?php

namespace App\Services;

use SomeDependency;

class ExampleService
{
    public function processData(string $input): array
    {
        $result = $this->doSomething($input);

        return $result;
    }
}
```

### After Hardening

```php
<?php

namespace App\Services;

use App\Exceptions\AnalysisException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SomeDependency;

class ExampleService
{
    public function processData(string $input): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('ExampleService: processData initiated', [
            'input_length' => strlen($input),
            'user_id' => auth()->id(),
        ]);

        try {
            // Validate input
            if (empty($input)) {
                throw new AnalysisException(
                    'Input cannot be empty',
                    AnalysisException::INVALID_INPUT
                );
            }

            $result = $this->doSomething($input);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('ExampleService: processData completed', [
                'result_count' => count($result),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('ExampleService: processData failed with AnalysisException', [
                'input_length' => strlen($input),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('ExampleService: processData failed with unexpected exception', [
                'input_length' => strlen($input),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Data processing failed: ' . $e->getMessage(),
                AnalysisException::UNEXPECTED_ERROR,
                $e
            );
        }
    }
}
```

---

## Exception Code Reference

### SearchException (`app/Exceptions/SearchException.php`)
- `EMBEDDING_FAILED = 1001` - Embedding generation failed
- `VECTOR_SEARCH_FAILED = 1002` - Vector search operation failed
- `CORPUS_NOT_FOUND = 1003` - Requested corpus doesn't exist
- `INVALID_QUERY = 1004` - Query validation failed
- `RESULT_FORMATTING_FAILED = 1005` - Result parsing/formatting failed
- `CACHE_FAILED = 1006` - Caching operation failed
- `UNEXPECTED_ERROR = 1999` - Generic unexpected error

### AnalysisException
Check `app/Exceptions/AnalysisException.php` for available error codes.

### AgentException
Check `app/Exceptions/AgentException.php` for available error codes.

### GraphException
Check `app/Exceptions/GraphException.php` for available error codes.

### VectorStoreException
Check `app/Exceptions/VectorStoreException.php` for available error codes.

---

## Service-Specific Guidelines

### For Services with Database Operations

Add database-specific error handling:

```php
} catch (\Illuminate\Database\QueryException $e) {
    Log::error('ServiceName: Database query failed', [
        'error' => $e->getMessage(),
        'sql' => $e->getSql(),
        'bindings' => $e->getBindings(),
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);

    throw new YourException(
        'Database operation failed',
        YourException::DATABASE_ERROR,
        $e
    );
}
```

### For Services with External API Calls

Add HTTP-specific error handling:

```php
} catch (\Illuminate\Http\Client\ConnectionException $e) {
    Log::error('ServiceName: External API connection failed', [
        'error' => $e->getMessage(),
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);

    throw new YourException(
        'External service unavailable',
        YourException::EXTERNAL_SERVICE_ERROR,
        $e
    );
} catch (\Illuminate\Http\Client\RequestException $e) {
    Log::error('ServiceName: External API request failed', [
        'error' => $e->getMessage(),
        'status_code' => $e->response->status(),
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);

    throw new YourException(
        'External API request failed',
        YourException::API_REQUEST_FAILED,
        $e
    );
}
```

### For Services with File Operations

Add file-specific error handling:

```php
} catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
    Log::error('ServiceName: File not found', [
        'file_path' => $filePath,
        'error' => $e->getMessage(),
        'exception_class' => get_class($e),
        'trace' => $e->getTraceAsString(),
    ]);

    throw new YourException(
        'Required file not found',
        YourException::FILE_NOT_FOUND,
        $e
    );
}
```

---

## Testing Hardened Services

### 1. Unit Tests

Ensure unit tests cover:
- Normal operation logging
- Error path logging
- Exception chaining
- Correlation ID propagation

### 2. Integration Tests

Test that:
- Correlation IDs flow through service calls
- Performance metrics are accurate
- Error logs contain stack traces
- Exception codes are correct

### 3. Manual Testing

Use log viewers to verify:
```bash
php artisan pail --filter="ServiceName"
```

Check for:
- Correlation ID in all related logs
- Duration measurements
- Complete stack traces on errors

---

## Commit Message Template

```
Intensive hardening: [ServiceName]

Apply intensive hardening pattern to [ServiceName]:

✅ Correlation ID tracking with Log::withContext()
✅ Info logging at operation start with full context
✅ Performance timing in milliseconds
✅ Specific exception catching ([SpecificException] before generic)
✅ Custom exception throwing with error codes
✅ Exception chaining for debugging
✅ Comprehensive error logging with stack traces

Methods hardened:
- method1() - description
- method2() - description
- method3() - description

Pattern includes:
- Correlation ID from X-Request-ID header or UUID
- Duration tracking with microtime(true)
- Exception class names in logs
- Full stack traces via getTraceAsString()
- Input validation with specific exceptions
```

---

## Metrics & Observability

After hardening, you should be able to:

### 1. Trace Request Flows
```bash
# Find all logs for a specific request
php artisan pail --filter="correlation_id:abc-123-def"
```

### 2. Monitor Performance
```bash
# Find slow operations
php artisan pail --filter="duration_ms:>1000"
```

### 3. Debug Errors
```bash
# Find all errors with stack traces
php artisan pail --filter="exception_class" --level=error
```

### 4. Track User Activity
```bash
# Find all operations by a specific user
php artisan pail --filter="user_id:42"
```

---

## Next Steps

### Immediate (Complete remaining 13 services)

1. **Priority 1 - Critical Services (5 services)**
   - EoglasnaService (540 lines) - External service integration
   - CaseVectorStoreService (616 lines) - Vector store operations
   - OpenAIAnalysisService (478 lines) - AI operations
   - VectorStoreManagementService (464 lines) - Vector store management
   - GraphQueryHelper (461 lines) - Graph database queries

2. **Priority 2 - Legal Reasoning Services (6 services)**
   - RiskAssessor (626 lines)
   - StrategyBuilder (511 lines)
   - ArgumentGenerator (516 lines)
   - ImpactAnalyzer (505 lines)
   - CitationAnalyzer (450 lines)
   - ConflictResolver (437 lines)

3. **Priority 3 - Supporting Services (2 services)**
   - LegalTeamOrchestrator (440 lines)
   - ConfigValidator (466 lines)

### Long-term

- Add custom metrics/dashboards for monitoring
- Create alerts for high error rates
- Build correlation ID tracking UI
- Implement distributed tracing with external tools (Jaeger, Zipkin)

---

## Resources

- **Correlation ID Middleware**: `app/Http/Middleware/AddCorrelationId.php`
- **Logging Config**: `config/logging.php` (JSON formatters enabled)
- **Exception Definitions**: `app/Exceptions/`
- **Hardened Examples**:
  - `app/Services/Research/SearchExecutorService.php`
  - `app/Services/Research/QualityAssessorService.php`

---

## Notes

- **Do not skip methods**: All public methods (except trivial getters/setters) need hardening
- **Consistency is key**: Use the exact same pattern across all services
- **Test after each service**: Run tests after hardening each service to catch issues early
- **Commit frequently**: Commit after each service or small batch to avoid losing progress
- **Use appropriate exceptions**: Choose the right exception type for each service's domain
- **Include context**: Always log relevant parameters and state, but avoid logging sensitive data

---

**Last Updated**: 2025-11-09
**Updated By**: Claude (Worker C - Intensive Hardening Task)
