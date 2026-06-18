<?php

use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\GeneratedCvController;
use Illuminate\Support\Facades\Route;

Route::get('/cv-analyses', [CvAnalysisController::class, 'indexApi']);
Route::post('/cv-analyses', [CvAnalysisController::class, 'storeApi']);
Route::get('/cv-analyses/{analysis}', [CvAnalysisController::class, 'showApi']);
Route::post('/cv-analyses/{analysis}/generated-cv', [GeneratedCvController::class, 'storeFromAnalysisApi']);

Route::get('/generated-cvs', [GeneratedCvController::class, 'indexApi']);
Route::post('/generated-cvs', [GeneratedCvController::class, 'storeApi']);
Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'showApi']);
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf']);
