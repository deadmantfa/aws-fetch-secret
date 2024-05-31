<?php

use PHPUnit\Framework\TestCase;

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
        $client = createAwsClient('SecretsManager', $_ENV['AWS_REGION']);
        $this->assertInstanceOf(Aws\SecretsManager\SecretsManagerClient::class, $client);
    }
}
