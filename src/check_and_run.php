<?php
namespace AwsSecretFetcher;

use DateTime;
use Exception;

require_once __DIR__ . '/common.php';

loadConfiguration();

$cacheDir = $_ENV['CACHE_DIR'] ?: __DIR__ . '/../secrets';
$checkAndRunScriptPath = __DIR__ . '/check_and_run.php';
$recipientEmail = $_ENV['RECIPIENT_EMAIL'];
$secretIds = explode(',', $_ENV['AWS_SECRET_IDS']);

global $argc, $argv;
if (!isset($argc)) {
    $argc = 1;
    $argv = ['check_and_run.php'];
}

if ($argc > 1) {
    // Specific secret ID passed as an argument
    $secretIds = [trim($argv[1])];
}

/**
 * @throws Exception
 */
if (!function_exists(__NAMESPACE__ . '\runFetchSecretScriptAndScheduleNextRotation')) {
    /**
     * @throws Exception
     */
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
}

foreach ($secretIds as $secretId) {
    $secretId = trim($secretId);
    $cacheFilePath = $cacheDir . '/' . $secretId . '.json';

    // Remove the initial temporary cron job
    try {
        removeTemporaryCronJob("$checkAndRunScriptPath $secretId");
    } catch (Exception $e) {
        logError('Error removing temporary cron job: ' . $e->getMessage());
    }

    try {
        if (file_exists($cacheFilePath)) {
            $cacheData = json_decode(file_get_contents($cacheFilePath), true);

            if (isset($cacheData['nextRotationDate'])) {
                $nextRotationDate = new DateTime($cacheData['nextRotationDate']);
                $now = new DateTime();

                if ($now >= $nextRotationDate) {
                    // Run the fetch secret script
                    runFetchSecretScriptAndScheduleNextRotation($secretId, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail);
                } else {
                    // Schedule cron job for next rotation date
                    scheduleCronJob($nextRotationDate, "$checkAndRunScriptPath $secretId");
                    echo "Scheduled cron job for next rotation date for $secretId.\n";
                }
            } else {
                echo "Next rotation date not set in cache for $secretId.\n";
            }
        } else {
            echo "Cache file not found for $secretId. Running the fetch secret script.\n";
            runFetchSecretScriptAndScheduleNextRotation($secretId, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail);
        }
    } catch (Exception $e) {
        logError('Error processing cache file or scheduling job: ' . $e->getMessage());
    }
}
