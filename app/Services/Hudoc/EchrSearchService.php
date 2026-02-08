<?php

namespace App\Services\Hudoc;

use App\Models\EchrArticle;
use App\Models\EchrCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EchrSearchService
{
    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = EchrCase::query();

        // Text search
        if (! empty($filters['q'])) {
            $query->where(function (Builder $q) use ($filters) {
                $search = $filters['q'];
                $q->where('case_name', 'ILIKE', "%{$search}%")
                    ->orWhere('full_text', 'ILIKE', "%{$search}%")
                    ->orWhere('application_number', 'LIKE', "%{$search}%");
            });
        }

        // State filter
        if (! empty($filters['state'])) {
            $query->where('respondent_state', $filters['state']);
        }

        // Article filter
        if (! empty($filters['article'])) {
            $query->whereHas('articles', function (Builder $q) use ($filters) {
                $q->where('article_code', $filters['article']);
            });
        }

        // Violation filter
        if (! empty($filters['violation'])) {
            $query->withViolation($filters['violation']);
        }

        // Date range
        if (! empty($filters['from'])) {
            $query->where('judgment_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('judgment_date', '<=', $filters['to']);
        }

        // Importance
        if (! empty($filters['importance'])) {
            $query->whereIn('importance', (array) $filters['importance']);
        }

        // Document type
        if (! empty($filters['type'])) {
            $query->where('document_type', $filters['type']);
        }

        // Only with full text
        if (! empty($filters['with_text'])) {
            $query->withFullText();
        }

        return $query
            ->with(['articles'])
            ->orderByDesc('judgment_date')
            ->paginate($perPage);
    }

    public function findRelatedCases(EchrCase $case, int $limit = 10): Collection
    {
        // Find cases with same articles and respondent
        $articleCodes = $case->articles->pluck('article_code');

        return EchrCase::where('id', '!=', $case->id)
            ->where(function (Builder $q) use ($case, $articleCodes) {
                $q->where('respondent_state', $case->respondent_state)
                    ->orWhereHas('articles', function ($aq) use ($articleCodes) {
                        $aq->whereIn('article_code', $articleCodes);
                    });
            })
            ->orderByDesc('importance')
            ->orderByDesc('judgment_date')
            ->limit($limit)
            ->get();
    }

    public function getStatsByState(string $state): array
    {
        $query = EchrCase::where('respondent_state', $state);

        return [
            'total_cases' => $query->count(),
            'judgments' => $query->clone()->judgments()->count(),
            'violations_by_article' => $this->getViolationsByArticle($state),
            'cases_by_year' => $this->getCasesByYear($state),
            'important_cases' => $query->clone()->important()->count(),
        ];
    }

    protected function getViolationsByArticle(string $state): array
    {
        return EchrArticle::withCount(['cases' => function ($q) use ($state) {
            $q->where('respondent_state', $state)
                ->wherePivot('status', 'VIOLATION');
        }])
            ->orderByDesc('cases_count')
            ->get()
            ->mapWithKeys(fn ($a) => [$a->article_code => $a->cases_count])
            ->toArray();
    }

    protected function getCasesByYear(string $state): array
    {
        return EchrCase::where('respondent_state', $state)
            ->selectRaw('EXTRACT(YEAR FROM judgment_date) as year, COUNT(*) as count')
            ->whereNotNull('judgment_date')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('count', 'year')
            ->toArray();
    }

    public function searchCroatianArticle6(string $query = ''): Collection
    {
        return EchrCase::croatia()
            ->withViolation('6')
            ->when($query, fn ($q) => $q->where('full_text', 'ILIKE', "%{$query}%"))
            ->orderByDesc('importance')
            ->orderByDesc('judgment_date')
            ->get();
    }

    public function searchCroatianArticle8(string $query = ''): Collection
    {
        return EchrCase::croatia()
            ->withViolation('8')
            ->when($query, fn ($q) => $q->where('full_text', 'ILIKE', "%{$query}%"))
            ->orderByDesc('importance')
            ->orderByDesc('judgment_date')
            ->get();
    }
}
