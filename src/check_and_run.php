<?php
namespace AwsSecretFetcher;

use Exception;

require_once __DIR__ . '/common.php';

loadConfiguration();

$cacheDir = $_ENV['CACHE_DIR'] ?: __DIR__ . '/../secrets';
$recipientEmail = $_ENV['RECIPIENT_EMAIL'];
$secretIds = explode(',', $_ENV['AWS_SECRET_IDS']);

global $argc, $argv;
if (!isset($argc)) {
    $argc = 1;
    $argv = ['check_and_run.php'];
}

function runFetchSecretScript($secretId, $recipientEmail): void
{
    global $sesClient;

    $fetchSecretScriptPath = __DIR__ . '/../src/fetch_secret.php';
    exec("php $fetchSecretScriptPath");

    $subject = "AWS Secret Manager: Secret Refreshed for $secretId";
    $body = "The secret has been refreshed and stored in the cache.";

    sendEmailNotification($recipientEmail, $subject, $body, $sesClient);
}

foreach ($secretIds as $secretId) {
    $secretId = trim($secretId);
    $cacheFilePath = $cacheDir . '/' . $secretId . '.json';

    try {
        if (file_exists($cacheFilePath)) {
            $cacheData = json_decode(file_get_contents($cacheFilePath), true);

            if (isset($cacheData['nextRotationDate'])) {

                // Run the fetch secret script
                runFetchSecretScript($secretId, $recipientEmail);
            } else {
                echo "Next rotation date not set in cache for $secretId.\n";
            }
        } else {
            echo "Cache file not found for $secretId. Running the fetch secret script.\n";
            runFetchSecretScript($secretId, $recipientEmail);
        }
    } catch (Exception $e) {
        logError('Error processing cache file or scheduling job: ' . $e->getMessage());
    }
}
