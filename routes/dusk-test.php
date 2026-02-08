<?php

use Illuminate\Support\Facades\Route;

// Public route for Dusk testing - no authentication required
Route::get('/dusk-test', function () {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dusk Test Page</title>
</head>
<body>
    <h1>Dusk Test Success!</h1>
    <p id="test-content">Chrome and Laravel Dusk are working perfectly in the cloud!</p>
    <button id="test-button">Click Me</button>
    <div id="result" style="display:none;">Button Clicked!</div>
    <script>
        document.getElementById("test-button").addEventListener("click", function() {
            document.getElementById("result").style.display = "block";
        });
    </script>
</body>
</html>';
});

// Test route for CollaborationDashboard Livewire component
Route::get('/test-collaboration-dashboard', function () {
    return view('test-collaboration-dashboard');
})->middleware('auth');

// Test route for FeedbackDashboard Livewire component
Route::get('/test-feedback-dashboard', function () {
    return view('test-feedback-dashboard');
})->middleware('auth');

// Test route for LearningOpportunityManager Livewire component
Route::get('/test-learning-opportunity-manager', function () {
    return view('test-learning-opportunity-manager');
})->middleware('auth');

// Test route for TranscriptPreviewer Livewire component
Route::get('/test-transcript-previewer', function () {
    return view('test-transcript-previewer');
})->middleware('auth');

// Test route for OpenAIVectorManager Livewire component
Route::get('/test-openai-vector-manager', function () {
    return view('test-openai-vector-manager');
})->middleware('auth');

// Test route for ParallelTimeline Livewire component
Route::get('/test-parallel-timeline', function () {
    return view('test-parallel-timeline');
})->middleware('auth');

// Test route for CitationTimeSeriesViewer Livewire component
Route::get('/test-citation-time-series-viewer', function () {
    return view('test-citation-time-series-viewer');
})->middleware('auth');

// Test routes for PrecedentialBadge component
Route::get('/test/precedential-badge/{type}', function ($type) {
    return view('test-precedential-badge', ['type' => $type]);
})->middleware('auth')->where('type', 'binding|persuasive|informational');
