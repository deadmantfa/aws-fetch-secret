<?php

use PHPUnit\Framework\TestCase;
use Aws\MockHandler;
use Aws\Result;
use Aws\SecretsManager\SecretsManagerClient;

class CommonTest extends TestCase
{
    protected function setUp(): void
    {
        loadConfiguration();
    }

    public function testLogError()
    {
        $this->expectOutputString('');
        logError('This is a test error.');
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
}
