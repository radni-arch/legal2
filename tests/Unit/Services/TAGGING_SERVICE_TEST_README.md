# TaggingService Test Suite

Comprehensive test suite for the `TaggingService` class, which manages hierarchical tags for Croatian legal content in a graph database.

## Overview

The `TaggingService` provides automated and manual tagging functionality for legal documents, using a hierarchical tag structure stored in Neo4j. It supports:

- **Hierarchical tag taxonomy** for Croatian legal content
- **Auto-tagging** based on Croatian legal keyword matching
- **Graph database operations** for tag relationships
- **Tag discovery** (related tags, hierarchy traversal)
- **Metadata-based tagging** from document properties

## Test Coverage

**Total Tests**: 42 tests with 74 assertions
**Status**: ✓ All passing

### Test Categories

#### 1. Tag Hierarchy Initialization (4 tests)
- `it_initializes_tag_hierarchy` - Creates all categories and subcategories
- `it_creates_tag_category_with_correct_structure` - Verifies node structure
- `it_creates_leaf_tags_at_level_3` - Tests 3-level hierarchy
- `it_creates_parent_relationships` - Tests PARENT_TAG relationships

**Croatian Legal Hierarchy**:
```
legal_area/
  ├── civil_law/ (ugovor, vlasništvo, obitelj, nasljeđivanje)
  ├── criminal_law/ (kazneno, prekršaj, kazna)
  ├── administrative_law/ (upravno, dozvola, inspekcija)
  ├── constitutional_law/ (ustav, pravo, sloboda)
  ├── labor_law/ (rad, zaposleni, plaća)
  └── commercial_law/ (trgovačko, stečaj, konkurencija)

procedure/
  ├── litigation/
  └── alternative_dispute_resolution/

jurisdiction/
  ├── croatia/ (national, regional, local)
  ├── european_union/
  └── international/

document_type/
  ├── primary_legislation/
  ├── secondary_legislation/
  └── case_law/

topic/
  ├── human_rights/
  ├── economic_rights/
  ├── environmental/
  └── digital/
```

#### 2. Auto-Tagging (4 tests)
- `it_auto_tags_content_with_croatian_legal_keywords` - Pattern matching
- `it_extracts_tags_from_metadata` - Metadata tag extraction
- `it_normalizes_and_deduplicates_tags` - Case-insensitive deduplication
- `it_integrates_content_and_metadata_tags` - Combined tagging

**Example**:
```php
$content = 'Ugovor o vlasništvu nekretnine';
$metadata = [
    'tags' => ['important'],
    'jurisdiction' => 'Croatia',
    'court' => 'Vrhovni sud'
];

$tags = $service->autoTag('Document', 'doc123', $content, $metadata);
// Returns: ['civil_law', 'important', 'jurisdiction_croatia', 'supreme_court']
```

#### 3. Croatian Legal Keyword Detection (6 tests)
- `it_detects_civil_law_keywords` - ugovor, vlasništvo, obitelj, nasljeđ
- `it_detects_criminal_law_keywords` - kazneno, presuda, zatvor
- `it_detects_administrative_law_keywords` - upravno, dozvola, uprava
- `it_detects_labor_law_keywords` - rad, plaća, radni odnos
- `it_detects_commercial_law_keywords` - trgovačko, stečaj, konkurencija
- `it_detects_constitutional_law_keywords` - ustav, sloboda, jednakost

**Croatian Legal Terms**:
- **Civil Law**: ugovor (contract), vlasništvo (property), obitelj (family), nasljeđivanje (inheritance), odšteta (damages)
- **Criminal Law**: kazneno (criminal), prekršaj (misdemeanor), presuda (verdict), kazna (penalty), zatvor (prison)
- **Administrative Law**: upravno (administrative), uprava (administration), dozvola (permit), inspekcija (inspection)
- **Labor Law**: rad (work), zaposleni (employees), plaća (salary), otpremnina (severance), radni odnos (employment relationship)
- **Commercial Law**: trgovačko (commercial), društvo (company), stečaj (bankruptcy), konkurencija (competition)
- **Constitutional Law**: ustav (constitution), pravo (right), sloboda (freedom), jednakost (equality)

