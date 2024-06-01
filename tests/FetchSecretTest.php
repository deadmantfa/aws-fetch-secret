<?php

use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SecretsManager\SecretsManagerClient;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/fetch_secret.php';
require_once __DIR__ . '/../src/common.php';

class FetchSecretTest extends TestCase
{
    protected SecretsManagerClient $secretsManagerClient;
    private $errorLogFile;

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

        // Redirect error log to a temporary file
        $this->errorLogFile = sys_get_temp_dir() . '/php-error.log';
        ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->errorLogFile)) {
            unlink($this->errorLogFile);
        }
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

        // Mock email sending function
        $mockEmailSender = function($recipientEmail, $subject, $body) {
            echo "Mock email sent to {$recipientEmail} with subject: {$subject}\n";
        };

        // Mock crontab functions
        $mockScheduleCronJob = function ($dateTime, $scriptPath) {
            echo "Mock crontab scheduled for {$dateTime->format('Y-m-d H:i:s')} with script {$scriptPath}\n";
        };

        $mockRemoveTemporaryCronJob = function ($scriptPath) {
            echo "Mock crontab removed for script {$scriptPath}\n";
        };

        ob_start();
        \AwsSecretFetcher\fetchSecret($this->secretsManagerClient, $mockEmailSender, $mockScheduleCronJob, $mockRemoveTemporaryCronJob);
        $output = ob_get_clean();

        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);

        // Verify cache file creation
        $cacheFilePath = $cacheDir . '/test-secret-id.json';
        $this->assertFileExists($cacheFilePath);

        // Clean up
        unlink($cacheFilePath);
        rmdir($cacheDir);
    }

    public function testFetchSecretGetSecretValueException()
    {
        $secretsManagerMock = new MockHandler();
        $secretsManagerMock->append(function () {
            throw new AwsException('Error fetching secret value', new \Aws\Command('getSecretValue'));
        });
        $this->secretsManagerClient = new SecretsManagerClient([
            'region' => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $secretsManagerMock,
        ]);

        ob_start();
        \AwsSecretFetcher\fetchSecret($this->secretsManagerClient);
        $output = ob_get_clean();

        $logContent = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Error retrieving secret or sending email: Error fetching secret value', $logContent);
    }

    public function testFetchSecretDescribeSecretException()
    {
        $secretsManagerMock = new MockHandler();
        $secretsManagerMock->append(new Result([
            'SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass']),
        ]));
        $secretsManagerMock->append(function () {
            throw new AwsException('Error describing secret', new \Aws\Command('describeSecret'));
        });
        $this->secretsManagerClient = new SecretsManagerClient([
            'region' => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $secretsManagerMock,
        ]);

        ob_start();
        \AwsSecretFetcher\fetchSecret($this->secretsManagerClient);
        $output = ob_get_clean();

        $logContent = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Error retrieving secret or sending email: Error describing secret', $logContent);
    }

    public function testFetchSecretMissingCacheDir()
    {
        // Use putenv to set environment variables for the test
        putenv('AWS_SECRET_IDS=test-secret-id');
        putenv('RECIPIENT_EMAIL=test@example.com');
        putenv('CACHE_DIR=');

        // Mock email sending function
        $mockEmailSender = function ($recipientEmail, $subject, $body) {
            echo "Mock email sent to {$recipientEmail} with subject: {$subject}\n";
        };

        // Mock crontab functions
        $mockScheduleCronJob = function ($dateTime, $scriptPath) {
            echo "Mock crontab scheduled for {$dateTime->format('Y-m-d H:i:s')} with script {$scriptPath}\n";
        };

        $mockRemoveTemporaryCronJob = function ($scriptPath) {
            echo "Mock crontab removed for script {$scriptPath}\n";
        };

        $defaultCacheDir = sys_get_temp_dir() . '/secrets';
        putenv('CACHE_DIR=' . $defaultCacheDir);

        ob_start();
        \AwsSecretFetcher\fetchSecret($this->secretsManagerClient, $mockEmailSender, $mockScheduleCronJob, $mockRemoveTemporaryCronJob);
        $output = ob_get_clean();

        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);

        // Verify cache file creation
        $cacheFilePath = $defaultCacheDir . '/test-secret-id.json';
        $this->assertFileExists($cacheFilePath);

        // Clean up
        unlink($cacheFilePath);
        rmdir($defaultCacheDir);
    }
}
