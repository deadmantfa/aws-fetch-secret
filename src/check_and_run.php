<?php
require 'common.php';

loadConfiguration();

$cacheDir = $_ENV['CACHE_DIR'] ?: __DIR__ . '/../secrets';
$fetchSecretScriptPath = __DIR__ . '/fetch_secret.php';
$checkAndRunScriptPath = __DIR__ . '/check_and_run.php';
$recipientEmail = $_ENV['RECIPIENT_EMAIL'];

/**
 * @throws Exception
 */
function runFetchSecretScriptAndScheduleNextRotation($fetchSecretScriptPath, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail): void
{
    exec("php $fetchSecretScriptPath");

    $subject = 'AWS Secret Manager: Secret Refreshed';
    $body = "The secret has been refreshed and stored in the cache.";

    sendEmailNotification($recipientEmail, $subject, $body);

    if (file_exists($cacheFilePath)) {
        $cacheData = json_decode(file_get_contents($cacheFilePath), true);

        if (isset($cacheData['nextRotationDate'])) {
            $nextRotationDate = new DateTime($cacheData['nextRotationDate']);
            scheduleCronJob($nextRotationDate, $checkAndRunScriptPath);
        }
    }
}

// Remove the initial temporary cron job
try {
    removeTemporaryCronJob($checkAndRunScriptPath);
} catch (Exception $e) {
    logError('Error removing temporary cron job: ' . $e->getMessage());
}

try {
    $cacheFilePath = $cacheDir . '/db_credentials.json';
    if (file_exists($cacheFilePath)) {
        $cacheData = json_decode(file_get_contents($cacheFilePath), true);

        if (isset($cacheData['nextRotationDate'])) {
            $nextRotationDate = new DateTime($cacheData['nextRotationDate']);
            $now = new DateTime();

            if ($now >= $nextRotationDate) {
                // Run the fetch secret script
                runFetchSecretScriptAndScheduleNextRotation($fetchSecretScriptPath, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail);
            } else {
                // Schedule cron job for next rotation date
                scheduleCronJob($nextRotationDate, $checkAndRunScriptPath);
                echo "Scheduled cron job for next rotation date.\n";
            }
        } else {
            echo "Next rotation date not set in cache.\n";
        }
    } else {
        echo "Cache file not found. Running the fetch secret script.\n";
        runFetchSecretScriptAndScheduleNextRotation($fetchSecretScriptPath, $checkAndRunScriptPath, $cacheFilePath, $recipientEmail);
    }
} catch (Exception $e) {
    logError('Error processing cache file or scheduling job: ' . $e->getMessage());
}
