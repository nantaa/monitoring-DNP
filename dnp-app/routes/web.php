<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobDocumentController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Jobs
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::patch('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
    Route::post('/jobs/{job}/advance', [JobController::class, 'advanceStage'])->name('jobs.advance');

    // Documents
    Route::post('/jobs/{job}/documents', [JobDocumentController::class, 'store'])->name('jobs.documents.store');
    Route::delete('/jobs/{job}/documents/{document}', [JobDocumentController::class, 'destroy'])->name('jobs.documents.destroy');

    // Recommendation
    Route::get('/jobs/{job}/recommend', RecommendationController::class)->name('jobs.recommend');
});
