<?php

use App\Http\Controllers\AmigoSecretoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FamilyInvitationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->middleware('auth.session')->name('home');
Route::group(['middleware' => 'guest'], function () {
    Route::get('/login', [HomeController::class, 'login'])->name('login');
    Route::post('/login', [UserController::class, 'authenticate'])->name('authenticate');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::group(['middleware' => ['auth', 'auth.session']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [UserController::class, 'logout'])->name('logout');
    Route::get('/settings', [UserController::class, 'viewSettings'])->name('settings');
    Route::get('/profile', [UserController::class, 'viewSettings'])->name('profile');

    // Notifications
    Route::get('/notifications', function () {
        return view('notifications.index');
    })->name('notifications.index');

    // Family routes
    Route::get('/family-invitations/{invitation}', [FamilyInvitationController::class, 'show'])->whereNumber('invitation')->name('family-invitations.show');
    Route::post('/family-invitations/{invitation}', [FamilyInvitationController::class, 'respond'])->whereNumber('invitation')->name('family-invitations.respond');

    Route::prefix('families')->group(function () {
        Route::get('/', [FamilyController::class, 'index'])->name('families.index');
        Route::get('/create', [FamilyController::class, 'create'])->name('families.create');
        Route::get('/join', [FamilyController::class, 'joinForm'])->name('families.join');
        Route::get('/{family}', [FamilyController::class, 'show'])->name('families.show');
        Route::get('/{family}/settings', [FamilyController::class, 'settings'])->name('families.settings');
    });

    // Wishlist routes
    Route::prefix('wishlists')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('wishlists.index');
        Route::get('/create', [WishlistController::class, 'create'])->name('wishlists.create');
        Route::get('/{wishlist}', [WishlistController::class, 'show'])->name('wishlists.show');
        Route::get('/{wishlist}/settings', [WishlistController::class, 'settings'])->name('wishlists.settings');
    });

    // Amigo Secreto routes
    Route::prefix('secret-santa')->group(function () {
        Route::get('/', [AmigoSecretoController::class, 'index'])->name('amigo-secreto.index');
        Route::get('/create', [AmigoSecretoController::class, 'create'])->name('amigo-secreto.create');
        Route::get('/join', [AmigoSecretoController::class, 'joinForm'])->name('amigo-secreto.join');
        Route::get('/{event}', [AmigoSecretoController::class, 'show'])->name('amigo-secreto.show');
        Route::get('/{event}/participants', [AmigoSecretoController::class, 'participants'])->name('amigo-secreto.participants');
        Route::get('/{event}/exclusions', [AmigoSecretoController::class, 'exclusions'])->name('amigo-secreto.exclusions');
        Route::get('/{event}/draw', [AmigoSecretoController::class, 'drawPage'])->name('amigo-secreto.draw');
        Route::get('/{event}/assignment', [AmigoSecretoController::class, 'assignment'])->name('amigo-secreto.assignment');
        Route::get('/{event}/history', [AmigoSecretoController::class, 'history'])->whereNumber('event')->name('amigo-secreto.history');
    });
    // Shareable links retain their URLs, but require a current authenticated session.
    Route::get('/w/{wishlist}', [WishlistController::class, 'show'])->name('wishlists.public');
    Route::get('/join-event/{code}', [AmigoSecretoController::class, 'joinForm'])->name('amigo-secreto.public-join');
});
