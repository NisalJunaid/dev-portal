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
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FEATURE_BLOCKED = 'feature_blocked';
    public const STATUS_FEATURE_COMPLETED = 'feature_completed';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_BUG = 'bug';
    public const TYPE_FEATURE = 'feature';
    public const TYPE_TASK = 'task';

    public const URGENCIES = ['critical', 'high', 'medium', 'low'];
    public const TYPES = [self::TYPE_BUG, self::TYPE_FEATURE, self::TYPE_TASK];
    public const BUG_STATUSES = [self::STATUS_BUG_PENDING, self::STATUS_BUG_BLOCKED, self::STATUS_BUG_COMPLETED];
    public const FEATURE_STATUSES = [self::STATUS_FEATURE_APPROVED, self::STATUS_RECOMMENDED, self::STATUS_NEXT_SPRINT, self::STATUS_IN_PROGRESS, self::STATUS_FEATURE_BLOCKED, self::STATUS_FEATURE_COMPLETED];
    public const STATUSES = [
        self::STATUS_BACKLOG,
        self::STATUS_BUG_PENDING,
        self::STATUS_BUG_BLOCKED,
        self::STATUS_BUG_COMPLETED,
        self::STATUS_FEATURE_APPROVED,
        self::STATUS_RECOMMENDED,
        self::STATUS_NEXT_SPRINT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_FEATURE_BLOCKED,
        self::STATUS_FEATURE_COMPLETED,
        self::STATUS_REJECTED,
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

    public function formattedStatus(): string
    {
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

    public function isParentFeature(): bool
    {
        return $this->isFeature() && $this->parent_ticket_id === null;
    }

    public function isSubFeature(): bool
    {
        return $this->isFeature() && $this->parent_ticket_id !== null;
    }

    public function isSubtask(): bool
    {
        return $this->isTask() && $this->parent_ticket_id !== null;
    }

    public function isBlocked(): bool
    {
        return in_array($this->status, [self::STATUS_BUG_BLOCKED, self::STATUS_FEATURE_BLOCKED], true);
    }

    public function totalBlockedDurationSeconds(): int
    {
        return (int) $this->blocks()->get()->sum(fn (TicketBlock $block) => $block->currentDurationSeconds());
    }
}
