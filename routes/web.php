<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\PageController;
use App\Http\Controllers\SoftwareController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch('/clients/{client}/disable', [ClientController::class, 'disable'])->name('clients.disable');
    Route::resource('clients', ClientController::class)->except(['destroy']);

    Route::patch('/softwares/{software}/toggle', [SoftwareController::class, 'toggle'])->name('softwares.toggle');
    Route::resource('softwares', SoftwareController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    foreach (['tickets', 'bugs', 'features', 'sprints', 'timeline', 'reports', 'settings'] as $section) {
        Route::get('/'.$section, PageController::class)
            ->defaults('section', $section)
            ->name($section.'.index');
    }
});

require __DIR__.'/auth.php';
