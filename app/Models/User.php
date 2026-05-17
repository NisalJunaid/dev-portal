<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isKielUser(): bool
    {
        return $this->hasAnyRole(['super_admin', 'kiel_manager', 'developer']);
    }

    public function isClientUser(): bool
    {
        return $this->hasAnyRole(['client_admin', 'client_user']);
    }

    public function canAccessClient(?Client $client): bool
    {
        if ($this->isSuperAdmin() || $this->isKielUser()) {
            return true;
        }

        return $client !== null && $this->client_id !== null && $this->client_id === $client->id;
    }

    public function submittedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'submitted_by');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function blockedTickets(): HasMany
    {
        return $this->hasMany(TicketBlock::class, 'blocked_by');
    }

    public function unblockedTickets(): HasMany
    {
        return $this->hasMany(TicketBlock::class, 'unblocked_by');
    }

    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }
}

