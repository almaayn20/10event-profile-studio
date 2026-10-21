<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| The 10 Event profile studio: project pages for the company profile, behind one shared team password.
*/
Route::name('studio.')->group(function () {
    Route::get('/login', [AccessController::class, 'show'])->name('login');
    Route::post('/login', [AccessController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::post('/logout', [AccessController::class, 'logout'])->name('logout');

    Route::middleware('studio')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::prefix('/projects/{project}')->name('projects.')->group(function () {
            Route::get('/', [ProjectController::class, 'edit'])->name('edit');
            Route::put('/', [ProjectController::class, 'update'])->name('update');
            Route::delete('/', [ProjectController::class, 'destroy'])->name('destroy');
            Route::post('/preview', [ProjectController::class, 'preview'])->name('preview');
            Route::post('/shuffle', [ProjectController::class, 'shuffle'])->name('shuffle');
            Route::post('/images', [ImageController::class, 'store'])->name('images.store')->middleware('throttle:30,1');
            Route::get('/thumb', [ProjectController::class, 'thumb'])->name('thumb');
            Route::get('/pdf', [ProjectController::class, 'pdf'])->name('pdf');
            Route::post('/approve', [ProjectController::class, 'approve'])->name('approve');
            Route::post('/refresh', [ProjectController::class, 'refresh'])->name('refresh');
            Route::post('/unapprove', [ProjectController::class, 'unapprove'])->name('unapprove');
            Route::post('/link', [ProjectController::class, 'link'])->name('link');
            Route::post('/move', [ProjectController::class, 'move'])->name('move');
        });
        Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
        Route::put('/images/{image}/priority', [ImageController::class, 'priority'])->name('images.priority');
        Route::put('/images/{image}/crop', [ImageController::class, 'crop'])->name('images.crop');
        Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');

        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::post('/profile', [ProfileController::class, 'store'])->name('profile.store');
        Route::get('/profile/{version}/download', [ProfileController::class, 'download'])->name('profile.download');
        Route::post('/profile/{version}/restore', [ProfileController::class, 'restore'])->name('profile.restore');
        Route::get('/profile/{version}/pages/{page}', [ProfileController::class, 'page'])->whereNumber('page')->name('profile.page');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');
        Route::put('/settings/stats', [SettingsController::class, 'stats'])->name('settings.stats');
        Route::post('/settings/fonts/{weight}', [SettingsController::class, 'font'])->whereIn('weight', ['medium', 'bold'])->name('settings.font');
        Route::get('/fonts/{weight}', [SettingsController::class, 'show'])->whereIn('weight', ['medium', 'bold'])->name('fonts');
    });
});
