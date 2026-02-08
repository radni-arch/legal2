# Sprint 13: UI Component Library - Card Component

## Overview

**Sprint**: 13 - UI Component Library Creation
**Worker**: A - Layout & Container Components
**Component**: Card (1/5 components)
**Status**: ✅ Complete (TDD Methodology)

## Test-Driven Development (TDD) Process

This implementation strictly followed TDD RED-GREEN-REFACTOR methodology as required by the test-driven-development skill:

### RED Phase ✅
1. **Wrote 8 tests FIRST** before any component code existed
2. **Verified tests FAILED** with expected error: `Unable to locate a class or view for component [card]`
3. **Confirmed failure reason**: Component missing (not typos or other issues)

### GREEN Phase ✅
1. **Implemented minimal component code** to pass all 8 tests
2. **Verified all 8 tests PASS** (26 assertions)
3. **No code written before tests** (TDD Iron Law followed)

### REFACTOR Phase
- Code is already clean and minimal
- No duplication detected
- Component follows Laravel Blade best practices

## Component Specification

**File**: `resources/views/components/card.blade.php`
**Props**:
- `title` (optional): Card header text
- `footer` (optional): Footer content slot
- `padding` (default: `p-4`): Padding class
- `variant` (default: `default`): Visual variant (default/dark/bordered)

**Slots**:
- Default slot: Main card content
- `footer`: Footer content (optional)

## Test Coverage (8 Tests, 26 Assertions)

### Test 1: Basic Content Rendering ✅
```php
public function test_card_renders_basic_content(): void
```
**Verifies**:
- Component renders without errors
- Slot content is displayed
- Default `bg-white` background applied
- Default `shadow` class applied

**Usage**:
```blade
<x-card>Test content here</x-card>
```

---

### Test 2: Title Rendering ✅
```php
public function test_card_renders_with_title(): void
```
**Verifies**:
- Title prop renders in `<h3>` tag
- Border separator (`border-b`) exists between title and content
- Title has proper styling

**Usage**:
```blade
<x-card title="Evidence Analysis">Card content</x-card>
```

---

### Test 3: Footer Slot ✅
```php
public function test_card_renders_with_footer(): void
```
**Verifies**:
- Footer slot content renders
- Top border (`border-t`) separates footer from content
- Footer is properly positioned

**Usage**:
```blade
<x-card>
    Main content
    <x-slot:footer>
        <button>Action Button</button>
    </x-slot:footer>
</x-card>
```

---

### Test 4: Dark Variant ✅
```php
public function test_card_supports_dark_variant(): void
```
**Verifies**:
- Dark variant applies `bg-gray-800`
- Dark variant applies `text-white`
- Border colors change to `border-gray-700`

**Usage**:
```blade
<x-card variant="dark">Dark card content</x-card>
```

---

### Test 5: Bordered Variant ✅
```php
public function test_card_supports_bordered_variant(): void
```
**Verifies**:
- Bordered variant has `border-2`
- Border color is `border-gray-200`
- Background is white

**Usage**:
```blade
<x-card variant="bordered">Bordered card</x-card>
```

---

### Test 6: Custom Padding ✅
```php
public function test_card_supports_custom_padding(): void
```
**Verifies**:
- Default padding is `p-4`
- Custom padding can override default
- Padding prop works correctly

**Usage**:
```blade
<!-- Default padding -->
<x-card>Content</x-card>

<!-- Custom padding -->
<x-card padding="p-6">Content</x-card>
```

---

### Test 7: Custom Attributes ✅
```php
public function test_card_merges_custom_attributes(): void
```
**Verifies**:
- Custom classes can be added
- Attributes are merged properly via `$attributes->merge()`
- ID and other HTML attributes work

**Usage**:
```blade
<x-card id="custom-card" class="custom-class">Content</x-card>
```

---

### Test 8: Combined Title and Footer ✅
```php
public function test_card_with_title_and_footer(): void
```
**Verifies**:
- Title and footer can be used together
- All three sections render (title, content, footer)
- Both borders present (`border-b` for title, `border-t` for footer)

**Usage**:
```blade
<x-card title="Analysis Results" variant="dark">
    <p>Main content here</p>
    <x-slot:footer>
        <button>Generate Motion</button>
    </x-slot:footer>
</x-card>
```

## Component Implementation

**File**: `resources/views/components/card.blade.php`

```blade
@props([
    'title' => null,
    'footer' => null,
    'padding' => 'p-4',
    'variant' => 'default' // default, dark, bordered
])

@php
$classes = match($variant) {
    'dark' => 'bg-gray-800 text-white rounded-lg shadow-lg',
    'bordered' => 'bg-white border-2 border-gray-200 rounded-lg',
    default => 'bg-white rounded-lg shadow-md'
};
@endphp

<div {{ $attributes->merge(['class' => "$classes $padding"]) }}>
    @if($title)
    <div class="border-b {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pb-3 mb-3">
        <h3 class="text-lg font-semibold {{ $variant === 'dark' ? 'text-white' : 'text-gray-900' }}">
            {{ $title }}
        </h3>
    </div>
    @endif

    <div class="{{ $variant === 'dark' ? 'text-gray-300' : 'text-gray-800' }}">
        {{ $slot }}
    </div>

    @if($footer)
    <div class="border-t {{ $variant === 'dark' ? 'border-gray-700' : 'border-gray-200' }} pt-3 mt-3">
        {{ $footer }}
    </div>
    @endif
</div>
```

## Real-World Usage Examples

