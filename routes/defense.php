<?php

use App\Http\Controllers\DefenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Defense API Routes
|--------------------------------------------------------------------------
|
| Defense-focused legal analysis endpoints for improving the accused's status
|
*/

Route::prefix('defense')->group(function () {

    // Comprehensive status improvement analysis
    Route::post('/improve-status/{caseId}', [DefenseController::class, 'improveAccusedStatus'])
        ->name('defense.improve-status');

    // Defense strategy
    Route::get('/strategy/{caseId}', [DefenseController::class, 'getDefenseStrategy'])
        ->name('defense.strategy');

    // Recommendations
    Route::get('/recommendations/{caseId}', [DefenseController::class, 'getRecommendations'])
        ->name('defense.recommendations');

    // Prosecution weaknesses analysis
    Route::get('/prosecution-weaknesses/{caseId}', [DefenseController::class, 'analyzeProsecutionWeaknesses'])
        ->name('defense.prosecution-weaknesses');

    // Mitigating factors
    Route::get('/mitigating-factors/{caseId}', [DefenseController::class, 'getMitigatingFactors'])
        ->name('defense.mitigating-factors');

    // Defense strength assessment
    Route::get('/strength/{caseId}', [DefenseController::class, 'assessDefenseStrength'])
        ->name('defense.strength');

});
