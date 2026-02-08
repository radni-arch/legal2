<?php

namespace Tests\Unit\Agents;

use Tests\TestCase;

class AgentCypherQuerySafetyTest extends TestCase
{
    private array $agentFiles = [
        'app/Agents/AutonomousResearchAgent.php',
        'app/Agents/DecisionDiscoveryAgent.php',
        'app/Agents/Specialists/PrecedentAnalystAgent.php',
        'app/Agents/Specialists/ResearchSpecialistAgent.php',
    ];

    /** @test */
    public function agents_do_not_concatenate_variables_into_cypher_queries(): void
    {
        $unsafePatterns = [
            '/\$\w+\s*\.\s*[\'"].*MATCH/i',  // $var . "MATCH..."
            '/[\'"].*MATCH.*[\'"].*\.\s*\$\w+/i',  // "MATCH..." . $var
            '/sprintf\s*\(\s*[\'"].*MATCH/i',  // sprintf("MATCH...
            '/"[^"\n]{0,500}\{\$[^}]*\}[^"\n]{0,200}MATCH|"[^"\n]{0,500}MATCH[^"\n]{0,200}\{\$[^}]*\}/i',  // "{$var}...MATCH" within reasonable distance (same line or nearby)
        ];

        $violations = [];

        foreach ($this->agentFiles as $file) {
            $path = base_path($file);
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            foreach ($unsafePatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "$file matches unsafe pattern: $pattern";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found potential Cypher injection vulnerabilities:\n" . implode("\n", $violations)
        );
    }

    /** @test */
    public function agents_use_parameter_binding_for_dynamic_values(): void
    {
        foreach ($this->agentFiles as $file) {
            $path = base_path($file);
            if (!file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);

            // If file contains Cypher queries, should have parameters
            if (preg_match('/MATCH\s*\(/i', $content)) {
                // Check for Cypher parameter syntax ($param in query string)
                // AND parameters array structure
                $hasCypherParamSyntax = preg_match('/["\'].*\$\w+.*MATCH|MATCH.*\$\w+.*["\']/i', $content) === 1;
                $hasParametersArray = preg_match('/["\']parameters["\']\s*=>/i', $content) === 1;

                $hasProperParameterBinding = $hasCypherParamSyntax && $hasParametersArray;

                $this->assertTrue(
                    $hasProperParameterBinding,
                    "Agent $file uses Cypher but may not use proper parameter binding (needs both \$param syntax in query and 'parameters' => array)"
                );
            }
        }
    }
}