### Example 1: Evidence Analysis Card
```blade
<x-card title="Evidence Analysis" variant="dark">
    <p>Analysis results here...</p>
    <ul class="list-disc ml-4">
        <li>Admissibility: Not Admissible</li>
        <li>Legal Basis: ZKP Članak 9</li>
        <li>Confidence: 95%</li>
    </ul>
    <x-slot:footer>
        <button class="btn success">Generate Suppression Motion</button>
    </x-slot:footer>
</x-card>
```

### Example 2: Case Summary Card
```blade
<x-card title="Case #123-2025" variant="bordered" padding="p-6">
    <div class="space-y-2">
        <p><strong>Defendant:</strong> John Doe</p>
        <p><strong>Charges:</strong> Drug Possession</p>
        <p><strong>Status:</strong> Active</p>
    </div>
</x-card>
```

### Example 3: Misconduct Detection Result
```blade
<x-card variant="dark">
    <h4 class="font-bold mb-2">Misconduct Detected</h4>
    <p>Severity Score: 85/100</p>
    <p class="text-red-400">Type: Fabricated Probable Cause</p>
    <x-slot:footer>
        <div class="flex gap-2">
            <button class="btn danger">Generate Dismissal Motion</button>
            <button class="btn warn">File Ethics Complaint</button>
        </div>
    </x-slot:footer>
</x-card>
```

## Variant Showcase

### Default Variant
```blade
<x-card title="Default Card">
    White background with subtle shadow
</x-card>
```
**Result**: `bg-white rounded-lg shadow-md`

### Dark Variant
```blade
<x-card title="Dark Card" variant="dark">
    Dark background with white text
</x-card>
```
**Result**: `bg-gray-800 text-white rounded-lg shadow-lg`

### Bordered Variant
```blade
<x-card title="Bordered Card" variant="bordered">
    White background with visible border
</x-card>
```
**Result**: `bg-white border-2 border-gray-200 rounded-lg`

## Test Execution Results

```bash
$ ./vendor/bin/phpunit tests/Feature/Components/CardTest.php --testdox

PHPUnit 11.5.42 by Sebastian Bergmann and contributors.

........                                                            8 / 8 (100%)

Time: 00:00.794, Memory: 55.00 MB

Card (Tests\Feature\Components\Card)
 ✔ Card renders basic content
 ✔ Card renders with title
 ✔ Card renders with footer
 ✔ Card supports dark variant
 ✔ Card supports bordered variant
 ✔ Card supports custom padding
 ✔ Card merges custom attributes
 ✔ Card with title and footer

OK!
Tests: 8, Assertions: 26
```

## TDD Verification Checklist

- [x] Every component feature has a test
- [x] Watched each test fail before implementing (RED verified)
- [x] Tests failed for expected reason (component missing)
- [x] Wrote minimal code to pass tests (GREEN verified)
- [x] All tests pass (8/8 tests, 26 assertions)
- [x] No test-only code in production
- [x] Tests use real component rendering
- [x] Edge cases covered (variants, slots, attributes)
- [x] No code written before tests

## Files Created

1. **`tests/Feature/Components/CardTest.php`** (218 lines)
   - 8 comprehensive tests
   - 26 assertions total
   - Full variant coverage

2. **`resources/views/components/card.blade.php`** (31 lines)
   - Minimal implementation
   - 3 variants supported
   - Flexible slot system

3. **`docs/sprint-13-card-component.md`** (this file)
   - Complete documentation
   - Usage examples
   - TDD process verification

## Integration with Existing System

The Card component integrates seamlessly with the AI Legal War Machine's existing UI:

**Legal Playground**: Can wrap analysis results
```blade
<x-card title="Evidence Analysis Results" variant="dark">
    @if($evidenceAnalysisResult)
        <!-- Analysis display -->
    @endif
</x-card>
```

**Textract Manager**: Can display job statuses
```blade
<x-card title="OCR Job #{{ $job->id }}">
    Status: {{ $job->status }}
    <x-slot:footer>
        <button wire:click="retry({{ $job->id }})">Retry</button>
    </x-slot:footer>
</x-card>
```

**Graph Viewer**: Can contain graph visualizations
```blade
<x-card title="Neo4j Graph Statistics" variant="bordered" padding="p-6">
    <div id="graph-container"></div>
</x-card>
```

## Code Quality Metrics

- **Component Lines**: 31 (minimal GREEN implementation)
- **Test Lines**: 218
- **Test-to-Code Ratio**: 7:1 (excellent)
- **Test Coverage**: 100% of component features
- **Assertions per Test**: 3.25 average
- **Cyclomatic Complexity**: Low (simple match/if logic)

## Future Enhancements

While not required for this sprint, potential improvements:

1. **Loading State**: Add `loading` prop for skeleton state
2. **Collapsible**: Add expand/collapse functionality
3. **Icons**: Support icon prop for title
4. **Elevation Levels**: Multiple shadow depths
5. **Color Variants**: Success, warning, error themed cards

## Conclusion

The Card component has been successfully implemented following strict TDD methodology:

- ✅ **8/8 tests passing** (100% success rate)
- ✅ **RED phase**: Tests written first, failures verified
- ✅ **GREEN phase**: Minimal implementation passes all tests
- ✅ **Production-ready** component
- ✅ **Comprehensive documentation**
- ✅ **Real-world usage examples**

The component is ready for immediate use in the AI Legal War Machine application and provides a solid foundation for the remaining Worker A components (Modal, Alert, Badge, Tooltip).

---

**Author**: Claude (TDD Skill)
**Date**: 2025-11-10
**Sprint**: 13
**Worker**: A
**Component**: Card (1/5)
**Methodology**: Test-Driven Development (RED-GREEN-REFACTOR)
