<?php

use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\GeneratedCvController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileContentController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [MobileAuthController::class, 'register']);
Route::post('/auth/login', [MobileAuthController::class, 'login']);
Route::post('/auth/forgot-password', [MobileAuthController::class, 'forgotPassword']);

Route::get('/mobile/education', [MobileContentController::class, 'education']);
Route::get('/mobile/education/{educationContent}', [MobileContentController::class, 'educationShow']);
Route::get('/mobile/cv-templates', [MobileContentController::class, 'cvTemplates']);
Route::get('/mobile/job-news', [MobileContentController::class, 'jobNews']);
Route::get('/mobile/job-news/{jobNews}', [MobileContentController::class, 'jobNewsShow']);
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])
    ->middleware('signed')
    ->name('api.generated-cvs.pdf');

Route::middleware('auth:sanctum')->group(function () {
	Route::get('/auth/me', [MobileAuthController::class, 'me']);
	Route::post('/auth/logout', [MobileAuthController::class, 'logout']);

	Route::get('/mobile/dashboard', [MobileContentController::class, 'dashboard']);
	Route::get('/mobile/my-cvs', [MobileContentController::class, 'myCvs']);
	Route::get('/mobile/notifications', [MobileContentController::class, 'notifications']);
	Route::post('/mobile/notifications/read-all', [MobileContentController::class, 'markAllNotificationsRead']);
	Route::post('/mobile/notifications/{notification}/read', [MobileContentController::class, 'markNotificationRead']);

	Route::get('/cv-analyses', [CvAnalysisController::class, 'indexApi']);
	Route::post('/cv-analyses', [CvAnalysisController::class, 'storeApi']);
	Route::get('/cv-analyses/{analysis}', [CvAnalysisController::class, 'showApi']);
	Route::post('/cv-analyses/{analysis}/generated-cv', [GeneratedCvController::class, 'storeFromAnalysisApi']);

	Route::get('/generated-cvs', [GeneratedCvController::class, 'indexApi']);
	Route::post('/generated-cvs', [GeneratedCvController::class, 'storeApi']);
	Route::post('/generated-cvs/enhance-job-description', [GeneratedCvController::class, 'enhanceJobDescription']);
	Route::get('/generated-cvs/{generatedCv}/download', [GeneratedCvController::class, 'downloadPdfApi']);
	Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'showApi']);
	Route::match(['put', 'patch'], '/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'updateApi']);
	Route::delete('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'destroyApi']);
});
