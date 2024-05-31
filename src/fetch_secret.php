<?php

require_once __DIR__ . '/common.php';

loadConfiguration();

function fetchSecret($secretsManagerClient = null): void
{
    $awsRegion = $_ENV['AWS_REGION'];
    $secretsManagerClient = $secretsManagerClient ?? createAwsClient('SecretsManager', $awsRegion);

    $secretIds = explode(',', $_ENV['AWS_SECRET_IDS']);
    $recipientEmail = $_ENV['RECIPIENT_EMAIL'];
    $cacheDir = $_ENV['CACHE_DIR'] ?: __DIR__ . '/../secrets';

    foreach ($secretIds as $secretId) {
        try {
            $secretValueResult = $secretsManagerClient->getSecretValue(['SecretId' => trim($secretId)]);
            $secret = $secretValueResult['SecretString'];

            if (is_string($secret)) {
                $descriptionResult = $secretsManagerClient->describeSecret(['SecretId' => trim($secretId)]);
                $nextRotationDate = $descriptionResult['NextRotationDate'] ?? null;

                $cacheData = [
                    'secret' => json_decode($secret, true),
                    'nextRotationDate' => $nextRotationDate,
                ];

                if (!file_exists($cacheDir)) {
                    mkdir($cacheDir, 0750, true);
                }

                $cacheFilePath = $cacheDir . '/' . trim($secretId) . '.json';
                file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);
                chmod($cacheFilePath, 0640);

                $subject = 'AWS Secret Manager: Secret Refreshed';
                $body = "The secret for {$secretId} has been refreshed and stored in the cache.\n\nNext rotation date: $nextRotationDate";

                sendEmailNotification($recipientEmail, $subject, $body);

                echo "Secret {$secretId} refreshed, stored in file cache, and email sent.\n";
            }
        } catch (Exception $e) {
            logError('Error retrieving secret or sending email: ' . $e->getMessage());
        }
    }
}

if (php_sapi_name() !== 'cli-server' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    fetchSecret();
}
