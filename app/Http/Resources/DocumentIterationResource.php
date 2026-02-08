<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentIterationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iteration_number' => $this->iteration_number,
            'phase' => $this->phase,
            'document_content' => $this->document_content,
            'feedback' => $this->feedback,
            'scores' => $this->scores,
            'weighted_score' => $this->weighted_score,
            'improvement_delta' => $this->improvement_delta,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
