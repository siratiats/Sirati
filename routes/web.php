<?php

use App\Http\Controllers\Admin\LabelController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\HealthController as AdminHealthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\GeneratedCvController;
use App\Http\Controllers\LandingLeadController;
use App\Support\LandingContent;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', function () {
        $staticHome = public_path('index.html');
        if (file_exists($staticHome)) {
            return response()->file($staticHome, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
        return view('welcome', ['cms' => new LandingContent()]);
    })->name('landing');

    Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');

    Route::post('/landing-leads', [LandingLeadController::class, 'store'])->name('landing-leads.store');

    Route::redirect('/login', '/admin/login')->name('login');

    Route::get('/analyze', [CvAnalysisController::class, 'create'])->name('analyses.create');
    Route::post('/analyze', [CvAnalysisController::class, 'store'])
        ->middleware('throttle:ai-heavy')
        ->name('analyses.store');
    Route::get('/analyses/{analysis}', [CvAnalysisController::class, 'show'])->name('analyses.show');

    Route::get('/generate-cv', [GeneratedCvController::class, 'create'])->name('generated-cvs.create');
    Route::post('/generate-cv', [GeneratedCvController::class, 'store'])
        ->middleware('throttle:ai-heavy')
        ->name('generated-cvs.store');
    Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'show'])->name('generated-cvs.show');
    Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])->name('generated-cvs.pdf');
});

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
    Route::get('/admin/notifications', [AdminController::class, 'notifications'])->name('admin.notifications.index');
    Route::post('/admin/notifications/count', [AdminController::class, 'notificationRecipientCount'])->name('admin.notifications.count');
    Route::post('/admin/notifications/send', [AdminController::class, 'sendNotification'])->name('admin.notifications.send');
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
    Route::post('/admin/users/{user}/grant-premium', [AdminController::class, 'grantUserPremium'])->name('admin.users.grant-premium');
    Route::post('/admin/users/{user}/revoke-premium', [AdminController::class, 'revokeUserPremium'])->name('admin.users.revoke-premium');

    // Localization admin
    Route::get('/admin/languages', [LanguageController::class, 'index'])->name('admin.languages.index');
    Route::post('/admin/languages', [LanguageController::class, 'store'])->name('admin.languages.store');
    Route::patch('/admin/languages/{code}', [LanguageController::class, 'update'])->name('admin.languages.update');
    Route::post('/admin/languages/{code}/publish', [LanguageController::class, 'publish'])->name('admin.languages.publish');
    Route::post('/admin/languages/{code}/rollback', [LanguageController::class, 'rollback'])->name('admin.languages.rollback');
    Route::delete('/admin/languages/{code}', [LanguageController::class, 'destroy'])->name('admin.languages.destroy');
    Route::get('/admin/labels', [LabelController::class, 'index'])->name('admin.labels.index');
    Route::put('/admin/labels/{code}', [LabelController::class, 'update'])->name('admin.labels.update');
    Route::post('/admin/labels/{code}/bulk', [LabelController::class, 'bulk'])->name('admin.labels.bulk');
    Route::post('/admin/labels/{code}/discard', [LabelController::class, 'discard'])->name('admin.labels.discard');
    Route::post('/admin/labels/{code}/import', [LabelController::class, 'import'])->name('admin.labels.import');
    Route::get('/admin/labels/{code}/export', [LabelController::class, 'export'])->name('admin.labels.export');

    // Admin monitoring
    Route::get('/admin/health', [AdminHealthController::class, 'index'])->name('admin.health.index');
    Route::post('/admin/health/failed-jobs/retry-all', [AdminHealthController::class, 'retryAll'])->name('admin.health.failed.retry-all');
    Route::post('/admin/health/failed-jobs/{id}/retry', [AdminHealthController::class, 'retry'])->name('admin.health.failed.retry');
    Route::post('/admin/health/failed-jobs/{id}/forget', [AdminHealthController::class, 'forget'])->name('admin.health.failed.forget');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
});
