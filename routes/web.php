<?php

use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/properties');

Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
