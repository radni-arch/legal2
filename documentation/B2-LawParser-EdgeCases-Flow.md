# Sprint B2: LawParser Edge Cases - Flow Documentation

## Overview

Sprint B2 (Tasks B2.1 and B2.2) extends `LawParser` edge-case test coverage and implements minimal fixes to handle three critical parsing scenarios while maintaining backward compatibility and downstream indexing consistency.

## Changes Summary

**Files Modified:**
- `tests/Unit/LawParserEdgeCasesTest.php` - Added 3 new test cases
- `app/Services/LawParser.php` - Minimal regex fix (2 characters changed)

**Lines Changed:** +68 lines added, -2 lines modified

## Parser Flow Architecture

### High-Level Flow

```
Raw HTML → LawParser.splitIntoArticles() → Parsed Articles Array → ZakonHrIngestService → Embeddings
```

### Detailed Processing Steps

#### 1. Input Normalization (`LawParser.php:38-41`)

```php
// Convert non-breaking spaces (U+00A0) to regular spaces
$normalized = preg_replace('/\xC2\xA0/u', ' ', $bodyHtml);
// Normalize whitespace to single spaces
$normalized = preg_replace('/\s+/', ' ', $normalized);
```

**Preservation:** NN markers like `(NN 123/20)` remain intact in body content.

#### 2. Article Header Detection (`LawParser.php:50-54`)

Inserts line breaks before article headers to facilitate splitting.

**Regex Pattern (Modified):**
```php
'/(Članak|CLANAK)\s+(\d+)(?:\.?\s*[a-z]|[a-z])?\s*(?:\)|\.|\(|(?=\s))?/ui'
```

**Change:** Added `?` to make delimiter group optional: `(?:...)?` instead of `(?:...)`

**Supported Formats:**
- Standard: `Članak 24.`
- Uppercase: `CLANAK 24.`
- Lettered: `Članak 24a`, `Članak 24.a`, `Članak 24. a)`
- With delimiters: `Članak 24)`, `Članak 24(`
- **NEW:** No delimiter: `Članak 70д` (non-Latin trailing char)

#### 3. Content Splitting (`LawParser.php:58-59`)

```php
$splitRegex = '/(?=\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+\d+(?:\.?\s*[a-z]|[a-z])?\s*(?:\)|\.|\(|(?=\s))?)/ui';
$chunks = preg_split($splitRegex, $normalized, -1, PREG_SPLIT_NO_EMPTY);
```

**Key Features:**
- Lookahead `(?=...)` preserves article headers in chunks
- `(?:<[^>]+>\s*)*` handles nested HTML wrappers (divs, spans, etc.)
- Latin letter matching `[a-z]` excludes non-Latin letters (Cyrillic, Greek, etc.)

#### 4. Article Extraction (`LawParser.php:63-89`)

```php
$headerAtStart = '/^\s*(?:<[^>]+>\s*)*(Članak|CLANAK)\s+(\d+)(?:\.?\s*([a-z])|([a-z]))?\s*(?:\)|\.|[\(\s])?/ui';
```

**Captured Groups:**
- `$m[1]` - "Članak" or "CLANAK"
- `$m[2]` - Base article number (e.g., "24")
- `$m[3]` - Letter with dot/space (e.g., from "24. a")
- `$m[4]` - Letter without dot/space (e.g., from "24a")

**Article Structure:**
```php
[
    'number' => '24a',           // Or '24' for base articles
    'heading_chain' => [],       // Reserved for future heading hierarchy
    'html' => '<h3>Članak 24.a</h3>...'  // Normalized header + body
]
```

#### 5. Lettered Article Merging (`LawParser.php:95-113`)

**Critical for Downstream Indexing:**

```php
$merged = [];
foreach ($articles as $art) {
    if (preg_match('/^(\d+)([a-z])$/u', $art['number'], $nm)) {
        $base = $nm[1];
        if (!empty($merged) && $merged[array_key_last($merged)]['number'] === $base) {
            // Merge into preceding base article
            $merged[array_key_last($merged)]['html'] .= $art['html'];
            continue;
        }
        // No base found: create base article entry
        $art['number'] = $base;
        $merged[] = $art;
    } else {
        $merged[] = $art;
    }
}
```

**Example Merging:**

**Input:**
```
[0] Article 24  (number: "24")
[1] Article 24a (number: "24a")
[2] Article 24b (number: "24b")
[3] Article 25  (number: "25")
```

**Output:**
```
[0] Article 24 (number: "24", html: contains 24 + 24a + 24b content)
[1] Article 25 (number: "25")
```

**Indexing Preservation:**
- Base article 24 remains at index 0
- Article 25 remains at index 1 (not 3)
- `chunk_index` in `ZakonHrIngestService` uses array index → stable mapping

## Edge Cases Handled

### 1. Nested Wrappers Without `<h>` Tags

**Test:** `test_nested_wrappers_without_h_tags()`

**Input:**
```html
<div class="outer">
    <div class="inner">Članak 50. Tekst u nested div</div>
</div>
<div class="wrapper">
    <div><span>Članak 51. Tekst u nested span</span></div>
</div>
```

**Processing:**
- Regex `(?:<[^>]+>\s*)*` skips over opening tags
- Matches "Članak 50." and "Članak 51." inside wrappers
- Splits correctly into 2 articles

