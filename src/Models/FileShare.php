<?php

namespace LucasBarros\LaravelFileManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileShare extends Model
{
    protected $table = 'file_shares';

    protected $fillable = [
        'file_id',

        'token',

        'creator_type',
        'creator_id',

        'expires_at',

        'revoked_at',

        'last_accessed_at',

        'access_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_accessed_at' => 'datetime',

        'access_count' => 'integer',
    ];

    protected $hidden = [
        'creator_type',
        'creator_id',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(
            File::class
        );
    }

    public function creator()
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired()
            && ! $this->isRevoked();
    }

    public function registerAccess(): void
    {
        $this->increment('access_count');

        $this->update([
            'last_accessed_at' => now(),
        ]);
    }
}