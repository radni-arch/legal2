<?php

namespace App\Services\Hudoc;

class HudocSearchQuery
{
    protected array $filters = [];

    protected int $start = 0;

    protected int $length = 500;

    protected string $sort = 'kpdate Descending';

    protected array $select = [
        'itemid', 'docname', 'appno', 'conclusion', 'importance',
        'kpdate', 'respondent', 'article', 'violation', 'nonviolation',
        'ecli', 'languageisocode', 'doctypebranch', 'kpthesaurus',
        'separateopinion', 'scl', 'externalsources',
    ];

    public static function make(): self
    {
        return new self;
    }

    public function respondent(string $state): self
    {
        $this->filters['respondent'] = [$state];

        return $this;
    }

    public function article(string|array $articles): self
    {
        $this->filters['article'] = (array) $articles;

        return $this;
    }

    public function dateFrom(string $date): self
    {
        $this->filters['kpdate'] = ($this->filters['kpdate'] ?? '')." >= {$date}";

        return $this;
    }

    public function dateTo(string $date): self
    {
        $this->filters['kpdate'] = ($this->filters['kpdate'] ?? '')." <= {$date}";

        return $this;
    }

    public function importance(array $levels): self
    {
        $this->filters['importance'] = $levels;

        return $this;
    }

    public function documentType(string|array $types): self
    {
        $this->filters['documentcollectionid2'] = (array) $types;

        return $this;
    }

    public function judgmentsOnly(): self
    {
        return $this->documentType(['JUDGMENTS']);
    }

    public function language(string $lang = 'ENG'): self
    {
        $this->filters['languageisocode'] = [$lang];

        return $this;
    }

    public function fullText(string $query): self
    {
        $this->filters['fulltext'] = $query;

        return $this;
    }

    public function offset(int $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function limit(int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function sortBy(string $field, string $direction = 'Descending'): self
    {
        $this->sort = "{$field} {$direction}";

        return $this;
    }

    public function toPayload(): array
    {
        $query = [];

        foreach ($this->filters as $field => $value) {
            if ($field === 'fulltext') {
                $query[] = ['term' => $value];
            } else {
                $query[] = [$field => $value];
            }
        }

        return [
            'query' => json_encode(['query' => $query]),
            'select' => implode(',', $this->select),
            'sort' => $this->sort,
            'start' => $this->start,
            'length' => $this->length,
        ];
    }
}
