<?php

use App\Http\Controllers\DashboardController;
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

    /* Messaging ------------------------------------------------------------ */
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', [MessageController::class, 'index'])->name('index');
        Route::get('compose', [MessageController::class, 'create'])->name('create');
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('{message}', [MessageController::class, 'show'])->name('show');
    });
    Route::post('media', [MediaController::class, 'store'])->name('media.store');

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
