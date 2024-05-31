<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;

class CommonTest extends TestCase
{
    private $errorLogFile;

    protected function setUp(): void
    {
        loadConfiguration();
        $this->errorLogFile = sys_get_temp_dir() . '/php-error.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->errorLogFile)) {
            unlink($this->errorLogFile);
        }
    }

    public function testLogError()
    {
        // Redirect error log to a temporary file
        ini_set('error_log', $this->errorLogFile);

        // Log an error
        logError('This is a test error.');

        // Read the log content
        $logContent = file_get_contents($this->errorLogFile);

        $this->assertStringContainsString('This is a test error.', $logContent);
    }

    public function testLoadConfiguration()
    {
        $this->assertArrayHasKey('AWS_REGION', $_ENV);
        $this->assertArrayHasKey('AWS_SECRET_IDS', $_ENV);
        $this->assertArrayHasKey('RECIPIENT_EMAIL', $_ENV);
    }

    public function testCreateAwsClient()
    {
        // Mock the AWS SecretsManager client
        $mock = new MockHandler();
        $mock->append(new Result([]));
        $secretsManagerClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock,
            'credentials' => false,  // Ensure no real credentials are used
        ]);

        $this->assertInstanceOf(SesClient::class, $secretsManagerClient);
    }

    public function testSendEmailNotificationValid()
    {
        // Mock the SES client
        $mock = new MockHandler();
        $mock->append(new Result([]));
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock,
            'credentials' => false,  // Ensure no real credentials are used
        ]);

        // Redirect output to capture the function's print output
        $obLevel = ob_get_level();
        ob_start();
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);
        $output = ob_get_clean();
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        $this->assertStringContainsString("Email sent: Test subject\n", $output);
    }

    public function testSendEmailNotificationInvalid()
    {
        // Redirect error log to a temporary file
        ini_set('error_log', $this->errorLogFile);

        // Test sending an email with an invalid recipient email
        sendEmailNotification('invalid-email', 'Test subject', 'Test body');

        // Read the log content
        $logContent = file_get_contents($this->errorLogFile);

        $this->assertStringContainsString('Invalid recipient email address: invalid-email', $logContent);
    }

    public function testSendEmailNotificationException()
    {
        // Mock the SES client to throw an exception
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd) {
            throw new AwsException('Error sending email', $cmd, ['code' => 'Error']);
        });
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock,
            'credentials' => false,  // Ensure no real credentials are used
        ]);

        // Redirect error log to a temporary file
        ini_set('error_log', $this->errorLogFile);

        // Test sending an email and catching the exception
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);

        // Read the log content
        $logContent = file_get_contents($this->errorLogFile);

        $this->assertStringContainsString('Error sending email: Error sending email', $logContent);
    }

    public function testScheduleCronJob()
    {
        // Simulate the scenario where the cron job is not already scheduled
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';

        // Clear any existing cron jobs
        exec('crontab -r');

        // Redirect output to capture the function's print output
        ob_start();
        scheduleCronJob($dateTime, $scriptPath);
        $output = ob_get_clean();

        // Assert the expected output
        $this->assertStringContainsString('Cron job scheduled:', $output);
    }

    public function testScheduleCronJobAlreadyScheduled()
    {
        // Simulate the scenario where the cron job is already scheduled
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';

        // Mock the existing cron job
        exec('echo "0 * * * * php /path/to/script.php" | crontab -');

        // Redirect output to capture the function's print output
        ob_start();
        scheduleCronJob($dateTime, $scriptPath);
        $output = ob_get_clean();

        // Assert the expected output
        $this->assertStringContainsString('Cron job already scheduled:', $output);
    }

    public function testScheduleCronJobFailure()
    {
        // Simulate the scenario where scheduling a cron job fails
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';

        // Mock the exec function to simulate failure
        $execMock = function($cmd, &$output, &$retval) {
            $retval = 1;
            $output = ["Mock error"];
        };

        // Redirect error log to a temporary file
        ini_set('error_log', $this->errorLogFile);

        // Test scheduling a cron job and catching the failure
        scheduleCronJob($dateTime, $scriptPath, $execMock);

        // Read the log content
        $logContent = file_get_contents($this->errorLogFile);

        $this->assertStringContainsString('Failed to schedule cron job:', $logContent);
    }

    public function testRemoveTemporaryCronJob()
    {
        // Mock the cron job removal
        $scriptPath = '/path/to/script.php';

        // Redirect output to capture the function's print output
        ob_start();
        removeTemporaryCronJob($scriptPath);
        $output = ob_get_clean();

        // Assert the expected output
        $this->assertStringContainsString('Temporary cron job removed.', $output);
    }
}
