<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VectorCast implements CastsAttributes
{
    /**
     * Cast the given value (from database string to PHP array).
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        // If it's already an array, return it
        if (is_array($value)) {
            return $value;
        }

        // Handle DB::raw Expression objects - refresh from database
        if ($value instanceof \Illuminate\Database\Query\Expression) {
            // Force a refresh from the database to get the actual value
            $model->refresh();
            // Get the value from attributes after refresh
            $value = $model->getAttributes()[$key] ?? null;

            if ($value === null) {
                return null;
            }

            if (is_array($value)) {
                return $value;
            }
        }

        // If still not a string (shouldn't happen after refresh), return null
        if (! is_string($value)) {
            return null;
        }

        // Parse pgvector format: "[0.1,0.2,0.3]" -> [0.1, 0.2, 0.3]
        $value = trim($value, '[]');
        if ($value === '') {
            return [];
        }

        return array_map('floatval', explode(',', $value));
    }

    /**
     * Prepare the given value for storage (from PHP array to database vector).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return $value;
        }

        // For PostgreSQL with pgvector, use DB::raw() to cast to vector type
        if (DB::connection()->getDriverName() === 'pgsql') {
            $jsonEncoded = json_encode($value);

            return DB::raw("'{$jsonEncoded}'::vector");
        }

        // For other databases, store as JSON
        return json_encode($value);
    }
}
