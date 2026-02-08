<?php

namespace App\Repositories;

use App\Models\EoglasnaKeywordMatch;
use Carbon\Carbon;

class EoglasnaKeywordMatchRepository
{
    public function recordMatch(int $keywordId, string $noticeUuid, ?array $metadata = null): EoglasnaKeywordMatch
    {
        // Avoid duplicate matches: we can firstOrCreate by (keyword_id, notice_uuid)
        $match = EoglasnaKeywordMatch::firstOrNew([
            'keyword_id' => $keywordId,
            'notice_uuid' => $noticeUuid,
        ]);

        if (! $match->exists) {
            $match->matched_at = Carbon::now();
        }

        // Only update matched_fields if metadata was explicitly provided
        if ($metadata !== null) {
            // If empty array provided, preserve existing fields for updates, set empty for new records
            if (empty($metadata) && $match->exists) {
                // Empty array on update: preserve existing fields
                // (do nothing - keep $match->matched_fields as is)
            } else {
                // New record with empty array, or any record with non-empty array: set the value
                $match->matched_fields = $metadata;
            }
        }
        // If metadata is null, keep existing matched_fields (or null for new records)

        $match->save();

        return $match;
    }
}