#### 4. Metadata Extraction (3 tests)
- `it_extracts_jurisdiction_from_metadata` - Jurisdiction tagging
- `it_detects_supreme_court_from_metadata` - Vrhovni sud detection
- `it_detects_appellate_court_from_metadata` - Županijski sud detection

**Croatian Court Hierarchy**:
- **Vrhovni sud** (Supreme Court) → `supreme_court` tag
- **Županijski sud** (County/Appellate Court) → `appellate_court` tag

#### 5. Croatian Text Processing (3 tests)
- `it_handles_croatian_characters_in_content` - UTF-8 diacritics
- `it_case_insensitive_keyword_matching` - mb_strtolower usage
- `it_stops_at_first_keyword_match_per_category` - Deduplication logic

**Croatian Diacritics**: đ, č, ć, š, ž (fully supported via mb_* functions)

#### 6. Tag Application (4 tests)
- `it_applies_tag_to_node` - Creates tag and HAS_TAG relationship
- `it_normalizes_tag_name_when_applying` - Lowercase + slug conversion
- `it_handles_tag_with_spaces_and_dashes` - Special character normalization
- `it_logs_warning_on_tag_application_failure` - Error handling

**Tag Normalization**:
```php
'Civil Law'       → tag_id: 'tag_civil_law', slug: 'civil-law'
'labor-law employment' → tag_id: 'tag_labor_law_employment', slug: 'labor-law-employment'
```

#### 7. Tag Retrieval (3 tests)
- `it_gets_node_tags` - Retrieves all tags for a node
- `it_gets_node_tags_ordered_by_category` - ORDER BY verification
- Tests Cypher query construction

**Cypher Query**:
```cypher
MATCH (n:Document {id: $id})-[:HAS_TAG]->(t:Tag)
RETURN t.name as name, t.category as category, t.slug as slug
ORDER BY t.category, t.name
```

#### 8. Node Discovery (4 tests)
- `it_gets_nodes_by_tag` - Find documents with specific tag
- `it_gets_nodes_by_tag_with_label_filter` - Filter by node label
- `it_gets_nodes_by_tag_with_custom_limit` - Pagination
- `it_normalizes_tag_name_in_queries` - Tag name normalization

**Example**:
```php
// Get all nodes tagged 'contract'
$nodes = $service->getNodesByTag('contract');

// Get only Document nodes tagged 'contract', limit 100
$nodes = $service->getNodesByTag('contract', 'Document', 100);
```

#### 9. Related Tags (3 tests)
- `it_gets_related_tags` - Tags that co-occur
- `it_gets_related_tags_with_custom_limit` - Pagination
- `it_excludes_same_tag_in_related_tags_query` - WHERE t <> related

**Cypher Query**:
```cypher
MATCH (t:Tag {id: $tagId})<-[:HAS_TAG]-(n)-[:HAS_TAG]->(related:Tag)
WHERE t <> related
RETURN related.name as name, related.category as category, count(*) as frequency
ORDER BY frequency DESC
LIMIT $limit
```

**Example**:
```php
$relatedTags = $service->getRelatedTags('contract', 10);
// Returns: [
//   ['name' => 'property', 'category' => 'legal_area', 'frequency' => 15],
//   ['name' => 'civil_law', 'category' => 'legal_area', 'frequency' => 12],
//   ...
// ]
```

#### 10. Tag Hierarchy (2 tests)
- `it_gets_tag_hierarchy` - Path from tag to root
- `it_returns_empty_array_when_tag_hierarchy_not_found` - Error handling

**Cypher Query**:
```cypher
MATCH path = (t:Tag {id: $tagId})-[:PARENT_TAG*0..]->(parent:Tag)
RETURN [tag in nodes(path) | tag.name] as hierarchy
```

**Example**:
```php
$hierarchy = $service->getTagHierarchy('contract');
// Returns: ['contracts', 'civil_law', 'legal_area']
```

#### 11. Tag Removal (2 tests)
- `it_removes_tag_from_node` - DELETE relationship
- `it_normalizes_tag_name_when_removing` - Tag name normalization

