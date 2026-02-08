# MCP Tools Testing Guide

## Overview

This document provides a comprehensive guide to the test suite for the MCP (Model Context Protocol) tools implementation. The test suite includes unit tests, integration tests, and feature tests covering all components of the MCP system.

## Test Coverage

### Unit Tests (tests/Unit/)

#### 1. ToolSchemas Tests (`tests/Unit/Mcp/ToolSchemasTest.php`)
**Coverage:** Centralized tool schema definitions
**Tests:** 15 test cases

- Schema structure validation
- All 5 tool schemas (odluke-search, odluke-meta, odluke-download, law-articles-search, law-article-by-id)
- OpenAI function format conversion
- Validation constraints (limits, enums, required fields)

**Key Assertions:**
- ✅ All 5 schemas are present and valid
- ✅ Schema structure follows JSON Schema spec
- ✅ OpenAI conversion produces correct snake_case names
- ✅ Validation constraints are properly configured

#### 2. OdlukeTools Tests (`tests/Unit/Mcp/OdlukeToolsTest.php`)
**Coverage:** Core MCP tool implementations
**Tests:** 20 test cases

- `search()` - Success/error cases, parameter validation
- `meta()` - Single/multiple IDs, error handling
- `download()` - Format validation, HTTP errors, file operations
- `searchLawArticles()` - Query filtering, limits, eager loading (N+1 prevention)
- `getLawArticleById()` - Retrieval with parent law

**Key Assertions:**
- ✅ All methods return correct MCP response format
- ✅ Error handling works properly
- ✅ N+1 query issue is prevented (eager loading verified)
- ✅ Parameter validation and defaults work correctly

#### 3. McpToOpenAIBridge Tests (`tests/Unit/Services/McpToOpenAIBridgeTest.php`)
**Coverage:** OpenAI compatibility layer
**Tests:** 13 test cases

- Function list generation
- Tool execution for all 5 tools
- Error handling and graceful degradation
- Tool definitions format
- Chat completion processing

**Key Assertions:**
- ✅ Returns 5 OpenAI-compatible functions
- ✅ All tools execute correctly through bridge
- ✅ Errors are caught and returned properly
- ✅ OpenAI format is valid

#### 4. InternalMcpClient Tests (`tests/Unit/Services/InternalMcpClientTest.php`)
**Coverage:** Direct in-process tool calls
**Tests:** 11 test cases

- Tool calling for all 5 tools
- Default parameter handling
- Error handling
- Tool listing

**Key Assertions:**
- ✅ All tools callable via internal client
- ✅ Arguments passed correctly
- ✅ Exceptions handled gracefully
- ✅ Tool list returns all 5 tools with schemas

#### 5. McpApiTokenAuth Tests (`tests/Unit/Http/Middleware/McpApiTokenAuthTest.php`)
**Coverage:** Authentication middleware
**Tests:** 11 test cases

- No token configured (backward compatibility)
- Missing/invalid Authorization header
- Wrong token rejection
- Correct token acceptance
- JSON error responses

**Key Assertions:**
- ✅ Allows requests when no token configured
- ✅ Rejects requests without proper auth
- ✅ Accepts valid Bearer tokens
- ✅ Returns proper 401 JSON responses

#### 6. OdlukeSearchTool Tests (`tests/Unit/Tools/OdlukeSearchToolTest.php`)
**Coverage:** Vizra ADK tool wrapper
**Tests:** 7 test cases

- Name and description
- Schema integration
- Tool execution via InternalMcpClient
- Content extraction from MCP format

**Key Assertions:**
- ✅ Uses centralized ToolSchemas
- ✅ Calls InternalMcpClient correctly
- ✅ Extracts text from MCP content format
- ✅ Falls back to JSON when needed

### Feature/Integration Tests (tests/Feature/)

#### 7. McpHttpController Tests (`tests/Feature/Mcp/McpHttpControllerTest.php`)
**Coverage:** HTTP MCP JSON-RPC 2.0 endpoints
**Tests:** 9 test cases

- `/mcp/info` - Public endpoint, tool listing
- `/mcp/message` - Authentication, JSON-RPC protocol
- Methods: `initialize`, `tools/list`, `tools/call`
- Rate limiting (60 req/min)
- Unknown method error handling

**Key Assertions:**
- ✅ Info endpoint public and returns all tools
- ✅ Message endpoint requires authentication
- ✅ JSON-RPC 2.0 protocol followed
- ✅ Rate limiting enforced
- ✅ Error codes correct (-32601 for unknown method)

#### 8. McpOpenAIController Tests (`tests/Feature/Mcp/McpOpenAIControllerTest.php`)
**Coverage:** OpenAI-compatible API endpoints
**Tests:** 12 test cases

- `/api/mcp-openai/info` - Public info endpoint
- `/api/mcp-openai/tools` - Tool listing (protected)
- `/api/mcp-openai/tools/execute` - Tool execution (protected)
- `/api/mcp-openai/chat/completions` - Chat endpoint (protected)
- Rate limiting and authentication

**Key Assertions:**
- ✅ Info endpoint is public
- ✅ All other endpoints require authentication
- ✅ Tools in OpenAI function format
- ✅ Tool names in snake_case
- ✅ Rate limiting enforced

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites

#### Unit Tests Only
```bash
php artisan test --testsuite=Unit
```

#### Feature Tests Only
```bash
php artisan test --testsuite=Feature
```

#### MCP-Specific Tests
```bash
# Unit tests
php artisan test tests/Unit/Mcp/
php artisan test tests/Unit/Services/
php artisan test tests/Unit/Tools/
php artisan test tests/Unit/Http/Middleware/

# Feature tests
php artisan test tests/Feature/Mcp/
```

