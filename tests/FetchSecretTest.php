<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\SecretsManager\SecretsManagerClient;

require_once __DIR__ . '/../src/fetch_secret.php';

class FetchSecretTest extends TestCase
{
    protected SecretsManagerClient $secretsManagerClient;

    protected function setUp(): void
    {
        loadConfiguration();

        $secretsManagerMock = new MockHandler();
        $secretsManagerMock->append(new Result([
            'SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass']),
            'ARN' => 'arn:aws:secretsmanager:us-east-1:123456789012:secret:test-secret-id',
            'Name' => 'test-secret-id',
        ]));
        $secretsManagerMock->append(new Result([
            'NextRotationDate' => (new DateTime('+1 day'))->format(DateTime::ATOM),
        ]));
        $this->secretsManagerClient = new SecretsManagerClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $secretsManagerMock,
        ]);
    }

    public function testFetchSecret()
    {
        $cacheDir = $_ENV['CACHE_DIR'] ?: '/tmp/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        ob_start();
        fetchSecret($this->secretsManagerClient);
        $output = ob_get_clean();

        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);
    }
}
