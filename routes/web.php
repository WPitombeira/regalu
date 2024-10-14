<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', [HomeController::class, 'login'])->name('login');

// Route::group(['middleware' => 'auth'], function () {
//     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

//     Route::prefix('wishlist')->group(function() {
//         Route::controller(WhistlistController::class)->group(function() {
//             Route::get('/', 'index')->name('wishlist.index');
//             Route::get('/{username}', 'show')->name('wishlist.show');
//             Route::get('/create', 'create')->name('wishlist.create');
//         });
//     });
// });

// Route::get('/wishes', [WishlistController::class, 'index'])->name('wishes.index');
// Route::get('/wishes/{username}', [WishlistController::class, 'show'])->name('wishes.show');
// Route::get('/wishes/create', [WishlistController::class, 'create'])->name('wishes.create');