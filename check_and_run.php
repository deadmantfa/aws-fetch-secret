<?php
require 'common.php';

loadConfiguration();

$cacheFilePath = '/opt/aws-fetch-secret/db_credentials.json';
$fetchSecretScriptPath = '/opt/aws-fetch-secret/fetch_secret.php';
$checkAndRunScriptPath = '/opt/aws-fetch-secret/check_and_run.php';
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
removeTemporaryCronJob($checkAndRunScriptPath);
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
