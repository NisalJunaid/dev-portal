<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_BUG_PENDING = 'bug_pending';
    public const STATUS_BUG_BLOCKED = 'bug_blocked';
    public const STATUS_BUG_COMPLETED = 'bug_completed';
    public const STATUS_FEATURE_APPROVED = 'feature_approved';
    public const STATUS_RECOMMENDED = 'recommended';
    public const STATUS_NEXT_SPRINT = 'next_sprint';
    public const STATUS_FEATURE_IN_SPRINT = 'feature_in_sprint';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_TASK_BLOCKED = 'task_blocked';
    public const STATUS_TASK_COMPLETED = 'task_completed';
    public const STATUS_FEATURE_BLOCKED = 'feature_blocked';
    public const STATUS_FEATURE_COMPLETED = 'feature_completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_TRIAGE_PENDING = 'triage_pending';

    public const TYPE_BUG = 'bug';
    public const TYPE_FEATURE = 'feature';
    public const TYPE_TASK = 'task';

    public const URGENCIES = ['critical', 'high', 'medium', 'low'];
    public const TYPES = [self::TYPE_BUG, self::TYPE_FEATURE, self::TYPE_TASK];
    public const BUG_STATUSES = [self::STATUS_BUG_PENDING, self::STATUS_BUG_BLOCKED, self::STATUS_BUG_COMPLETED];
    public const FEATURE_STATUSES = [self::STATUS_FEATURE_APPROVED, self::STATUS_RECOMMENDED, self::STATUS_NEXT_SPRINT, self::STATUS_FEATURE_IN_SPRINT, self::STATUS_IN_PROGRESS, self::STATUS_FEATURE_BLOCKED, self::STATUS_FEATURE_COMPLETED];
    public const TASK_STATUSES = [self::STATUS_BACKLOG, self::STATUS_IN_PROGRESS, self::STATUS_TASK_BLOCKED, self::STATUS_TASK_COMPLETED];

    public const STATUSES = [
        self::STATUS_BACKLOG,
        self::STATUS_BUG_PENDING,
        self::STATUS_BUG_BLOCKED,
        self::STATUS_BUG_COMPLETED,
        self::STATUS_FEATURE_APPROVED,
        self::STATUS_RECOMMENDED,
        self::STATUS_NEXT_SPRINT,
        self::STATUS_FEATURE_IN_SPRINT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_TASK_BLOCKED,
        self::STATUS_TASK_COMPLETED,
        self::STATUS_FEATURE_BLOCKED,
        self::STATUS_FEATURE_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_TRIAGE_PENDING,
    ];

    protected $fillable = [
        'client_id',
        'software_id',
        'submitted_by',
        'assigned_to',
        'ticket_no',
        'title',
        'description',
        'urgency',
        'type',
        'status',
        'rejection_reason',
        'submitted_at',
        'classified_at',
        'completed_at',
        'start_date',
        'due_date',
        'estimated_hours',
        'actual_completed_at',
        'priority_order',
        'timeline_position',
        'parent_ticket_id',
        'depends_on_ticket_id',
        'source_feature_id',
        'is_generated_task',
        'generated_from_sprint_id',
        'archived_at',
        'archived_reason',
        'returned_to_sprint_at',
        'returned_from_task_id',
        'requested_type',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'classified_at' => 'datetime',
        'completed_at' => 'datetime',
        'start_date' => 'date',
        'due_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_completed_at' => 'datetime',
        'is_generated_task' => 'boolean',
        'archived_at' => 'datetime',
        'returned_to_sprint_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ticket_id')->orderBy('priority_order')->orderBy('id');
    }

    public function subFeatures(): HasMany
    {
        return $this->children()->where('type', self::TYPE_FEATURE);
    }

    public function subtasks(): HasMany
    {
        return $this->children()->where('type', self::TYPE_TASK);
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_ticket_id');
    }


    public function sourceFeature(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_feature_id');
    }

    public function generatedTasks(): HasMany
    {
        return $this->hasMany(self::class, 'source_feature_id');
    }

    public function generatedFromSprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class, 'generated_from_sprint_id');
    }

    public function returnedFromTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'returned_from_task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->latest();
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TicketBlock::class);
    }

    public function activeBlock()
    {
        return $this->hasOne(TicketBlock::class)->whereNull('unblocked_at')->latestOfMany('blocked_at');
    }

    public function sprints(): BelongsToMany
    {
        return $this->belongsToMany(Sprint::class, 'sprint_items')->withPivot('position')->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isKielUser()) {
            return $query;
        }

        return $query->where('client_id', $user->client_id);
    }


    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }


    public static function taskStatusRank(string $status): int
    {
        return match ($status) {
            self::STATUS_BACKLOG => 1,
            self::STATUS_IN_PROGRESS => 2,
            self::STATUS_TASK_BLOCKED => 3,
            self::STATUS_TASK_COMPLETED, self::STATUS_REJECTED => 4,
            default => 0,
        };
    }

    public function formattedStatus(): string
    {
        if ($this->status === self::STATUS_TASK_COMPLETED) {
            return 'Completed';
        }

        if ($this->status === self::STATUS_TASK_BLOCKED) {
            return 'Blocked';
        }

        return str($this->status)->replace('_', ' ')->headline()->toString();
    }

    public function formattedUrgency(): string
    {
        return str($this->urgency)->headline()->toString();
    }

    public function isBug(): bool
    {
        return $this->type === self::TYPE_BUG;
    }

    public function isCompletedBug(): bool
    {
        return $this->status === self::STATUS_BUG_COMPLETED;
    }

    public function isFeature(): bool
    {
        return $this->type === self::TYPE_FEATURE;
    }

    public function isCompletedFeature(): bool
    {
        return $this->status === self::STATUS_FEATURE_COMPLETED;
    }

    public function isTask(): bool
    {
        return $this->type === self::TYPE_TASK || $this->is_generated_task;
    }


    public function canHaveSubtasks(): bool
    {
        return $this->isTask()
            && $this->parent_ticket_id === null;
    }

    public function isSubtask(): bool
    {
        return $this->isTask()
            && $this->parent_ticket_id !== null;
    }

    public function isParentFeature(): bool
    {
        return $this->isFeature() && $this->parent_ticket_id === null;
    }

    public function isSubFeature(): bool
    {
        return $this->isFeature() && $this->parent_ticket_id !== null;
    }


    public function listSectionKey(?string $workType = null): string
    {
        if ($workType === 'triage') {
            return 'pending';
        }

        if ($workType === 'bugs' || $this->isBug()) {
            return match ($this->status) {
                self::STATUS_BUG_BLOCKED => 'blocked',
                self::STATUS_BUG_COMPLETED, self::STATUS_REJECTED => 'completed',
                default => 'pending',
            };
        }

        if ($this->isDone()) return 'completed';
        if ($this->status === self::STATUS_IN_PROGRESS) return 'in_progress';
        return 'backlog';
    }

    public static function listSections(string $workType = 'tasks'): array
    {
        return match ($workType) {
            'triage' => ['pending' => 'Pending Review'],
            'bugs' => ['pending' => 'Pending', 'blocked' => 'Blocked', 'completed' => 'Completed'],
            default => ['backlog' => 'Backlog', 'in_progress' => 'In Progress', 'completed' => 'Completed'],
        };
    }

    public static function statusForListSection(self $ticket, string $section): string
    {
        return match ($section) {
            'in_progress' => self::STATUS_IN_PROGRESS,
            'completed' => $ticket->isBug() ? self::STATUS_BUG_COMPLETED : self::STATUS_TASK_COMPLETED,
            default => self::STATUS_BACKLOG,
        };
    }


    public function isTriage(): bool
    {
        return $this->type === null || $this->status === self::STATUS_TRIAGE_PENDING;
    }
    public function isDone(): bool
    {
        return in_array($this->status, [self::STATUS_BUG_COMPLETED, self::STATUS_FEATURE_COMPLETED, self::STATUS_TASK_COMPLETED], true);
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, [self::STATUS_BUG_BLOCKED, self::STATUS_FEATURE_BLOCKED, self::STATUS_TASK_BLOCKED], true);
    }

    public function isTerminalForSprint(): bool
    {
        $terminalStatuses = array_values(array_filter([
            self::STATUS_BUG_COMPLETED,
            self::STATUS_FEATURE_COMPLETED,
            self::STATUS_REJECTED,
        self::STATUS_TRIAGE_PENDING,
            self::STATUS_BUG_BLOCKED,
            self::STATUS_FEATURE_BLOCKED,
            defined('self::STATUS_TASK_COMPLETED') ? self::STATUS_TASK_COMPLETED : null,
            defined('self::STATUS_TASK_BLOCKED') ? self::STATUS_TASK_BLOCKED : null,
        ]));

        return in_array($this->status, $terminalStatuses, true);
    }

    public function isActiveForSprint(): bool
    {
        return ! $this->isTerminalForSprint();
    }

    public function isGeneratedSprintTask(): bool
    {
        return $this->type === self::TYPE_TASK
            && $this->is_generated_task
            && $this->generated_from_sprint_id !== null;
    }

    public function totalBlockedDurationSeconds(): int
    {
        return (int) $this->blocks()->get()->sum(fn (TicketBlock $block) => $block->currentDurationSeconds());
    }
}
