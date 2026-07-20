<?php

declare(strict_types=1);

namespace App\Domain\Developer\Services;

use App\Domain\Developer\Data\CreatedDeveloperApplication;
use App\Domain\Developer\Data\IssuedApiCredential;
use App\Models\DeveloperApiCredential;
use App\Models\DeveloperApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeveloperApplicationService
{
    public function create(User $user, string $name, array $notifyUrls, array $returnUrls): CreatedDeveloperApplication
    {
        return DB::transaction(function () use ($user, $name, $notifyUrls, $returnUrls): CreatedDeveloperApplication {
            $application = DeveloperApplication::query()->create([
                'public_id' => (string) Str::ulid(),
                'user_id' => $user->getKey(),
                'name' => $name,
                'status' => 'active',
                'allowed_notify_urls' => array_values($notifyUrls),
                'allowed_return_urls' => array_values($returnUrls),
            ]);
            $issued = $this->issueCredential($application);

            return new CreatedDeveloperApplication($application, $issued);
        }, 3);
    }

    public function rotateCredential(DeveloperApplication $application): IssuedApiCredential
    {
        return DB::transaction(function () use ($application): IssuedApiCredential {
            DeveloperApiCredential::query()
                ->where('application_id', $application->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $this->issueCredential($application);
        }, 3);
    }

    private function issueCredential(DeveloperApplication $application): IssuedApiCredential
    {
        $secret = 'sk_live_'.$this->randomSecret();
        $credential = DeveloperApiCredential::query()->create([
            'application_id' => $application->getKey(),
            'key_id' => 'key_'.strtolower((string) Str::ulid()),
            'secret_ciphertext' => $secret,
            'secret_fingerprint' => hash('sha256', $secret),
            'secret_last_four' => substr($secret, -4),
        ]);

        return new IssuedApiCredential($credential, $secret);
    }

    private function randomSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
