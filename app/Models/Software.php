<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Software extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'description',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
