<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/properties');

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

// Bookings are always made for one property, so the routes are nested under it.
Route::get('/properties/{property}/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/properties/{property}/bookings', [BookingController::class, 'store'])->name('bookings.store');
