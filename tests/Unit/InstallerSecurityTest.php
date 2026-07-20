<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Install\EnvironmentFileWriter;
use App\Domain\Install\InstallAccessService;
use App\Domain\Install\InstallSessionStore;
use App\Domain\Install\InstallState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class InstallerSecurityTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = storage_path('framework/testing/installer-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->temporaryDirectory, 0700);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_install_token_is_only_stored_as_a_hash_and_lock_prevents_rotation(): void
    {
        $state = new InstallState($this->temporaryDirectory);
        $token = $state->generateAccessToken();

        $this->assertTrue($state->verifyAccessToken($token));
        $this->assertStringNotContainsString($token, (string) file_get_contents($this->temporaryDirectory.'/install-token'));

        $state->writeInstalledLock(['gateway' => 'easypay_v2']);

        $this->assertTrue($state->isInstalled());
        $this->expectException(\RuntimeException::class);
        $state->generateAccessToken(true);
    }

    public function test_install_access_cookie_is_signed_and_tampering_is_rejected(): void
    {
        $state = new InstallState($this->temporaryDirectory);
        $state->generateAccessToken();
        $service = new InstallAccessService($state);
        $request = Request::create('https://pay.example.com/install/access', 'POST');
        $cookie = $service->issueCookie($request);
        $authenticatedRequest = Request::create('https://pay.example.com/install/requirements');
        $authenticatedRequest->cookies->set(InstallAccessService::COOKIE_NAME, $cookie->getValue());

        $this->assertNotNull($service->accessContext($authenticatedRequest));

        $authenticatedRequest->cookies->set(InstallAccessService::COOKIE_NAME, $cookie->getValue().'tampered');
        $this->assertNull($service->accessContext($authenticatedRequest));
    }

    public function test_install_draft_is_encrypted_at_rest(): void
    {
        $state = new InstallState($this->temporaryDirectory);
        $state->generateAccessToken();
        $store = new InstallSessionStore($state);
        $sessionId = bin2hex(random_bytes(16));
        $draft = ['database' => ['password' => 'database-secret']];

        $store->write($sessionId, $draft);

        $contents = (string) file_get_contents($this->temporaryDirectory.'/install-session-'.$sessionId.'.json');
        $this->assertStringNotContainsString('database-secret', $contents);
        $this->assertSame($draft, $store->read($sessionId));
    }

    public function test_environment_writer_updates_atomically_and_escapes_multiline_secrets(): void
    {
        $environmentPath = $this->temporaryDirectory.'/.env';
        $examplePath = $this->temporaryDirectory.'/.env.example';
        file_put_contents($examplePath, "APP_KEY=\nDB_PASSWORD=\nEASYPAY_V2_MERCHANT_PRIVATE_KEY=\n");
        $writer = new EnvironmentFileWriter($environmentPath, $examplePath);

        $writer->update([
            'APP_KEY' => 'base64:test-key',
            'DB_PASSWORD' => 'p@ss$word',
            'EASYPAY_V2_MERCHANT_PRIVATE_KEY' => "line-one\nline-two",
        ]);

        $contents = (string) file_get_contents($environmentPath);
        $this->assertStringContainsString('APP_KEY="base64:test-key"', $contents);
        $this->assertStringContainsString('DB_PASSWORD="p@ss\\$word"', $contents);
        $this->assertStringContainsString('EASYPAY_V2_MERCHANT_PRIVATE_KEY="line-one\\nline-two"', $contents);
    }
}
