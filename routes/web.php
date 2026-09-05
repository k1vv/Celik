<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('role:teacher')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('teacher/submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
        Route::post('teacher/submissions/{submission}/validate', [SubmissionController::class, 'validate'])->name('submissions.validate');
    });

    Route::middleware('role:student')->group(function () {
        Route::get('student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('student/homework/submit', [StudentDashboardController::class, 'submitHomework'])->name('student.homework.submit');
        Route::get('practice', [PracticeController::class, 'index'])->name('practice');
        Route::post('practice', [PracticeController::class, 'store'])->name('practice.answer');

        Route::post('homework', [HomeworkController::class, 'store'])->name('homework.answer');

        Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    });
});
