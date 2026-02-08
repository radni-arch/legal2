<?php

namespace App\Http\Controllers;

use App\Http\Requests\McpTools\CaseSearchRequest;
use App\Http\Requests\McpTools\DecisionGetRequest;
use App\Http\Requests\McpTools\DecisionSearchRequest;
use App\Http\Requests\McpTools\LawGetArticleRequest;
use App\Http\Requests\McpTools\LawSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CaseDocument;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use Illuminate\Http\JsonResponse;

class McpToolsController extends Controller
{
    /**
     * Search laws by query with optional filters.
     */
    public function lawSearch(LawSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Law::query();

        if (! empty($validated['doc_id'])) {
            $query->where('doc_id', $validated['doc_id']);
        }

        if (! empty($validated['law_number'])) {
            $query->where('law_number', 'like', '%'.$validated['law_number'].'%');
        }

        if (! empty($validated['jurisdiction'])) {
            $query->where('jurisdiction', $validated['jurisdiction']);
        }

        if (! empty($validated['country'])) {
            $query->where('country', $validated['country']);
        }

        if (! empty($validated['language'])) {
            $query->where('language', $validated['language']);
        }

        if (! empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (! empty($validated['query'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('title', 'like', '%'.$validated['query'].'%')
                    ->orWhere('content', 'like', '%'.$validated['query'].'%');
            });
        }

        $limit = min((int) ($validated['limit'] ?? 10), 100);
        $page = (int) ($validated['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $total = $query->count();

        $laws = $query->select([
            'id', 'doc_id', 'title', 'law_number', 'jurisdiction',
            'country', 'language', 'promulgation_date', 'effective_date',
            'repeal_date', 'tags', 'source_url', 'chunk_index',
        ])
            ->orderBy('doc_id')
            ->orderBy('chunk_index')
            ->skip($offset)
            ->take($limit)
            ->get();

        return ApiResponse::paginated($laws, [
            'total' => $total,
            'count' => $laws->count(),
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * Get specific law article by doc_id and optional filters.
     */
    public function lawGetArticle(LawGetArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Law::where('doc_id', $validated['doc_id']);

        if (isset($validated['number'])) {
            $query->where('chunk_index', (int) $validated['number']);
        }

        if (! empty($validated['chapter'])) {
            $query->where('chapter', 'like', '%'.$validated['chapter'].'%');
        }

        if (! empty($validated['section'])) {
            $query->where('section', 'like', '%'.$validated['section'].'%');
        }

        $articles = $query->orderBy('chunk_index')->get([
            'id', 'doc_id', 'title', 'law_number', 'chapter', 'section',
            'chunk_index', 'content', 'metadata', 'source_url',
        ]);

        if ($articles->isEmpty()) {
            return ApiResponse::notFound(
                sprintf('No articles found for doc_id "%s" with the given criteria.', $validated['doc_id'])
            );
        }

        return ApiResponse::success([
            'doc_id' => $validated['doc_id'],
            'total_chunks' => $articles->count(),
            'articles' => $articles,
        ]);
    }

    /**
     * Search court decisions by query with optional filters.
     */
    public function decisionSearch(DecisionSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = CourtDecision::query();

        if (! empty($validated['case_number'])) {
            $query->where('case_number', 'like', '%'.$validated['case_number'].'%');
        }

        if (! empty($validated['court'])) {
            $query->where('court', 'like', '%'.$validated['court'].'%');
        }

        if (! empty($validated['jurisdiction'])) {
            $query->where('jurisdiction', $validated['jurisdiction']);
        }

        if (! empty($validated['judge'])) {
            $query->where('judge', 'like', '%'.$validated['judge'].'%');
        }

        if (! empty($validated['decision_type'])) {
            $query->where('decision_type', $validated['decision_type']);
        }

        if (! empty($validated['register'])) {
            $query->where('register', $validated['register']);
        }

        if (! empty($validated['ecli'])) {
            $query->where('ecli', $validated['ecli']);
        }

        if (! empty($validated['finality'])) {
            $query->where('finality', $validated['finality']);
        }

        if (! empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (! empty($validated['date_from'])) {
            $query->where('decision_date', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('decision_date', '<=', $validated['date_to']);
        }

        if (! empty($validated['query'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('title', 'like', '%'.$validated['query'].'%')
                    ->orWhere('description', 'like', '%'.$validated['query'].'%')
                    ->orWhere('case_number', 'like', '%'.$validated['query'].'%');
            });
        }

        $limit = min((int) ($validated['limit'] ?? 10), 100);
        $page = (int) ($validated['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $total = $query->count();

        $decisions = $query->select([
            'id', 'case_number', 'title', 'court', 'jurisdiction',
            'judge', 'decision_date', 'publication_date', 'decision_type',
            'register', 'finality', 'ecli', 'tags',
        ])
            ->orderBy('decision_date', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return ApiResponse::paginated($decisions, [
            'total' => $total,
            'count' => $decisions->count(),
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * Get specific court decision by ID.
     */
    public function decisionGet(DecisionGetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $decision = CourtDecision::find($validated['id']);

        if (! $decision) {
            return ApiResponse::notFound(
                sprintf('Court decision with ID "%s" not found.', $validated['id'])
            );
        }

        $result = [
            'decision' => [
                'id' => $decision->id,
                'case_number' => $decision->case_number,
                'title' => $decision->title,
                'court' => $decision->court,
                'jurisdiction' => $decision->jurisdiction,
                'judge' => $decision->judge,
                'decision_date' => $decision->decision_date?->format('Y-m-d'),
                'publication_date' => $decision->publication_date?->format('Y-m-d'),
                'decision_type' => $decision->decision_type,
                'register' => $decision->register,
                'finality' => $decision->finality,
                'ecli' => $decision->ecli,
                'tags' => $decision->tags,
                'description' => $decision->description,
                'created_at' => $decision->created_at?->toIso8601String(),
                'updated_at' => $decision->updated_at?->toIso8601String(),
            ],
        ];

        if ($validated['include_documents'] ?? true) {
            $documentsQuery = $decision->documents();

            if ($validated['include_content'] ?? false) {
                $documents = $documentsQuery->orderBy('chunk_index')->get();
            } else {
                $documents = $documentsQuery->select([
                    'id', 'decision_id', 'doc_id', 'title', 'category',
                    'author', 'language', 'tags', 'chunk_index', 'metadata',
                    'source', 'source_id',
                ])->orderBy('chunk_index')->get();
            }

            $result['documents'] = [
                'total_chunks' => $documents->count(),
                'items' => $documents,
            ];
        }

        return ApiResponse::success($result);
    }

    /**
     * Search legal cases (PRIVATE - requires authentication).
     */
    public function caseSearch(CaseSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (($validated['search_documents'] ?? true) && ! empty($validated['query'])) {
            return $this->searchCaseDocuments($validated);
        }

        return $this->searchCases($validated);
    }

    private function searchCases(array $validated): JsonResponse
    {
        $query = LegalCase::query();

        if (! empty($validated['case_id'])) {
            $query->where('id', $validated['case_id']);
        }

        if (! empty($validated['case_number'])) {
            $query->where('case_number', 'like', '%'.$validated['case_number'].'%');
        }

        if (! empty($validated['client_name'])) {
            $query->where('client_name', 'like', '%'.$validated['client_name'].'%');
        }

        if (! empty($validated['opponent_name'])) {
            $query->where('opponent_name', 'like', '%'.$validated['opponent_name'].'%');
        }

        if (! empty($validated['court'])) {
            $query->where('court', 'like', '%'.$validated['court'].'%');
        }

        if (! empty($validated['jurisdiction'])) {
            $query->where('jurisdiction', $validated['jurisdiction']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (! empty($validated['query'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('title', 'like', '%'.$validated['query'].'%')
                    ->orWhere('description', 'like', '%'.$validated['query'].'%')
                    ->orWhere('case_number', 'like', '%'.$validated['query'].'%');
            });
        }

        $limit = min((int) ($validated['limit'] ?? 10), 100);
        $page = (int) ($validated['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $total = $query->count();

        $cases = $query->select([
            'id', 'case_number', 'title', 'client_name', 'opponent_name',
            'court', 'jurisdiction', 'judge', 'filing_date', 'status', 'tags',
        ])
            ->orderBy('filing_date', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return ApiResponse::paginated($cases, [
            'total' => $total,
            'count' => $cases->count(),
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $limit),
        ], null, ['search_type' => 'cases']);
    }

    private function searchCaseDocuments(array $validated): JsonResponse
    {
        $query = CaseDocument::query();

        if (! empty($validated['case_id'])) {
            $query->where('case_id', $validated['case_id']);
        }

        if (! empty($validated['case_number']) || ! empty($validated['client_name']) ||
            ! empty($validated['opponent_name']) || ! empty($validated['court']) ||
            ! empty($validated['jurisdiction']) || ! empty($validated['status'])) {

            $query->whereHas('case', function ($q) use ($validated) {
                if (! empty($validated['case_number'])) {
                    $q->where('case_number', 'like', '%'.$validated['case_number'].'%');
                }
                if (! empty($validated['client_name'])) {
                    $q->where('client_name', 'like', '%'.$validated['client_name'].'%');
                }
                if (! empty($validated['opponent_name'])) {
                    $q->where('opponent_name', 'like', '%'.$validated['opponent_name'].'%');
                }
                if (! empty($validated['court'])) {
                    $q->where('court', 'like', '%'.$validated['court'].'%');
                }
                if (! empty($validated['jurisdiction'])) {
                    $q->where('jurisdiction', $validated['jurisdiction']);
                }
                if (! empty($validated['status'])) {
                    $q->where('status', $validated['status']);
                }
            });
        }

        if (! empty($validated['query'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('content', 'like', '%'.$validated['query'].'%')
                    ->orWhere('title', 'like', '%'.$validated['query'].'%');
            });
        }

        if (! empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        $limit = min((int) ($validated['limit'] ?? 10), 100);
        $page = (int) ($validated['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $total = $query->count();

        if ($validated['include_content'] ?? false) {
            $documents = $query->with('case:id,case_number,title')
                ->orderBy('chunk_index')
                ->skip($offset)
                ->take($limit)
                ->get();
        } else {
            $documents = $query->select([
                'id', 'case_id', 'doc_id', 'title', 'category', 'author',
                'language', 'tags', 'chunk_index', 'metadata', 'source',
            ])
                ->with('case:id,case_number,title')
                ->orderBy('chunk_index')
                ->skip($offset)
                ->take($limit)
                ->get();
        }

        return ApiResponse::paginated($documents, [
            'total' => $total,
            'count' => $documents->count(),
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => (int) ceil($total / $limit),
        ], null, ['search_type' => 'documents']);
    }
}
