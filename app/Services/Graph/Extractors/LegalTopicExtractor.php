<?php

namespace App\Services\Graph\Extractors;

class LegalTopicExtractor
{
    /**
     * Croatian legal topic taxonomy with keywords for matching
     */
    protected array $topicTaxonomy = [
        'građansko_pravo' => [
            'name' => 'Građansko pravo',
            'name_en' => 'Civil Law',
            'keywords' => ['ugovor', 'naknada štete', 'obveza', 'vlasništvo', 'založno pravo', 'najam', 'zakup', 'kupoprodaja', 'dar', 'hipoteka', 'služnost', 'posjed'],
            'children' => [
                'obvezno_pravo' => [
                    'name' => 'Obvezno pravo',
                    'keywords' => ['ugovor', 'obveza', 'dužnik', 'vjerovnik', 'ispunjenje', 'raskid', 'naknada'],
                ],
                'stvarno_pravo' => [
                    'name' => 'Stvarno pravo',
                    'keywords' => ['vlasništvo', 'posjed', 'služnost', 'založno pravo', 'hipoteka', 'nekretnina'],
                ],
            ],
        ],
        'kazneno_pravo' => [
            'name' => 'Kazneno pravo',
            'name_en' => 'Criminal Law',
            'keywords' => ['kazneno djelo', 'optuženik', 'okrivljenik', 'kazna', 'zatvor', 'presuda', 'krivnja', 'ubojstvo', 'krađa', 'prijevara'],
            'children' => [],
        ],
        'radno_pravo' => [
            'name' => 'Radno pravo',
            'name_en' => 'Labor Law',
            'keywords' => ['radnik', 'poslodavac', 'ugovor o radu', 'otkaz', 'plaća', 'otpremnina', 'radno vrijeme', 'godišnji odmor'],
            'children' => [],
        ],
        'upravno_pravo' => [
            'name' => 'Upravno pravo',
            'name_en' => 'Administrative Law',
            'keywords' => ['rješenje', 'upravni postupak', 'žalba', 'javna uprava', 'dozvola', 'inspekcija', 'porez'],
            'children' => [],
        ],
        'trgovacko_pravo' => [
            'name' => 'Trgovačko pravo',
            'name_en' => 'Commercial Law',
            'keywords' => ['trgovačko društvo', 'd.o.o.', 'd.d.', 'dionice', 'stečaj', 'likvidacija', 'prokura', 'zastupanje'],
            'children' => [],
        ],
        'obiteljsko_pravo' => [
            'name' => 'Obiteljsko pravo',
            'name_en' => 'Family Law',
            'keywords' => ['brak', 'razvod', 'dijete', 'uzdržavanje', 'skrbništvo', 'posvojenje', 'roditeljska skrb'],
            'children' => [],
        ],
    ];

    /**
     * Extract legal topics from decision text
     *
     * @param string $text The decision text to analyze
     * @return array Array of ['topic_id' => string, 'name' => string, 'relevance' => float]
     */
    public function extract(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $normalizedText = mb_strtolower($text);
        $topics = [];

        foreach ($this->topicTaxonomy as $topicId => $topic) {
            $matches = $this->countKeywordMatches($normalizedText, $topic['keywords']);

            if ($matches > 0) {
                $relevance = min(1.0, $matches / 10); // Cap at 1.0, 10+ matches = full relevance
                $topics[] = [
                    'topic_id' => 'topic_' . md5($topic['name']),
                    'name' => $topic['name'],
                    'relevance' => round($relevance, 2),
                ];
            }

            // Check child topics
            foreach ($topic['children'] ?? [] as $childId => $child) {
                $childMatches = $this->countKeywordMatches($normalizedText, $child['keywords']);
                if ($childMatches > 0) {
                    $relevance = min(1.0, $childMatches / 5); // Child topics need fewer matches
                    $topics[] = [
                        'topic_id' => 'topic_' . md5($child['name']),
                        'name' => $child['name'],
                        'parent_id' => 'topic_' . md5($topic['name']),
                        'relevance' => round($relevance, 2),
                    ];
                }
            }
        }

        // Sort by relevance descending
        usort($topics, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return $topics;
    }

    /**
     * Count keyword matches in text
     */
    protected function countKeywordMatches(string $text, array $keywords): int
    {
        $count = 0;
        foreach ($keywords as $keyword) {
            $count += substr_count($text, mb_strtolower($keyword));
        }
        return $count;
    }

    /**
     * Get the full topic taxonomy
     */
    public function getTaxonomy(): array
    {
        return $this->topicTaxonomy;
    }
}
