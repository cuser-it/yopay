<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WebhookEndpoint extends Model
{
    protected $fillable = [
        'application_id',
        'name',
        'url',
        'url_hash',
        'secret_ciphertext',
        'subscribed_events',
        'enabled',
        'last_success_at',
        'last_failure_at',
    ];

    protected $hidden = ['secret_ciphertext'];

    protected function casts(): array
    {
        return [
            'secret_ciphertext' => 'encrypted',
            'subscribed_events' => 'array',
            'enabled' => 'boolean',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(DeveloperApplication::class, 'application_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_endpoint_id');
    }
}
