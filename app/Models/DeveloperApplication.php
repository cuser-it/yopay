<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DeveloperApplication extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'name',
        'status',
        'allowed_notify_urls',
        'allowed_return_urls',
        'ip_allowlist',
        'minimum_amount_cents',
        'maximum_amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'allowed_notify_urls' => 'array',
            'allowed_return_urls' => 'array',
            'ip_allowlist' => 'array',
            'minimum_amount_cents' => 'integer',
            'maximum_amount_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DeveloperApiCredential::class, 'application_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class, 'application_id');
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class, 'application_id');
    }
}
