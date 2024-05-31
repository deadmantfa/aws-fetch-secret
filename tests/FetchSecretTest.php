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
        // Ensure cache directory exists
        $cacheDir = $_ENV['CACHE_DIR'] ?: '/tmp/secrets';
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0750, true);
        }

        // Execute the script
        ob_start();
        require __DIR__ . '/../src/fetch_secret.php';
        $output = ob_get_clean();

        // Assert expected output
        $this->assertStringContainsString('Secret test-secret-id refreshed, stored in file cache, and email sent.', $output);
    }
}
