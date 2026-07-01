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
    Route::get('/admin/templates', [AdminController::class, 'cvTemplates'])->name('admin.cv-templates.index');
    Route::get('/admin/education', [AdminController::class, 'education'])->name('admin.education.index');
    Route::get('/admin/jobs', [AdminController::class, 'jobs'])->name('admin.jobs.index');
    Route::get('/admin/leads', [AdminController::class, 'leads'])->name('admin.leads.index');
    Route::get('/admin/analyses', [AdminController::class, 'analyses'])->name('admin.analyses.index');
    Route::get('/admin/generated-cvs', [AdminController::class, 'generatedCvs'])->name('admin.generated-cvs.index');
    Route::post('/admin/cv-templates', [AdminController::class, 'storeCvTemplate'])->name('admin.cv-templates.store');
    Route::patch('/admin/cv-templates/{cvTemplate}', [AdminController::class, 'updateCvTemplate'])->name('admin.cv-templates.update');
    Route::post('/admin/cv-templates/{cvTemplate}/default', [AdminController::class, 'setDefaultCvTemplate'])->name('admin.cv-templates.default');
    Route::delete('/admin/cv-templates/{cvTemplate}', [AdminController::class, 'destroyCvTemplate'])->name('admin.cv-templates.destroy');
    Route::post('/admin/education-contents', [AdminController::class, 'storeEducationContent'])->name('admin.education-contents.store');
    Route::delete('/admin/education-contents/{educationContent}', [AdminController::class, 'destroyEducationContent'])->name('admin.education-contents.destroy');
    Route::post('/admin/job-news', [AdminController::class, 'storeJobNews'])->name('admin.job-news.store');
    Route::post('/admin/job-news/bulk', [AdminController::class, 'bulkUpdateJobNews'])->name('admin.job-news.bulk');
    Route::post('/admin/job-news/import', [AdminController::class, 'importJobsFromFile'])->name('admin.job-news.import');
    Route::post('/admin/job-news/sync-from-sheet', [AdminController::class, 'syncJobsFromGoogleSheet'])->name('admin.job-news.sync');
    Route::get('/admin/job-news/template.csv', [AdminController::class, 'downloadJobsTemplate'])->name('admin.job-news.template');
    Route::patch('/admin/job-news/{jobNews}', [AdminController::class, 'updateJobNews'])->name('admin.job-news.update');
    Route::delete('/admin/job-news/{jobNews}', [AdminController::class, 'destroyJobNews'])->name('admin.job-news.destroy');
});
