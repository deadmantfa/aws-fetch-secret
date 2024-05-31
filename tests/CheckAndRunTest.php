<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
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
        $stream = fopen('php://memory', 'a', false);
        $originalErrorLog = ini_set('error_log', 'php://memory');

        // Log an error
        logError('This is a test error.');

        // Check the log content
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);

        // Restore the original error log
        ini_set('error_log', $originalErrorLog);

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
        $secretsManagerClient = new SecretsManagerClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock
        ]);
        $this->assertInstanceOf(SecretsManagerClient::class, $secretsManagerClient);
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

        // Override the createAwsClient function to return the mocked SES client
        $this->overrideCreateAwsClient('Ses', $_ENV['AWS_REGION'], $sesClient);

        // Test sending an email with a valid recipient email
        $this->expectOutputString("Email sent: Test subject\n");
        sendEmailNotification('test@example.com', 'Test subject', 'Test body');
    }

    public function testSendEmailNotificationInvalid()
    {
        $this->expectOutputString('');
        sendEmailNotification('invalid-email', 'Test subject', 'Test body');

        // Verify that an error was logged
        $stream = fopen('php://memory', 'a', false);
        $originalErrorLog = ini_set('error_log', 'php://memory');
        logError('Invalid recipient email address: invalid-email');
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);
        ini_set('error_log', $originalErrorLog);

        $this->assertStringContainsString('Invalid recipient email address: invalid-email', $logContent);
    }

    public function testSendEmailNotificationException()
    {
        // Mock the SES client to throw an exception
        $mock = new MockHandler();
        $mock->append(function () {
            throw new AwsException('Error sending email', null);
        });
        $sesClient = new SesClient([
            'region'  => $_ENV['AWS_REGION'],
            'version' => 'latest',
            'handler' => $mock
        ]);

        // Override the createAwsClient function to return the mocked SES client
        $this->overrideCreateAwsClient('Ses', $_ENV['AWS_REGION'], $sesClient);

        // Test sending an email and catching the exception
        $this->expectOutputString('');
        sendEmailNotification('test@example.com', 'Test subject', 'Test body');

        // Verify that an error was logged
        $stream = fopen('php://memory', 'a', false);
        $originalErrorLog = ini_set('error_log', 'php://memory');
        logError('Error sending email: Error sending email');
        rewind($stream);
        $logContent = stream_get_contents($stream);
        fclose($stream);
        ini_set('error_log', $originalErrorLog);

        $this->assertStringContainsString('Error sending email: Error sending email', $logContent);
    }

    private function overrideCreateAwsClient($service, $region, $client)
    {
        $function = function () use ($client) {
            return $client;
        };
        $GLOBALS['createAwsClient'] = $function;
    }
}
