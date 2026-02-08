<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LegalCase extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table;

    protected $fillable = [
        'id', 'case_number', 'title', 'client_name', 'opponent_name', 'court', 'jurisdiction',
        'judge', 'filing_date', 'status', 'tags', 'description', 'case_type', 'user_id', 'team_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'filing_date' => 'date',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('vizra-adk.tables.cases', 'cases');
    }

    // Relations
    public function caseDocument()
    {
        return $this->hasOne(CaseDocument::class, 'case_id');
    }

    public function documents()
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function uploads()
    {
        return $this->hasMany(CaseDocumentUpload::class, 'case_id');
    }

    public function features()
    {
        return $this->hasOne(CaseFeature::class, 'case_id');
    }

    public function predictions()
    {
        return $this->hasMany(CasePrediction::class, 'case_id');
    }

    public function strategies()
    {
        return $this->hasMany(CaseStrategy::class, 'case_id');
    }

    public function textractJobs()
    {
        return $this->hasMany(TextractJob::class, 'case_id');
    }

    public function textractDocuments()
    {
        return $this->hasMany(TextractDocument::class, 'case_id');
    }

    // Temporary stub for Evidence relationship - returns empty collection
    // Evidence model is a stub until full implementation
    public function evidence()
    {
        return $this->hasMany(Evidence::class, 'case_id');
    }

    /**
     * Get the user who owns this case
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'case_user', 'case_id', 'user_id')
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted()
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::ulid();
            }
        });
    }
}