**Output:**
```
Article 50: number="50", html contains "Tekst u nested div"
Article 51: number="51", html contains "Tekst u nested span"
```

### 2. Multiple NN Markers in One Article

**Test:** `test_multiple_nn_markers_in_article()`

**Input:**
```html
<p>Članak 60. (NN 123/20) Prvi dio (NN 45/21) drugi dio (NN 78/22) treći dio</p>
```

**Processing:**
- Header regex removes only the article header: `Članak 60.`
- Body regex does NOT strip `(NN ...)` patterns
- All NN markers preserved as-is

**Output:**
```
Article 60: html="<h3>Članak 60.</h3> (NN 123/20) Prvi dio (NN 45/21) drugi dio (NN 78/22) treći dio"
```

**Verification:** All 3 NN markers appear in `article['html']`

### 3. Invalid Non-Latin Trailing Letter

**Test:** `test_invalid_non_latin_letter_in_header()`

**Input:**
```html
<p>Članak 70д Tekst sa cirilicnim slovom</p>
<p>Članak 71. Sljedeći članak</p>
```

**Processing:**
- Regex `[a-z]` only matches Latin lowercase letters
- Cyrillic "д" does NOT match letter pattern
- Optional delimiter `(?:...)?` allows match without delimiter
- "Članak 70" matches, "д" left in body

**Output:**
```
Article 70: number="70", html contains "д Tekst sa cirilicnim slovom"
Article 71: number="71"
```

**Behavior:**
- ✓ Treats as base numeric article (no lettered variant)
- ✓ Preserves non-Latin character in body
- ✓ Does not merge with other articles (no letter in number)

## Regex Change Details

### Before (Lines 51, 58)

```php
'/(Članak|CLANAK)\s+(\d+)(?:\.?\s*[a-z]|[a-z])?\s*(?:\)|\.|\(|(?=\s))/ui'
```

**Required:** Closing paren OR dot OR opening paren OR (whitespace lookahead)

**Problem:** Fails to match "Članak 70д" because:
- No delimiter after "70"
- Next char "д" is not Latin letter → doesn't match `[a-z]`
- Entire pattern fails

### After (Lines 51, 58)

```php
'/(Članak|CLANAK)\s+(\d+)(?:\.?\s*[a-z]|[a-z])?\s*(?:\)|\.|\(|(?=\s))?/ui'
                                                                          ^
                                                                     Added ?
```

**Optional:** Delimiter group is now optional

**Fix:** Successfully matches "Članak 70д":
- Matches "Članak 70"
- Delimiter group not required
- "д" remains in body content

## Downstream Impact

### ZakonHrIngestService Integration

**File:** `app/Services/ZakonHrIngestService.php`

**Usage (Line 270):**
```php
'chunk_index' => $idx
```

**Impact of Merging:**

| Original Articles | Parser Output (Merged) | chunk_index Values |
|-------------------|------------------------|-------------------|
| 24, 24a, 24b, 25  | 24 (merged), 25       | 0, 1              |

**Consistency Guarantee:**
- Base article numbers (24, 25, ...) always at predictable indices
- Lettered variants merged → no index gaps
- Embedding service receives stable chunk_index for each logical article

## Test Coverage Summary

**Total Tests:** 3 new tests added to `LawParserEdgeCasesTest.php`

1. **test_nested_wrappers_without_h_tags**
   - Validates: HTML wrapper handling
   - Assertions: 2 articles split correctly, content preserved

2. **test_multiple_nn_markers_in_article**
   - Validates: NN marker preservation
   - Assertions: 1 article, all 3 NN markers present in HTML

3. **test_invalid_non_latin_letter_in_header**
   - Validates: Non-Latin character handling
   - Assertions: Base number assigned, Cyrillic char in body, no merge

**Existing Tests:** All 18 previous tests remain passing (no regressions)

## Acceptance Criteria ✓

- [x] All new tests pass
- [x] NN markers remain visible in parsed article bodies
- [x] Lettered article merging preserves base indexing for chunk_index alignment
- [x] No changes to public method signatures
- [x] Minimal code changes (2-character regex modification)
- [x] Improved headerAtStart regex preserved
- [x] Break normalization semantics maintained

## Future Considerations

### Potential Edge Cases Not Yet Encountered

1. **Multiple non-Latin letters:** "Članak 70дγ"
   - Current: Matches "70", leaves "дγ" in body
   - Expected: Same behavior (reasonable)

2. **Mixed Latin/non-Latin:** "Članak 70aд"
   - Current: Matches "70a", leaves "д" in body
   - Expected: Creates lettered article "70a" (reasonable)

3. **Non-Latin uppercase:** "Članak 70Д"
   - Current: Matches "70", leaves "Д" in body
   - Expected: Same (case-insensitive `[a-z]` with `u` flag only matches Latin)

### Monitoring Recommendations

- Track parsing failures in production logs
- Monitor for unexpected article merging patterns
- Validate chunk_index sequences in ingestion pipeline

## Conclusion

Sprint B2 successfully extends LawParser robustness with minimal code changes (2 characters). The optional delimiter pattern enables handling of malformed article headers while maintaining strict Latin-letter-only article numbering. All downstream processes (merging, indexing, embedding) remain unaffected, ensuring production stability.
