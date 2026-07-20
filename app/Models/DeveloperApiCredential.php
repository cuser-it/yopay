<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DeveloperApiCredential extends Model
{
    protected $fillable = [
        'application_id',
        'key_id',
        'secret_ciphertext',
        'secret_fingerprint',
        'secret_last_four',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $hidden = ['secret_ciphertext', 'secret_fingerprint'];

    protected function casts(): array
    {
        return [
            'secret_ciphertext' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeveloperApplication::class, 'application_id');
    }
}