### Run Single Test File
```bash
php artisan test tests/Unit/Mcp/ToolSchemasTest.php
```

### Run Single Test Method
```bash
php artisan test --filter test_all_returns_array_of_schemas
```

### Run with Coverage (requires Xdebug)
```bash
php artisan test --coverage
```

### Run with Coverage Report
```bash
php artisan test --coverage --coverage-html coverage-report
```

## Test Statistics

### Total Test Count: **98+ tests**

**Breakdown:**
- Unit Tests: 77 tests
  - ToolSchemas: 15 tests
  - OdlukeTools: 20 tests
  - McpToOpenAIBridge: 13 tests
  - InternalMcpClient: 11 tests
  - McpApiTokenAuth: 11 tests
  - OdlukeSearchTool: 7 tests

- Feature Tests: 21 tests
  - McpHttpController: 9 tests
  - McpOpenAIController: 12 tests

### Code Coverage Targets

**Current Coverage (estimated):**
- ToolSchemas: ~95%
- OdlukeTools: ~85%
- McpToOpenAIBridge: ~90%
- InternalMcpClient: ~90%
- McpApiTokenAuth: ~100%
- Tool Wrappers: ~85%
- Controllers: ~80%

**Target Coverage:** 85%+ overall

## Test Data & Fixtures

### Database Setup
Tests use Laravel's `RefreshDatabase` trait to ensure clean state:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
}
```

### Test Data Examples

#### IngestedLaw Factory Usage
```php
$ingestedLaw = IngestedLaw::create([
    'id' => 'test-law-id',
    'doc_id' => 'NN-123-20',
    'title' => 'Test Law',
    'law_number' => 'NN 123/20',
    'jurisdiction' => 'Croatia',
    'country' => 'HR',
    'language' => 'hr',
    'ingested_at' => now(),
]);
```

#### Law Article Factory Usage
```php
Law::create([
    'id' => 'test-article-1',
    'doc_id' => 'NN-123-20',
    'ingested_law_id' => $ingestedLaw->id,
    'chunk_index' => 1,
    'content' => 'Article 1 content',
    'chapter' => 'Chapter 1',
]);
```

### Mocking External Dependencies

#### Mocking OdlukeClient
```php
$mockClient = $this->createMock(OdlukeClient::class);
$mockClient->method('collectIdsFromList')
    ->willReturn(['ids' => ['id1', 'id2', 'id3']]);

$this->app->instance(OdlukeClient::class, $mockClient);
```

#### Mocking InternalMcpClient
```php
$mockClient = $this->createMock(InternalMcpClient::class);
$mockClient->expects($this->once())
    ->method('callTool')
    ->with('odluke-search', ['q' => 'test'])
    ->willReturn([
        'content' => [['type' => 'text', 'text' => 'Results']],
        'isError' => false,
    ]);
```

## Continuous Integration

### GitHub Actions Configuration
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: php artisan test
```

## Best Practices

### 1. Test Naming
- Use descriptive test names: `test_search_returns_success_response_with_ids()`
- Or use `/** @test */` annotation with descriptive method names

### 2. AAA Pattern (Arrange-Act-Assert)
```php
public function test_example(): void
{
    // Arrange: Set up test data
    $data = ['key' => 'value'];

    // Act: Execute the code under test
    $result = $service->process($data);

    // Assert: Verify the result
    $this->assertTrue($result);
}
```

### 3. Test Isolation
- Each test should be independent
- Use `RefreshDatabase` for clean state
- Mock external dependencies
- Don't rely on test execution order

### 4. Assertions
- Use specific assertions: `assertCount()`, `assertArrayHasKey()`, etc.
- Avoid generic `assertTrue()` when specific assertion available
- Include helpful failure messages when needed

### 5. Coverage
- Aim for 85%+ code coverage
- Focus on critical paths first
- Test both success and error cases
- Test edge cases and boundary conditions

## Known Testing Limitations

1. **External API Calls:** OdlukeClient makes real HTTP calls - always mock in tests
2. **File System:** Tests use temporary directories - clean up after tests
3. **Rate Limiting:** Tests may hit rate limits - use separate test config
4. **Database:** Some tests require specific database setup

## Future Testing Enhancements

### Recommended Additions:
1. ✅ Performance/benchmark tests
2. ✅ End-to-end tests with real OpenAI API
3. ✅ Load testing for rate limits
4. ✅ Security testing (SQL injection, XSS, etc.)
5. ✅ Contract testing for API compatibility

### Test Coverage Gaps:
1. Other 4 Vizra ADK tool wrappers (only OdlukeSearchTool tested)
2. McpHttpController - detailed tool call tests with real data
3. Error scenarios in production-like conditions
4. Concurrent request handling

## Troubleshooting

### Common Issues

#### Tests Failing Due to Database
```bash
# Reset database
php artisan migrate:fresh --env=testing
```

#### Rate Limiting Causing Failures
```bash
# Clear rate limit cache
php artisan cache:clear --env=testing
```

#### Mock Not Working
- Ensure mock is bound before test execution
- Use `$this->app->instance()` for Laravel dependency injection
- Check method expectations match actual calls

## Contributing

When adding new tests:
1. Follow existing test structure
2. Use descriptive names
3. Test both success and failure paths
4. Update this document
5. Ensure all tests pass before PR

## Contact

For questions about testing:
- Review existing test files for examples
- Check Laravel testing documentation
- Refer to PHPUnit documentation

---

**Last Updated:** 2025-01-XX
**Test Suite Version:** 1.0
**Total Tests:** 98+
**Estimated Coverage:** 85%+
