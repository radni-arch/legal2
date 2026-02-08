<?php

use App\Http\Controllers\MisconductController;
use Illuminate\Support\Facades\Route;

/**
 * Prosecutorial Misconduct Module Routes
 *
 * Endpoints for detecting prosecutorial misconduct and generating
 * legal actions (dismissal motions, complaints, appeals).
 *
 * All routes are prefixed with '/api/misconduct'
 */
Route::prefix('misconduct')->middleware(['api.token', 'throttle:60,1'])->group(function () {
    /**
     * Analyze case for prosecutorial misconduct
     *
     * POST /api/misconduct/analyze/{caseId}
     *
     * Request body (optional):
     * {
     *     "options": {
     *         "include_patterns": true,
     *         "min_severity": 50
     *     }
     * }
     */
    Route::post('/analyze/{caseId}', [MisconductController::class, 'analyzeMisconduct']);

    /**
     * Generate dismissal motion based on misconduct
     *
     * POST /api/misconduct/dismissal-motion/{caseId}
     *
     * Request body (optional):
     * {
     *     "min_severity": 85
     * }
     */
    Route::post('/dismissal-motion/{caseId}', [MisconductController::class, 'generateDismissalMotion']);

    /**
     * Generate complaint to State Attorney / Judicial Council / Police
     *
     * POST /api/misconduct/complaint/{caseId}
     *
     * Request body (required):
     * {
     *     "complaint_type": "state_attorney|judicial_council|police_internal_affairs"
     * }
     */
    Route::post('/complaint/{caseId}', [MisconductController::class, 'generateComplaint']);

    /**
     * Build appeal based on misconduct
     *
     * POST /api/misconduct/appeal/{caseId}
     *
     * Request body (required):
     * {
     *     "appeal_type": "zalba|zastita_zakonitosti|ustavna_tuzba"
     * }
     */
    Route::post('/appeal/{caseId}', [MisconductController::class, 'buildAppeal']);
});
