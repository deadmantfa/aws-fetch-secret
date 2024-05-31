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
        // Load configuration from test .env file
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Mock the AWS SecretsManager client
        $secretsManagerMock = new MockHandler();
        $secretsManagerMock->append(new Result([
            'SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass']),
        ]));
        $secretsManagerMock->append(new Result([
            'NextRotationDate' => (new DateTime('+1 day'))->format(DateTime::ATOM),
        ]));
        $this->secretsManagerClient = new SecretsManagerClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $secretsManagerMock,
            'credentials' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
            ],
        ]);

        // Mock email sending function
        global $sendEmailNotification;
        $sendEmailNotification = function($recipientEmail, $subject, $body) {
            // Simulate sending an email
            echo "Email sent to {$recipientEmail} with subject: {$subject}\n";
        };
    }

    public function testFetchSecret()
    {
        $cacheDir = $_ENV['CACHE_DIR'] ?: sys_get_temp_dir() . '/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        // Mock environment variables
        $_ENV['AWS_SECRET_IDS'] = 'test-secret-id';
        $_ENV['RECIPIENT_EMAIL'] = 'test@example.com';
        $_ENV['CACHE_DIR'] = $cacheDir;

        ob_start();
        fetchSecret($this->secretsManagerClient);
        $output = ob_get_clean();

        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);

        // Verify cache file creation
        $cacheFilePath = $cacheDir . '/test-secret-id.json';
        $this->assertFileExists($cacheFilePath);

        // Clean up
        unlink($cacheFilePath);
        rmdir($cacheDir);
    }
}
