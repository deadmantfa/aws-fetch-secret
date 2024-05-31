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

        // Set AWS credentials via environment variables
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_REGION=' . $_ENV['AWS_REGION']);

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
        ]);

        // Mock email sending function
        global $sendEmailNotification;
        $sendEmailNotification = function($recipientEmail, $subject, $body) {
            // Simulate sending an email
            echo "Mock email sent to {$recipientEmail} with subject: {$subject}\n";
        };

        // Mock logging function
        global $logError;
        $logError = function($message) {
            // Simulate logging an error
            echo "Logged error: {$message}\n";
        };
    }

    public function testFetchSecret()
    {
        // Use putenv to set environment variables for the test
        putenv('AWS_SECRET_IDS=test-secret-id');
        putenv('RECIPIENT_EMAIL=test@example.com');
        putenv('CACHE_DIR=' . sys_get_temp_dir() . '/secrets');

        $cacheDir = getenv('CACHE_DIR');
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        ob_start();
        \AwsSecretFetcher\fetchSecret($this->secretsManagerClient);
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
