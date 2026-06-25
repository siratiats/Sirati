<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\GeneratedCvController;
use App\Http\Controllers\LandingLeadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('landing');

Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');

Route::post('/landing-leads', [LandingLeadController::class, 'store'])->name('landing-leads.store');

Route::redirect('/login', '/admin/login')->name('login');

Route::get('/analyze', [CvAnalysisController::class, 'create'])->name('analyses.create');
Route::post('/analyze', [CvAnalysisController::class, 'store'])->name('analyses.store');
Route::get('/analyses/{analysis}', [CvAnalysisController::class, 'show'])->name('analyses.show');

Route::get('/generate-cv', [GeneratedCvController::class, 'create'])->name('generated-cvs.create');
Route::post('/generate-cv', [GeneratedCvController::class, 'store'])->name('generated-cvs.store');
Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'show'])->name('generated-cvs.show');
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])->name('generated-cvs.pdf');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/education-contents', [AdminController::class, 'storeEducationContent'])->name('admin.education-contents.store');
    Route::delete('/admin/education-contents/{educationContent}', [AdminController::class, 'destroyEducationContent'])->name('admin.education-contents.destroy');
    Route::post('/admin/job-news', [AdminController::class, 'storeJobNews'])->name('admin.job-news.store');
    Route::delete('/admin/job-news/{jobNews}', [AdminController::class, 'destroyJobNews'])->name('admin.job-news.destroy');
});
