<?php

use PHPUnit\Framework\TestCase;

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

        // Create a mock cache file for testing
        $secretId = 'test-secret-id';
        $cacheFilePath = $cacheDir . '/' . $secretId . '.json';
        $cacheData = [
            'secret' => ['username' => 'testuser', 'password' => 'testpass'],
            'nextRotationDate' => (new DateTime('+1 day'))->format(DateTime::ATOM),
        ];
        file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);
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

    public function testCheckAndRun()
    {
        // Initialize argc and argv for the command-line simulation
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
}
