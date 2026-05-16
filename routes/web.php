<?php

use App\Http\Controllers\App\ClientWorkspaceController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\PageController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/clients/{client}', [ClientWorkspaceController::class, 'show'])
        ->middleware('client.scope')
        ->name('clients.show');

    foreach (['tickets', 'bugs', 'features', 'sprints', 'timeline', 'reports', 'clients', 'software', 'settings'] as $section) {
        Route::get('/'.$section, PageController::class)
            ->defaults('section', $section)
            ->name($section.'.index');
    }
});

require __DIR__.'/auth.php';
