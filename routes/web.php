<?php

use App\Http\Controllers\BugController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\PageController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch('/clients/{client}/disable', [ClientController::class, 'disable'])->name('clients.disable');
    Route::resource('clients', ClientController::class)->except(['destroy']);

    Route::patch('/softwares/{software}/toggle', [SoftwareController::class, 'toggle'])->name('softwares.toggle');
    Route::resource('softwares', SoftwareController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('/tickets/backlog', [TicketController::class, 'backlog'])->name('tickets.backlog');
    Route::patch('/tickets/{ticket}/classify', [TicketController::class, 'classify'])->name('tickets.classify');
    Route::patch('/tickets/{ticket}/reject', [TicketController::class, 'reject'])->name('tickets.reject');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show', 'update']);

    Route::get('/bugs', [BugController::class, 'index'])->name('bugs.index');
    Route::get('/bugs/{ticket}', [BugController::class, 'show'])->name('bugs.show');
    Route::post('/bugs/{ticket}/pending', [BugController::class, 'pending'])->name('bugs.pending');
    Route::post('/bugs/{ticket}/complete', [BugController::class, 'complete'])->name('bugs.complete');
    Route::post('/bugs/{ticket}/block', [BugController::class, 'block'])->name('bugs.block');

    Route::get('/features', [FeatureController::class, 'index'])->name('features.index');
    Route::get('/features/recommended', [FeatureController::class, 'recommended'])->name('features.recommended');
    Route::get('/features/{ticket}', [FeatureController::class, 'show'])->name('features.show');
    Route::patch('/features/{ticket}', [FeatureController::class, 'update'])->name('features.update');
    Route::post('/features/{ticket}/recommend', [FeatureController::class, 'recommend'])->name('features.recommend');
    Route::post('/features/{ticket}/approve-next-sprint', [FeatureController::class, 'approveNextSprint'])->name('features.approve-next-sprint');
    Route::post('/features/{ticket}/defer', [FeatureController::class, 'defer'])->name('features.defer');
    Route::post('/features/{ticket}/complete', [FeatureController::class, 'complete'])->name('features.complete');

    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/start', [SprintController::class, 'startForm'])->name('sprints.start');
    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');
    Route::post('/sprints/start', [SprintController::class, 'start'])->name('sprints.start.store');
    Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');

    foreach (['timeline', 'reports', 'settings'] as $section) {
        Route::get('/'.$section, PageController::class)
            ->defaults('section', $section)
            ->name($section.'.index');
    }
});

require __DIR__.'/auth.php';
