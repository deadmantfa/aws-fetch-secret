<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;

class CommonTest extends TestCase
{
    protected function setUp(): void
    {
        loadConfiguration();
    }

    public function testLogError()
    {
        // Redirect error log to a temporary stream
        $stream = fopen('php://temp', 'a+');
        ini_set('error_log', 'php://temp');

        // Log an error
        logError('This is a test error.');

        // Check the log content
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);

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
            'handler' => $mock
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
            'handler' => $mock
        ]);

        // Redirect output to capture the function's print output
        ob_start();
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);
        $output = ob_get_clean();

        $this->assertStringContainsString("Email sent: Test subject\n", $output);
    }

    public function testSendEmailNotificationInvalid()
    {
        // Redirect error log to a temporary stream
        $stream = fopen('php://temp', 'a+');
        ini_set('error_log', 'php://temp');

        // Test sending an email with an invalid recipient email
        sendEmailNotification('invalid-email', 'Test subject', 'Test body');

        // Check the log content
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('Invalid recipient email address: invalid-email', $logContent);
    }

    public function testSendEmailNotificationException()
    {
        // Mock the SES client to throw an exception
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd) {
            throw new AwsException('Error sending email', $cmd);
        });
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock
        ]);

        // Redirect error log to a temporary stream
        $stream = fopen('php://temp', 'a+');
        ini_set('error_log', 'php://temp');

        // Test sending an email and catching the exception
        sendEmailNotification('test@example.com', 'Test subject', 'Test body', $sesClient);

        // Check the log content
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('Error sending email: Error sending email', $logContent);
    }
}
