<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApiRequestNonce extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'nonce_hash',
        'request_timestamp',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_timestamp' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeveloperApplication::class, 'application_id');
    }
}
