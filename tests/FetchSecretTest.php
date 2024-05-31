<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\SecretsManager\SecretsManagerClient;

class FetchSecretTest extends TestCase
{
    protected function setUp(): void
    {
        loadConfiguration();
        // Mock the AWS SecretsManager client
        $mock = new MockHandler();
        $mock->append(new Result([
            'SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass']),
        ]));
        $secretsManagerClient = new SecretsManagerClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock
        ]);
        $GLOBALS['secretsManagerClient'] = $secretsManagerClient;
    }

    public function testFetchSecret()
    {
        // Ensure cache directory exists
        $cacheDir = $_ENV['CACHE_DIR'] ?: '/tmp/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        // Execute the script
        ob_start();
        require __DIR__ . '/../src/fetch_secret.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);
    }
}