#### 12. Tag Hierarchy Structure (3 tests)
- `it_has_comprehensive_tag_hierarchy` - 5 main categories
- `it_has_croatian_legal_area_categories` - 6 legal areas
- `it_has_jurisdiction_categories_including_croatia` - Croatia, EU, International

#### 13. Edge Cases (2 tests)
- `it_handles_empty_content` - Graceful degradation
- `it_handles_empty_metadata` - Graceful degradation

## Testing Approach

### Unit Testing with Mocked Dependencies

All tests use **Mockery** to mock the `GraphDatabaseService` dependency:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
    $this->service = new TaggingService($this->mockGraph);
}
```

### Testing Private/Protected Methods

Uses **ReflectionClass** to test protected methods like `extractTagsFromContent`:

```php
$reflection = new \ReflectionClass($this->service);
$method = $reflection->getMethod('extractTagsFromContent');
$method->setAccessible(true);
$tags = $method->invoke($this->service, $content, $metadata);
```

### Mock Graph Database Interactions

#### Node Creation:
```php
$this->mockGraph->shouldReceive('upsertNode')
    ->once()
    ->with('Tag', 'tag_contract', [
        'name' => 'contract',
        'slug' => 'contract',
    ]);
```

#### Relationship Creation:
```php
$this->mockGraph->shouldReceive('createRelationship')
    ->once()
    ->with('Document', 'doc123', 'HAS_TAG', 'Tag', 'tag_contract', Mockery::on(function ($data) {
        return isset($data['applied_at']);
    }));
```

#### Cypher Query Execution:
```php
$mockResult = Mockery::mock();
$mockResult->shouldReceive('map')->once()->andReturn(collect([...]));

$this->mockGraph->shouldReceive('run')
    ->once()
    ->with(Mockery::type('string'), ['id' => 'doc123'])
    ->andReturn($mockResult);
```

## Running the Tests

```bash
# Run all TaggingService tests
./vendor/bin/phpunit tests/Unit/Services/TaggingServiceTest.php

# Run with coverage
./vendor/bin/phpunit tests/Unit/Services/TaggingServiceTest.php --coverage-html coverage/

# Run specific test
./vendor/bin/phpunit --filter it_auto_tags_content_with_croatian_legal_keywords tests/Unit/Services/TaggingServiceTest.php
```

## Dependencies

### Required Services
- **GraphDatabaseService** - Neo4j graph database wrapper (mocked in tests)

### Required Packages
- **Mockery** - Mocking framework for GraphDatabaseService
- **Laravel Collections** - Used in mock result processing
- **Laravel Facades** - Log facade for error handling

### Croatian Language Support
- **mb_string** extension - Required for Croatian character processing
- **UTF-8** encoding - For diacritics (đ, č, ć, š, ž)

## Integration Testing

While these are unit tests with mocked dependencies, **integration tests** would require:

1. **Neo4j Database**:
   - Running Neo4j instance (Docker recommended)
   - Test database with graph schema
   - Cleanup between tests

2. **Graph Queries**:
   - Verify actual Cypher query execution
   - Test relationship traversal
   - Validate pattern matching performance

3. **Croatian Text Processing**:
   - Full-text search with Croatian analyzer
   - Lemmatization for Croatian legal terms
   - Synonym matching

## Croatian Legal Domain

### Legal Areas (Pravna područja)

| English | Croatian | Tag |
|---------|----------|-----|
| Civil Law | Građansko pravo | `civil_law` |
| Criminal Law | Kazneno pravo | `criminal_law` |
| Administrative Law | Upravno pravo | `administrative_law` |
| Constitutional Law | Ustavno pravo | `constitutional_law` |
| Labor Law | Radno pravo | `labor_law` |
| Commercial Law | Trgovačko pravo | `commercial_law` |

### Court System (Sudstvo)

| Court Type | Croatian | Tag |
|------------|----------|-----|
| Supreme Court | Vrhovni sud | `supreme_court` |
| Appellate Court | Županijski sud | `appellate_court` |
| Municipal Court | Općinski sud | (detected via metadata) |

### Document Types (Vrste dokumenata)

- **Primary Legislation**: Constitution (Ustav), Laws (Zakoni), Ordinances (Uredbe)
- **Secondary Legislation**: Regulations (Pravilnici), Bylaws (Statute), Decrees (Odluke)
- **Case Law**: Supreme Court (Vrhovni sud), Appellate Court (Županijski sud), First Instance (Prvostupanjski)

## Performance Considerations

### Tag Application
- **Bulk operations**: Apply multiple tags in a single transaction
- **Caching**: Cache frequently used tag hierarchies
- **Indexing**: Neo4j indexes on `Tag.id` and `Tag.slug`

### Auto-Tagging
- **Keyword matching**: O(n×m) where n=patterns, m=keywords per pattern
- **Optimization**: Short-circuit on first keyword match per category
- **Memory**: Minimal - pattern matching on lowercase content only

### Graph Traversal
- **Hierarchy queries**: O(depth) - typically 3 levels max
- **Related tags**: Limited by query LIMIT parameter (default 10)
- **Node discovery**: Limited by LIMIT parameter (default 50)

## Error Handling

### Tag Application Failures
```php
try {
    $this->graph->createRelationship(...);
} catch (\Exception $e) {
    Log::warning('Failed to apply tag', [
        'node_label' => $nodeLabel,
        'node_id' => $nodeId,
        'tag' => $tagName,
        'error' => $e->getMessage(),
    ]);
}
```

**Test Coverage**: `it_logs_warning_on_tag_application_failure`

### Missing Data
- **Empty content**: Returns empty array
- **Empty metadata**: Returns empty array
- **Tag not found**: Returns empty array (hierarchy queries)

## Usage Examples

### Auto-Tag a Legal Document
```php
$content = 'Ugovor o kupoprodaji nekretnine u Zagrebu';
$metadata = [
    'jurisdiction' => 'Croatia',
    'tags' => ['real-estate'],
];

