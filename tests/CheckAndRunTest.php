<?php

use PHPUnit\Framework\TestCase;
use Aws\Result;
use Aws\MockHandler;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Ses\SesClient;

require_once __DIR__ . '/../src/common.php';

function runFetchSecretScriptAndScheduleNextRotation($secretId, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail, callable|string $exec = 'exec'): void
{
    global $secretsManagerClient, $sesClient;

    $fetchSecretScriptPath = __DIR__ . '/../src/fetch_secret.php';
    $exec("php $fetchSecretScriptPath");

    $subject = "AWS Secret Manager: Secret Refreshed for $secretId";
    $body = "The secret has been refreshed and stored in the cache.";

    sendEmailNotification($recipientEmail, $subject, $body, $sesClient);

    if (file_exists($cacheFilePath)) {
        $cacheData = json_decode(file_get_contents($cacheFilePath), true);

        if (isset($cacheData['nextRotationDate'])) {
            $nextRotationDate = new DateTime($cacheData['nextRotationDate']);
            scheduleCronJob($nextRotationDate, "$checkAndRunScriptPath $secretId");
        }
    }
}

class CheckAndRunTest extends TestCase
{
    protected function setUp(): void
    {
        loadConfiguration();

        // Ensure the cache directory exists
        $cacheDir = $_ENV['CACHE_DIR'] ?? '/tmp/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up the mock cache directory after tests
        $cacheDir = $_ENV['CACHE_DIR'] ?? '/tmp/secrets';
        if (file_exists($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
            rmdir($cacheDir);
        }
    }

    public function testCheckAndRunWithoutCacheFile()
    {
        global $argc, $argv;
        $argc = 2;
        $argv = ['check_and_run.php', 'test-secret-id'];

        // Mock AWS Secrets Manager
        $mockHandler = new MockHandler();
        $mockHandler->append(new Result(['SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass'])]));
        $mockSecretsManager = $this->getMockBuilder(SecretsManagerClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['getSecretValue'])
            ->getMock();
        $mockSecretsManager->method('getSecretValue')
            ->willReturn(new Result(['SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass'])]));

        // Mock AWS SES
        $mockSesHandler = new MockHandler();
        $mockSesHandler->append(new Result());
        $mockSes = $this->getMockBuilder(SesClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['sendEmail'])
            ->getMock();
        $mockSes->method('sendEmail')
            ->willReturn(new Result());

        // Inject mocks into the function
        global $sesClient;
        $sesClient = $mockSes;
        global $secretsManagerClient;
        $secretsManagerClient = $mockSecretsManager;

        // Simulate running the check_and_run.php script for the test secret ID
        ob_start();
        require __DIR__ . '/../src/check_and_run.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Cache file not found for test-secret-id. Running the fetch secret script.', $output);
    }

    public function testCheckAndRunWithFutureRotationDate()
    {
        $cacheDir = $_ENV['CACHE_DIR'] ?? '/tmp/secrets';
        $secretId = 'test-secret-id';
        $cacheFilePath = $cacheDir . '/' . $secretId . '.json';
        $cacheData = [
            'secret' => ['username' => 'testuser', 'password' => 'testpass'],
            'nextRotationDate' => (new DateTime('+1 day'))->format(DateTime::ATOM),
        ];
        file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);

        global $argc, $argv;
        $argc = 2;
        $argv = ['check_and_run.php', 'test-secret-id'];

        // Simulate running the check_and_run.php script for the test secret ID
        ob_start();
        require __DIR__ . '/../src/check_and_run.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Scheduled cron job for next rotation date for test-secret-id.', $output);
    }

    public function testCheckAndRunWithPastRotationDate()
    {
        $cacheDir = $_ENV['CACHE_DIR'] ?? '/tmp/secrets';
        $secretId = 'test-secret-id';
        $cacheFilePath = $cacheDir . '/' . $secretId . '.json';
        $cacheData = [
            'secret' => ['username' => 'testuser', 'password' => 'testpass'],
            'nextRotationDate' => (new DateTime('-1 day'))->format(DateTime::ATOM),
        ];
        file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);

        global $argc, $argv;
        $argc = 2;
        $argv = ['check_and_run.php', 'test-secret-id'];

        // Mock AWS Secrets Manager
        $mockHandler = new MockHandler();
        $mockHandler->append(new Result(['SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass'])]));
        $mockSecretsManager = $this->getMockBuilder(SecretsManagerClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['getSecretValue'])
            ->getMock();
        $mockSecretsManager->method('getSecretValue')
            ->willReturn(new Result(['SecretString' => json_encode(['username' => 'testuser', 'password' => 'testpass'])]));

        // Mock AWS SES
        $mockSesHandler = new MockHandler();
        $mockSesHandler->append(new Result());
        $mockSes = $this->getMockBuilder(SesClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['sendEmail'])
            ->getMock();
        $mockSes->method('sendEmail')
            ->willReturn(new Result());

        // Inject mocks into the function
        global $sesClient;
        $sesClient = $mockSes;
        global $secretsManagerClient;
        $secretsManagerClient = $mockSecretsManager;

        ob_start();
        runFetchSecretScriptAndScheduleNextRotation($secretId, __DIR__ . '/../src/check_and_run.php', $cacheFilePath, 'test@example.com');
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('AWS Secret Manager: Secret Refreshed for test-secret-id', $output);
    }

    public function testCheckAndRunWithoutNextRotationDate()
    {
        $cacheDir = $_ENV['CACHE_DIR'] ?? '/tmp/secrets';
        $secretId = 'test-secret-id';
        $cacheFilePath = $cacheDir . '/' . $secretId . '.json';
        $cacheData = [
            'secret' => ['username' => 'testuser', 'password' => 'testpass'],
        ];
        file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);

        global $argc, $argv;
        $argc = 2;
        $argv = ['check_and_run.php', 'test-secret-id'];

        // Simulate running the check_and_run.php script for the test secret ID
        ob_start();
        require __DIR__ . '/../src/check_and_run.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Next rotation date not set in cache for test-secret-id.', $output);
    }
}
