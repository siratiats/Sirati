<?php

use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\GeneratedCvController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\MobileContentController;
use App\Http\Controllers\NotificationEngagementController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [MobileAuthController::class, 'register'])
    ->middleware('throttle:5,1');
Route::post('/auth/login', [MobileAuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::post('/auth/forgot-password', [MobileAuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1');
Route::post('/auth/reset-password', [MobileAuthController::class, 'resetPassword'])
    ->middleware('throttle:5,1');

Route::get('/mobile/education', [MobileContentController::class, 'education']);
Route::get('/mobile/education/{educationContent}', [MobileContentController::class, 'educationShow']);
Route::get('/mobile/cv-templates', [MobileContentController::class, 'cvTemplates']);
Route::get('/mobile/job-titles', [MobileContentController::class, 'jobTitles']);
Route::get('/mobile/job-news', [MobileContentController::class, 'jobNews']);
Route::get('/mobile/job-news/{jobNews}', [MobileContentController::class, 'jobNewsShow']);
Route::get('/generated-cvs/{generatedCv}/pdf', [GeneratedCvController::class, 'downloadPdf'])
    ->middleware('signed')
    ->name('api.generated-cvs.pdf');

Route::middleware('auth:sanctum')->group(function () {
    // Available before email verification (verify flow + session basics).
    Route::get('/auth/me', [MobileAuthController::class, 'me']);
    Route::post('/auth/logout', [MobileAuthController::class, 'logout']);
    Route::post('/auth/email/verify', [MobileAuthController::class, 'verifyEmail'])
        ->middleware('throttle:10,1');
    Route::post('/auth/email/resend', [MobileAuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1');
    Route::delete('/auth/account', [MobileAuthController::class, 'deleteAccount'])
        ->middleware('throttle:5,1');

    // Everything else requires a verified email.
    Route::middleware(EnsureEmailIsVerified::class)->group(function () {
        Route::post('/auth/change-password', [MobileAuthController::class, 'changePassword'])
            ->middleware('throttle:5,1');
        Route::put('/auth/profile', [MobileAuthController::class, 'updateProfile']);
        Route::post('/fcm-tokens', [FcmTokenController::class, 'store']);
        Route::delete('/fcm-tokens', [FcmTokenController::class, 'destroy']);

        Route::get('/mobile/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/mobile/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::post('/mobile/activity', [NotificationEngagementController::class, 'reportActivity'])
            ->middleware('throttle:30,1');
        Route::post('/mobile/conversions', [NotificationEngagementController::class, 'reportConversion'])
            ->middleware('throttle:30,1');

        Route::get('/mobile/dashboard', [MobileContentController::class, 'dashboard']);
        Route::get('/mobile/my-cvs', [MobileContentController::class, 'myCvs']);
        Route::get('/mobile/notifications', [MobileContentController::class, 'notifications']);
        Route::post('/mobile/notifications/read-all', [MobileContentController::class, 'markAllNotificationsRead']);
        Route::post('/mobile/notifications/{notification}/read', [MobileContentController::class, 'markNotificationRead']);
        Route::post('/mobile/notifications/{notification}/opened', [NotificationEngagementController::class, 'markOpened']);

        Route::get('/cv-analyses', [CvAnalysisController::class, 'indexApi']);
        Route::post('/cv-analyses', [CvAnalysisController::class, 'storeApi'])
            ->middleware('throttle:ai-heavy');
        Route::get('/cv-analyses/{analysis}', [CvAnalysisController::class, 'showApi']);
        Route::post('/cv-analyses/{analysis}/generated-cv', [GeneratedCvController::class, 'storeFromAnalysisApi'])
            ->middleware('throttle:ai-heavy');

        Route::get('/generated-cvs', [GeneratedCvController::class, 'indexApi']);
        Route::post('/generated-cvs', [GeneratedCvController::class, 'storeApi'])
            ->middleware('throttle:ai-heavy');
        Route::post('/generated-cvs/enhance-job-description', [GeneratedCvController::class, 'enhanceJobDescription'])
            ->middleware('throttle:ai-light');
        Route::post('/generated-cvs/enhance-field', [GeneratedCvController::class, 'enhanceField'])
            ->middleware('throttle:ai-light');
        Route::get('/generated-cvs/{generatedCv}/download', [GeneratedCvController::class, 'downloadPdfApi']);
        Route::get('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'showApi']);
        Route::match(['put', 'patch'], '/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'updateApi']);
        Route::delete('/generated-cvs/{generatedCv}', [GeneratedCvController::class, 'destroyApi']);
    });
});
