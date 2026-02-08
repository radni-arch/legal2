<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verify that old classes replaced by the unified LegalArtillery system
 * carry @deprecated annotations to guide developers to the new APIs.
 *
 * Task 25: Cleanup Redundant Code - Sprint 3 Legal Artillery Integration
 */
class DeprecatedClassAnnotationsTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider deprecatedClassProvider
     */
    public function old_class_has_deprecated_annotation(string $className, string $replacementHint): void
    {
        $this->assertTrue(
            class_exists($className),
            "Class {$className} should still exist (not deleted while in use)"
        );

        $reflection = new \ReflectionClass($className);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse(
            $docComment,
            "Class {$className} should have a PHPDoc comment"
        );

        $this->assertStringContainsString(
            '@deprecated',
            $docComment,
            "Class {$className} should have a @deprecated annotation"
        );

        $this->assertStringContainsString(
            $replacementHint,
            $docComment,
            "Class {$className} @deprecated annotation should reference replacement: {$replacementHint}"
        );
    }

    public static function deprecatedClassProvider(): array
    {
        return [
            'RecursiveDocumentWritingAgent replaced by LegalArtilleryOrchestrator' => [
                \App\Agents\RecursiveDocumentWritingAgent::class,
                'LegalArtilleryOrchestrator',
            ],
        ];
    }
}
