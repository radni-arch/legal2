<?php

use App\Http\Controllers\EvidenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Evidence Analysis API Routes
|--------------------------------------------------------------------------
|
| Evidence analysis endpoints for Croatian legal system (ZKP & Ustav RH)
|
| Note: These routes are included within a prefix('evidence') group in api.php
|
*/

// Comprehensive evidence analysis
Route::post('/analyze/{caseId}', [EvidenceController::class, 'analyzeEvidence'])
    ->name('evidence.analyze');

// Generate suppression motion
Route::post('/suppress-motion/{caseId}', [EvidenceController::class, 'generateSuppressionMotion'])
    ->name('evidence.suppress-motion');

// Check admissibility (ZKP)
Route::post('/check-admissibility/{caseId}', [EvidenceController::class, 'checkAdmissibility'])
    ->name('evidence.check-admissibility');

// Detect constitutional violations (Ustav RH)
Route::post('/constitutional-violations/{caseId}', [EvidenceController::class, 'detectConstitutionalViolations'])
    ->name('evidence.constitutional-violations');

// Get alternative interpretations
Route::post('/alternative-interpretations/{caseId}', [EvidenceController::class, 'getAlternativeInterpretations'])
    ->name('evidence.alternative-interpretations');

// Recontextualize evidence
Route::post('/recontextualize/{caseId}', [EvidenceController::class, 'recontextualizeEvidence'])
    ->name('evidence.recontextualize');
