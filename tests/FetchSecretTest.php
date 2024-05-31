<?php

use PHPUnit\Framework\TestCase;

class FetchSecretTest extends TestCase
{
    protected function setUp(): void
    {
        loadConfiguration();
    }

    public function testFetchSecret()
    {
        // Simulate the environment setup
        $_ENV['AWS_REGION'] = 'us-east-1';
        $_ENV['AWS_SECRET_IDS'] = 'test-secret-id';
        $_ENV['RECIPIENT_EMAIL'] = 'test@example.com';
        $_ENV['CACHE_DIR'] = '/tmp/secrets';

        // Mock AWS client and other dependencies if necessary

        // Execute the script
        ob_start();
        require __DIR__ . '/../src/fetch_secret.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);
    }
}
