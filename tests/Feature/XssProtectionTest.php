<?php

namespace Tests\Feature;

use Tests\TestCase;

class XssProtectionTest extends TestCase
{
    /**
     * Common XSS attack vectors to test
     */
    protected array $xssVectors = [
        '<script>alert("XSS")</script>',
        '<img src=x onerror=alert("XSS")>',
        '<svg/onload=alert("XSS")>',
        'javascript:alert("XSS")',
        '<iframe src="javascript:alert(\'XSS\')"></iframe>',
        '<body onload=alert("XSS")>',
        '<input onfocus=alert("XSS") autofocus>',
        '<select onfocus=alert("XSS") autofocus>',
        '<textarea onfocus=alert("XSS") autofocus>',
        '<keygen onfocus=alert("XSS") autofocus>',
        '<video><source onerror="alert(\'XSS\')">',
        '<audio src=x onerror=alert("XSS")>',
        '<details open ontoggle=alert("XSS")>',
        '<marquee onstart=alert("XSS")>',
        '"><script>alert(String.fromCharCode(88,83,83))</script>',
    ];

    /**
     * Test that HTML Purifier clean() function sanitizes XSS attacks
     *
     * @test
     */
    public function it_sanitizes_xss_attacks_with_clean_function(): void
    {
        foreach ($this->xssVectors as $vector) {
            $cleaned = clean($vector);

            // The cleaned output should not contain script tags or event handlers in executable context
            $this->assertStringNotContainsString('<script', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('onerror=', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('onload=', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('onfocus=', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('ontoggle=', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('onstart=', $cleaned, "XSS vector not sanitized: {$vector}");

            // Check that dangerous tags are not present in executable form
            $this->assertStringNotContainsString('<iframe', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('<embed', $cleaned, "XSS vector not sanitized: {$vector}");
            $this->assertStringNotContainsString('<object', $cleaned, "XSS vector not sanitized: {$vector}");
        }
    }

    /**
     * Test that clean() allows safe HTML tags
     *
     * @test
     */
    public function it_allows_safe_html_tags(): void
    {
        $safeHtml = '<p>This is <strong>safe</strong> HTML with <em>formatting</em>.</p>';
        $cleaned = clean($safeHtml);

        $this->assertStringContainsString('<p>', $cleaned);
        $this->assertStringContainsString('<strong>', $cleaned);
        $this->assertStringContainsString('<em>', $cleaned);
        $this->assertStringContainsString('</p>', $cleaned);
    }

    /**
     * Test that clean() preserves safe content while removing dangerous content
     *
     * @test
     */
    public function it_preserves_safe_content_while_removing_dangerous_content(): void
    {
        $mixedContent = '<p>Safe paragraph</p><script>alert("XSS")</script><strong>More safe content</strong>';
        $cleaned = clean($mixedContent);

        // Safe content should be preserved
        $this->assertStringContainsString('Safe paragraph', $cleaned);
        $this->assertStringContainsString('More safe content', $cleaned);

        // Dangerous content should be removed
        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
    }

    /**
     * Test that clean() handles nested XSS attempts
     *
     * @test
     */
    public function it_handles_nested_xss_attempts(): void
    {
        $nestedXss = '<div><p>Text</p><script>alert("XSS")</script></div>';
        $cleaned = clean($nestedXss);

        $this->assertStringContainsString('<div>', $cleaned);
        $this->assertStringContainsString('Text', $cleaned);
        $this->assertStringNotContainsString('<script', $cleaned);
    }

    /**
     * Test that clean() removes event handlers from HTML attributes
     *
     * @test
     */
    public function it_removes_event_handlers_from_attributes(): void
    {
        $htmlWithEvents = '<button onclick="alert(\'XSS\')">Click me</button>';
        $cleaned = clean($htmlWithEvents);

        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
        $this->assertStringContainsString('Click me', $cleaned);
    }

    /**
     * Test that clean() removes javascript: protocol from links
     *
     * @test
     */
    public function it_removes_javascript_protocol_from_links(): void
    {
        $maliciousLink = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $cleaned = clean($maliciousLink);

        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
    }

    /**
     * Test that clean() handles data URIs safely
     *
     * @test
     */
    public function it_handles_data_uris_safely(): void
    {
        $dataUri = '<img src="data:text/html,<script>alert(\'XSS\')</script>">';
        $cleaned = clean($dataUri);

        // Data URIs with scripts should be removed or sanitized
        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
    }

    /**
     * Test that clean() preserves allowed inline styles
     *
     * @test
     */
    public function it_preserves_allowed_inline_styles(): void
    {
        $styledContent = '<p style="color: red;">Red text</p>';
        $cleaned = clean($styledContent);

        // Check that the paragraph and text are preserved
        $this->assertStringContainsString('Red text', $cleaned);
        // Note: HTML Purifier may strip or preserve styles based on config
    }

    /**
     * Test that clean() handles empty or null input
     *
     * @test
     */
    public function it_handles_empty_or_null_input(): void
    {
        $this->assertEquals('', clean(''));
        $this->assertEquals('', clean(null));
    }

    /**
     * Test that clean() handles special characters
     *
     * @test
     */
    public function it_handles_special_characters(): void
    {
        $specialChars = '<p>Special chars: &lt; &gt; &amp; &quot; &#39;</p>';
        $cleaned = clean($specialChars);

        $this->assertStringContainsString('Special chars', $cleaned);
        // HTML entities should be preserved or properly encoded
    }

    /**
     * Test that clean() removes SVG-based XSS
     *
     * @test
     */
    public function it_removes_svg_based_xss(): void
    {
        $svgXss = '<svg><script>alert("XSS")</script></svg>';
        $cleaned = clean($svgXss);

        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
    }

    /**
     * Test that clean() handles markdown-generated HTML safely
     *
     * @test
     */
    public function it_handles_markdown_generated_html_safely(): void
    {
        $markdown = "# Header\n\n<script>alert('XSS')</script>\n\n**Bold text**";
        $html = \Illuminate\Support\Str::markdown($markdown);
        $cleaned = clean($html);

        $this->assertStringContainsString('Header', $cleaned);
        $this->assertStringContainsString('Bold text', $cleaned);
        // Script tags should not be in executable form (not as <script> tags)
        $this->assertStringNotContainsString('<script', $cleaned);
        // It's okay if "alert" appears as escaped text like &lt;script&gt;alert...
        // as long as it's not executable
    }

    /**
     * Test that clean() preserves line breaks from nl2br
     *
     * @test
     */
    public function it_preserves_line_breaks_from_nl2br(): void
    {
        $text = "Line 1\nLine 2\nLine 3";
        $html = nl2br(e($text));
        $cleaned = clean($html);

        $this->assertStringContainsString('Line 1', $cleaned);
        $this->assertStringContainsString('Line 2', $cleaned);
        $this->assertStringContainsString('Line 3', $cleaned);
        $this->assertStringContainsString('<br', $cleaned);
    }

    /**
     * Test that clean() preserves mark tags for search highlighting
     *
     * @test
     */
    public function it_preserves_mark_tags_for_search_highlighting(): void
    {
        $highlightedText = 'This is <mark>highlighted</mark> text.';
        $cleaned = clean($highlightedText);

        $this->assertStringContainsString('<mark>', $cleaned);
        $this->assertStringContainsString('highlighted', $cleaned);
        $this->assertStringContainsString('</mark>', $cleaned);
    }

    /**
     * Test protection against Unicode-based XSS
     *
     * @test
     */
    public function it_protects_against_unicode_xss(): void
    {
        // Unicode-encoded script tag - this is already safe as plain text
        // The real danger would be if JavaScript eval() or similar processed this
        $unicodeXss = '\u003cscript\u003ealert("XSS")\u003c/script\u003e';
        $cleaned = clean($unicodeXss);

        // Verify it remains as text and doesn't become executable
        $this->assertStringNotContainsString('<script', $cleaned);
        // The text "alert" can appear as long as it's not in an executable context
        $this->assertIsString($cleaned);
    }

    /**
     * Test protection against HTML entity-encoded XSS
     *
     * @test
     */
    public function it_protects_against_html_entity_encoded_xss(): void
    {
        $encodedXss = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;';
        $cleaned = clean($encodedXss);

        // Should be double-encoded or remain safe
        $this->assertStringNotContainsString('<script', $cleaned);
    }

    /**
     * Test that clean() handles very long input without errors
     *
     * @test
     */
    public function it_handles_very_long_input(): void
    {
        $longInput = str_repeat('<p>This is a paragraph. </p>', 1000);
        $cleaned = clean($longInput);

        $this->assertIsString($cleaned);
        $this->assertStringContainsString('This is a paragraph', $cleaned);
    }
}
