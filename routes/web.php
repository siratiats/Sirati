<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\GeneratedCvController;
use App\Http\Controllers\LandingLeadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('landing');

Route::post('/landing-leads', [LandingLeadController::class, 'store'])->name('landing-leads.store');

Route::get('/analyze', [CvAnalysisController::class, 'create'])->name('analyses.create');
Route::post('/analyze', [CvAnalysisController::class, 'store'])->name('analyses.store');
Route::get('/analyses/{analysis}', [CvAnalysisController::class, 'show'])->name('analyses.show');

Route::get('/generate-cv', [GeneratedCvController::class, 'create'])->name('generated-cvs.create');
Route::post('/generate-cv', [GeneratedCvController::class, 'store'])->name('generated-cvs.store');
Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'show'])->name('generated-cvs.show');
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])->name('generated-cvs.pdf');

Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
