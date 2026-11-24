<?php

use App\Http\Controllers\RecipeAnalysisController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('recipes/analyze', [RecipeAnalysisController::class, 'create'])->name('recipes.analyze');
    // Each call costs money: at most 10 analyses per minute per user (quotas come with O4).
    Route::post('recipes/analyze', [RecipeAnalysisController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('recipes.analyze.store');
});

require __DIR__.'/settings.php';