$tags = $service->autoTag('Document', 'doc123', $content, $metadata);
// Returns: ['civil_law', 'real-estate', 'jurisdiction_croatia']
```

### Find Related Legal Content
```php
// Find all documents tagged 'contract'
$documents = $service->getNodesByTag('contract', 'Document', 20);

// Find tags that often appear with 'contract'
$relatedTags = $service->getRelatedTags('contract', 5);
// Returns: ['property', 'civil_law', 'real-estate', ...]
```

### Build Tag Breadcrumb
```php
$hierarchy = $service->getTagHierarchy('contracts');
// Returns: ['contracts', 'civil_law', 'legal_area']

// Display: Legal Area > Civil Law > Contracts
```

### Initialize Taxonomy
```php
// One-time setup: Create all tags and relationships
$service->initializeTagHierarchy();
// Creates ~50+ tags with PARENT_TAG relationships
```

## Test Maintenance

### Adding New Legal Areas
1. Update `$tagHierarchy` property in `TaggingService.php`
2. Add keyword patterns to `extractTagsFromContent()`
3. Add detection test: `it_detects_new_area_keywords()`
4. Add hierarchy test if multi-level

### Adding New Keywords
1. Update patterns in `extractTagsFromContent()`
2. Add test case with new keyword
3. Verify keyword doesn't conflict with other categories

### Updating Cypher Queries
1. Update query in service method
2. Verify mock expectations still match
3. Update pattern matching if query structure changed

## Known Limitations

1. **Pattern Matching**:
   - Simple keyword matching (no lemmatization)
   - First match only per category (doesn't score multiple matches)
   - Croatian-specific only (no multi-language support)

2. **Graph Database**:
   - No actual Neo4j connection in unit tests
   - Cypher queries not validated for syntax
   - Integration tests required for query verification

3. **Performance**:
   - No benchmarks for large-scale tagging
   - No testing of concurrent tag operations
   - No cache warming strategies

## Related Documentation

- **GraphDatabaseService**: Graph database abstraction layer
- **Croatian Legal System**: https://pravosudje.gov.hr/
- **Neo4j Cypher**: https://neo4j.com/docs/cypher-manual/
- **Laravel Collections**: https://laravel.com/docs/collections

## Authors

- Test Suite: Claude (Anthropic)
- Service Implementation: ai-legal-war-machine project

## License

Part of the ai-legal-war-machine project.
