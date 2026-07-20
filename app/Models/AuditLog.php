<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'application_id',
        'request_id',
        'action',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
