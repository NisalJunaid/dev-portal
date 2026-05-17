<?php

use App\Http\Controllers\BugController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\PageController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketBlockController;
use App\Http\Controllers\TaskWorkspaceController;
use App\Http\Controllers\TimeTrackingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::patch('/clients/{client}/disable', [ClientController::class, 'disable'])->name('clients.disable');
    Route::resource('clients', ClientController::class)->except(['destroy']);

    Route::patch('/softwares/{software}/toggle', [SoftwareController::class, 'toggle'])->name('softwares.toggle');
    Route::resource('softwares', SoftwareController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('/tasks', [TaskWorkspaceController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/partial', [TaskWorkspaceController::class, 'partial'])->name('tasks.partial');
    Route::get('/kanban', fn () => redirect()->route('tasks.index', ['view' => 'board']))->name('kanban.index');
    Route::get('/timeline', fn () => redirect()->route('tasks.index', ['view' => 'timeline']))->name('timeline.index');
    Route::get('/timeline/data', [TimelineController::class, 'data'])->name('timeline.data');
    Route::patch('/timeline/tasks/{ticket}/dates', [TimelineController::class, 'dates'])->name('timeline.tasks.dates');
    Route::patch('/timeline/tasks/{ticket}/dependency', [TimelineController::class, 'dependency'])->name('timeline.tasks.dependency');
    Route::patch('/kanban/tickets/reorder', [KanbanController::class, 'reorder'])->name('kanban.tickets.reorder');
    Route::patch('/kanban/tickets/{ticket}/move', [KanbanController::class, 'move'])->name('kanban.tickets.move');

    Route::get('/tickets/backlog', [TicketController::class, 'backlog'])->name('tickets.backlog');
    Route::patch('/tickets/{ticket}/classify', [TicketController::class, 'classify'])->name('tickets.classify');
    Route::patch('/tickets/{ticket}/reject', [TicketController::class, 'reject'])->name('tickets.reject');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::get('/tickets/{ticket}/drawer', [TicketController::class, 'drawer'])->name('tickets.drawer');
    Route::patch('/tickets/{ticket}/inline-update', [TicketController::class, 'inlineUpdate'])->name('tickets.inline-update');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/block', [TicketBlockController::class, 'block'])->name('tickets.block');
    Route::post('/tickets/{ticket}/unblock', [TicketBlockController::class, 'unblock'])->name('tickets.unblock');
    Route::post('/tickets/{ticket}/timer/start', [TimeTrackingController::class, 'start'])->name('tickets.timer.start');
    Route::post('/tickets/{ticket}/timer/pause', [TimeTrackingController::class, 'pause'])->name('tickets.timer.pause');
    Route::post('/tickets/{ticket}/timer/resume', [TimeTrackingController::class, 'resume'])->name('tickets.timer.resume');
    Route::post('/tickets/{ticket}/timer/stop', [TimeTrackingController::class, 'stop'])->name('tickets.timer.stop');
    Route::get('/tickets', fn () => redirect()->route('tasks.index', ['view' => 'list']))->name('tickets.index');
    Route::resource('tickets', TicketController::class)->only(['create', 'store', 'show', 'update']);

    Route::get('/bugs', [BugController::class, 'index'])->name('bugs.index');
    Route::get('/bugs/{ticket}', [BugController::class, 'show'])->name('bugs.show');
    Route::post('/bugs/{ticket}/pending', [BugController::class, 'pending'])->name('bugs.pending');
    Route::post('/bugs/{ticket}/complete', [BugController::class, 'complete'])->name('bugs.complete');
    Route::post('/bugs/{ticket}/block', [BugController::class, 'block'])->name('bugs.block');

    Route::get('/features', [FeatureController::class, 'index'])->name('features.index');
    Route::get('/features/recommended', [FeatureController::class, 'recommended'])->name('features.recommended');
    Route::get('/features/{ticket}', [FeatureController::class, 'show'])->name('features.show');
    Route::patch('/features/{ticket}', [FeatureController::class, 'update'])->name('features.update');
    Route::post('/features/request', [FeatureController::class, 'storeRequest'])->name('features.request.store');
    Route::post('/features/{ticket}/recommend', [FeatureController::class, 'recommend'])->name('features.recommend');
    Route::post('/features/{ticket}/approve-next-sprint', [FeatureController::class, 'approveNextSprint'])->name('features.approve-next-sprint');
    Route::post('/features/{ticket}/defer', [FeatureController::class, 'defer'])->name('features.defer');
    Route::post('/features/{ticket}/complete', [FeatureController::class, 'complete'])->name('features.complete');

    Route::get('/sprints', [SprintController::class, 'index'])->name('sprints.index');
    Route::get('/sprints/start', [SprintController::class, 'startForm'])->name('sprints.start');
    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('sprints.show');
    Route::post('/sprints/start', [SprintController::class, 'start'])->name('sprints.start.store');
    Route::post('/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/time', [ReportController::class, 'time'])->name('reports.time');
    Route::get('/reports/tickets', [ReportController::class, 'tickets'])->name('reports.tickets');
    Route::get('/reports/sprints', [ReportController::class, 'sprints'])->name('reports.sprints');
    Route::get('/reports/blocked', [ReportController::class, 'blocked'])->name('reports.blocked');
    Route::get('/reports/export/time', [ReportController::class, 'export'])->defaults('type', 'time')->name('reports.export.time');
    Route::get('/reports/export/tickets', [ReportController::class, 'export'])->defaults('type', 'tickets')->name('reports.export.tickets');
    Route::get('/reports/export/sprints', [ReportController::class, 'export'])->defaults('type', 'sprints')->name('reports.export.sprints');
    Route::get('/reports/export/blocked', [ReportController::class, 'export'])->defaults('type', 'blocked')->name('reports.export.blocked');

    Route::get('/settings', PageController::class)
        ->defaults('section', 'settings')
        ->name('settings.index');
});

require __DIR__.'/auth.php';
