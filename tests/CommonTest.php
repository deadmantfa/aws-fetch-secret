<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use function AwsSecretFetcher\createAwsClient;
use function AwsSecretFetcher\loadConfiguration;
use function AwsSecretFetcher\logError;
use function AwsSecretFetcher\removeTemporaryCronJob;
use function AwsSecretFetcher\scheduleCronJob;
use function AwsSecretFetcher\sendEmailNotification;

require_once __DIR__ . '/bootstrap.php';

class CommonTest extends TestCase
{
    private string $errorLogFile;

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
        ini_set('error_log', $this->errorLogFile);
        logError('This is a test error.');
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
        $client = createAwsClient('Ses', $_ENV['AWS_REGION']);
        $this->assertInstanceOf(SesClient::class, $client);
    }

    public function testCreateAwsClientException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The service "invalidservice" is not provided by the AWS SDK for PHP.');
        createAwsClient('InvalidService', 'us-east-1');
    }

    public function testSendEmailNotificationValid()
    {
        $mock = new MockHandler();
        $mock->append(new Result([]));
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock,
            'credentials' => false,
        ]);

        ob_start();
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);
        $output = ob_get_clean();

        $this->assertStringContainsString("Email sent: Test subject\n", $output);
    }

    public function testSendEmailNotificationInvalid()
    {
        ini_set('error_log', $this->errorLogFile);
        sendEmailNotification('invalid-email', 'Test subject', 'Test body');
        $logContent = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Invalid recipient email address: invalid-email', $logContent);
    }

    public function testSendEmailNotificationException()
    {
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd) {
            throw new AwsException('Error sending email', $cmd, ['code' => 'Error']);
        });
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock,
            'credentials' => false,
        ]);

        ini_set('error_log', $this->errorLogFile);
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);
        $logContent = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Error sending email: Error sending email', $logContent);
    }

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testSendEmailNotificationWithoutClient()
    {
        $sesClientMock = $this->createMock(SesClient::class);
        $sesClientMock->expects($this->once())
            ->method('__call')
            ->with('sendEmail')
            ->willReturn(true);

        ob_start();
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClientMock);
        $output = ob_get_clean();
        $this->assertStringContainsString("Email sent: Test subject\n", $output);
    }

    public function testScheduleCronJob()
    {
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';

        exec('crontab -r');
        ob_start();
        scheduleCronJob($dateTime, $scriptPath);
        $output = ob_get_clean();
        $this->assertStringContainsString('Cron job scheduled:', $output);
    }

    public function testScheduleCronJobAlreadyScheduled()
    {
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';
        exec('echo "0 * * * * php /path/to/script.php" | crontab -');

        ob_start();
        scheduleCronJob($dateTime, $scriptPath);
        $output = ob_get_clean();
        $this->assertStringContainsString('Cron job already scheduled:', $output);
    }

    public function testScheduleCronJobFailure()
    {
        $dateTime = new DateTime();
        $scriptPath = '/path/to/script.php';

        $execMock = function($cmd, &$output, &$retval) {
            $retval = 1;
            $output = ["Mock error"];
        };

        ini_set('error_log', $this->errorLogFile);
        scheduleCronJob($dateTime, $scriptPath, $execMock);
        $logContent = file_get_contents($this->errorLogFile);
        $this->assertStringContainsString('Failed to schedule cron job:', $logContent);
    }

    public function testRemoveTemporaryCronJob()
    {
        $scriptPath = '/path/to/script.php';
        $cronCommand = "php $scriptPath";

        // Mock the existing cron jobs
        exec('echo "0 * * * * php /other_script.php\n" | crontab -');

        ob_start();
        removeTemporaryCronJob($scriptPath);
        $output = ob_get_clean();

        // Ensure the crontab was updated to remove the cron job
        $newCrontab = shell_exec('crontab -l');

        $this->assertStringNotContainsString($cronCommand, $newCrontab);
        $this->assertStringContainsString('Temporary cron job removed.', $output);

        // Ensure newline before EOF
        file_put_contents('/tmp/crontab.txt', $newCrontab . PHP_EOL);
        exec('crontab /tmp/crontab.txt');
    }

    public function testCreateAwsClientHandlesException()
    {
        $region = 'us-east-1';

        // Set the error log to capture the logError output
        ini_set('error_log', $this->errorLogFile);

        // Mock client creator to throw an AwsException
        $mockClientCreator = function() {
            throw new AwsException("Mocked AWS exception for client creation", $this->createMock(CommandInterface::class));
        };

        // Execute the function and capture the output
        $client = createAwsClient('InvalidService', $region, $mockClientCreator);
        $logContent = file_get_contents($this->errorLogFile);

        // Assertions for AwsException
        $this->assertNull($client);
        $this->assertStringContainsString("Error creating AWS client: Mocked AWS exception for client creation", $logContent);
    }
}
