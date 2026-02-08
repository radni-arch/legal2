<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentGenerationRunResource extends JsonResource
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
            'document_type' => $this->document_type,
            'case_id' => $this->case_id,
            'status' => $this->status,
            'final_document' => $this->final_document,
            'final_score' => $this->final_score,
            'total_iterations' => $this->total_iterations,
            'stopped_reason' => $this->stopped_reason,
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'iterations' => DocumentIterationResource::collection($this->whenLoaded('iterations')),
            'context' => new DocumentContextResource($this->whenLoaded('context')),
        ];
    }
}
