<?php

use App\Http\Controllers\{HomeController, UserController};
use App\Livewire\Contact;
use Illuminate\Support\Facades\Route;


Route::get('/', [HomeController::class, 'index'])->name('home');
Route::group(['middleware' => 'guest'], function() {
    Route::get('/login', [HomeController::class, 'login'])->name('login');
    Route::post('/login', [UserController::class, 'authenticate'])->name('authenticate');
});

Route::get('/contact', [Contact::class, 'render'])->name('contact');

Route::group(['middleware' => 'auth'], function () {
    //     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/logout', [UserController::class, 'logout'])->name('logout');
    Route::get('/settings', [UserController::class, 'viewSettings'])->name('settings');
//     Route::prefix('wishlist')->group(function() {
//         Route::controller(WhistlistController::class)->group(function() {
//             Route::get('/', 'index')->name('wishlist.index');
//             Route::get('/{username}', 'show')->name('wishlist.show');
//             Route::get('/create', 'create')->name('wishlist.create');
//         });
//     });

//     Route::prefix('secret-santa')->group(function() {
//         Route::controller(SecretSantaController::class)->group(function() {
//             Route::get('/', 'index')->name('secretsanta.index');
//             Route::get('/{identifier}', 'show')->name('secretsanta.show');
//             Route::get('/create', 'create')->name('secretsanta.create');
//         });
//     });
});

// Route::get('/wishes', [WishlistController::class, 'index'])->name('wishes.index');
// Route::get('/wishes/{username}', [WishlistController::class, 'show'])->name('wishes.show');
// Route::get('/wishes/create', [WishlistController::class, 'create'])->name('wishes.create');