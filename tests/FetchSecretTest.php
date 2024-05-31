<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\SecretsManager\SecretsManagerClient;

require_once __DIR__ . '/../src/fetch_secret.php';

class FetchSecretTest extends TestCase
{
    protected $secretsManagerClient;

    protected function setUp(): void
    {
        loadConfiguration();

        // Mock the AWS SecretsManager client
        $secretsManagerMock = new MockHandler();
        $secretsManagerMock->append(new Result([
            'SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass']),
        ]));
        $this->secretsManagerClient = new SecretsManagerClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $secretsManagerMock,
        ]);
    }

    public function testFetchSecret()
    {
        // Ensure cache directory exists
        $cacheDir = $_ENV['CACHE_DIR'] ?: '/tmp/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        // Execute the function
        ob_start();
        fetchSecret($this->secretsManagerClient);
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);
    }
}
