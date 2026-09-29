<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Broadcasts\BroadcastController;
use App\Http\Controllers\Broadcasts\SegmentController;
use App\Http\Controllers\Contacts\ContactConsentController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\ContactImportController;
use App\Http\Controllers\DataDeletionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inbox\InboxController;
use App\Http\Controllers\Messaging\MediaController;
use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Onboarding\EmbeddedSignupController;
use App\Http\Controllers\Onboarding\PhoneNumberController;
use App\Http\Controllers\Onboarding\WhatsAppAccountController;
use App\Http\Controllers\Templates\TemplateController;
use App\Http\Controllers\Templates\TemplateMediaController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : Inertia::render('welcome');
})->name('home');

Route::get('privacy-policy', function () {
    return Inertia::render('privacy-policy');
})->name('privacy');
Route::get('terms-of-service', function () {
    return Inertia::render('terms-of-service');
})->name('terms');
Route::get('user-data-deletion', function () {
    return Inertia::render('user-data-deletion');
})->name('user-dd');
Route::post('api/data-deletion-callback', [DataDeletionController::class, 'callback'])->name('deletion.callback');
Route::get('deletion-status', [DataDeletionController::class, 'status'])->name('deletion.status');

/*
|--------------------------------------------------------------------------
| Meta webhooks (no auth, no CSRF)
|--------------------------------------------------------------------------
*/
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('whatsapp.handle');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    /* Embedded Signup & onboarding ---------------------------------------- */
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [EmbeddedSignupController::class, 'index'])->name('index');
        Route::post('embedded-signup', [EmbeddedSignupController::class, 'callback'])->name('callback');
        Route::post('embedded-signup/session', [EmbeddedSignupController::class, 'sessionEvent'])->name('session');
    });

    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [WhatsAppAccountController::class, 'index'])->name('index');
        Route::get('{account}', [WhatsAppAccountController::class, 'show'])->name('show');
        Route::post('{account}/sync', [WhatsAppAccountController::class, 'sync'])->name('sync');
        Route::post('{account}/subscribe', [WhatsAppAccountController::class, 'subscribe'])->name('subscribe');
        Route::post('{account}/subscriptions', [WhatsAppAccountController::class, 'subscriptions'])->name('subscriptions');
        Route::delete('{account}/subscribe', [WhatsAppAccountController::class, 'unsubscribe'])->name('unsubscribe');
        Route::post('{account}/credit-line', [WhatsAppAccountController::class, 'shareCreditLine'])->name('credit-line');
        Route::post('{account}/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
        Route::delete('{account}', [WhatsAppAccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('phone-numbers')->name('phones.')->group(function () {
        Route::post('{phone}/register', [PhoneNumberController::class, 'register'])->name('register');
        Route::post('{phone}/deregister', [PhoneNumberController::class, 'deregister'])->name('deregister');
        Route::post('{phone}/request-code', [PhoneNumberController::class, 'requestCode'])->name('request-code');
        Route::post('{phone}/verify-code', [PhoneNumberController::class, 'verifyCode'])->name('verify-code');
        Route::post('{phone}/pin', [PhoneNumberController::class, 'updatePin'])->name('pin');
        Route::post('{phone}/default', [PhoneNumberController::class, 'setDefault'])->name('default');
    });

    /* Contacts -------------------------------------------------------------- */
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::get('import', [ContactImportController::class, 'create'])->name('import');
        Route::post('import/preview', [ContactImportController::class, 'preview'])->name('import.preview');
        Route::post('import', [ContactImportController::class, 'store'])->name('import.store');
        Route::put('{contact}/consent', [ContactConsentController::class, 'update'])->name('consent');
        Route::get('create', [ContactController::class, 'create'])->name('create');
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::get('{contact}/edit', [ContactController::class, 'edit'])->name('edit');
        Route::put('{contact}', [ContactController::class, 'update'])->name('update');
        Route::delete('{contact}', [ContactController::class, 'destroy'])->name('destroy');
    });

    /* Segments & broadcasts --------------------------------------------------- */
    Route::prefix('segments')->name('segments.')->group(function () {
        Route::get('/', [SegmentController::class, 'index'])->name('index');
        Route::get('create', [SegmentController::class, 'create'])->name('create');
        Route::post('count', [SegmentController::class, 'count'])->name('count');
        Route::post('/', [SegmentController::class, 'store'])->name('store');
        Route::get('{segment}/edit', [SegmentController::class, 'edit'])->name('edit');
        Route::put('{segment}', [SegmentController::class, 'update'])->name('update');
        Route::delete('{segment}', [SegmentController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('broadcasts')->name('broadcasts.')->group(function () {
        Route::get('/', [BroadcastController::class, 'index'])->name('index');
        Route::get('create', [BroadcastController::class, 'create'])->name('create');
        Route::post('/', [BroadcastController::class, 'store'])->name('store');
        Route::get('{broadcast}', [BroadcastController::class, 'show'])->name('show');
        Route::get('{broadcast}/progress', [BroadcastController::class, 'progress'])->name('progress');
        Route::get('{broadcast}/edit', [BroadcastController::class, 'edit'])->name('edit');
        Route::put('{broadcast}', [BroadcastController::class, 'update'])->name('update');
        Route::post('{broadcast}/launch', [BroadcastController::class, 'launch'])->name('launch');
        Route::post('{broadcast}/cancel', [BroadcastController::class, 'cancel'])->name('cancel');
        Route::delete('{broadcast}', [BroadcastController::class, 'destroy'])->name('destroy');
    });

    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    /* Inbox (WhatsApp-style chat) ------------------------------------------- */
    Route::prefix('inbox')->name('inbox.')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('index');
        Route::get('conversations', [InboxController::class, 'conversations'])->name('conversations');
        Route::get('{contact}', [InboxController::class, 'show'])->name('show');
        Route::get('{contact}/messages', [InboxController::class, 'messages'])->name('messages');
        Route::post('{contact}/messages', [InboxController::class, 'send'])->name('send');
        Route::post('{contact}/read', [InboxController::class, 'markRead'])->name('read');
    });

    /* Messaging ------------------------------------------------------------ */
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', [MessageController::class, 'index'])->name('index');
        Route::get('compose', [MessageController::class, 'create'])->name('create');
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('{message}', [MessageController::class, 'show'])->name('show');
    });
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{asset}', [MediaController::class, 'show'])->name('media.show');

    /* Templates ------------------------------------------------------------ */
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('create', [TemplateController::class, 'create'])->name('create');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::post('media', [TemplateMediaController::class, 'store'])->name('media');
        Route::get('{template}', [TemplateController::class, 'show'])->name('show');
        Route::post('{template}/refresh', [TemplateController::class, 'refresh'])->name('refresh');
        Route::delete('{template}', [TemplateController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
