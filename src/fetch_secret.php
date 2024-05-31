<?php
require 'common.php';

loadConfiguration();

$awsRegion = $_ENV['AWS_REGION'];
$secretsManagerClient = createAwsClient('SecretsManager', $awsRegion);
$sesClient = createAwsClient('Ses', $awsRegion);

// Get the secret IDs from .env
$secretIds = explode(',', $_ENV['AWS_SECRET_IDS']);
$recipientEmail = $_ENV['RECIPIENT_EMAIL'];
$cacheDir = $_ENV['CACHE_DIR'] ?: __DIR__ . '/../secrets';

foreach ($secretIds as $secretId) {
    try {
        // Fetch the secret from AWS Secrets Manager
        $secretValueResult = $secretsManagerClient->getSecretValue([
            'SecretId' => trim($secretId),
        ]);

        $secret = $secretValueResult['SecretString'];
        if (is_string($secret)) {
            // Fetch the rotation configuration for the secret
            $descriptionResult = $secretsManagerClient->describeSecret([
                'SecretId' => trim($secretId),
            ]);

            // Fetch the next rotation date
            $nextRotationDate = $descriptionResult['NextRotationDate'] ?? null;

            // Prepare the data to be cached, including the secret and the next rotation date
            $cacheData = [
                'secret' => json_decode($secret, true), // Assuming the secret itself is a JSON string
                'nextRotationDate' => $nextRotationDate,
            ];

            // Ensure directory exists
            if (!file_exists($cacheDir)) {
                mkdir($cacheDir, 0750, true);
            }

            // Store the prepared data in a file with restricted permissions
            $cacheFilePath = $cacheDir . '/' . trim($secretId) . '.json';
            file_put_contents($cacheFilePath, json_encode($cacheData), LOCK_EX);
            chmod($cacheFilePath, 0640); // Sets the file permission to read/write for owner only

            // Send notification email
            $subject = 'AWS Secret Manager: Secret Refreshed';
            $body = "The secret for {$secretId} has been refreshed and stored in the cache.\n\nNext rotation date: $nextRotationDate";

            sendEmailNotification($recipientEmail, $subject, $body);

            echo "Secret {$secretId} refreshed, stored in file cache, and email sent.\n";
        }

    } catch (Exception $e) {
        logError('Error retrieving secret or sending email: ' . $e->getMessage());
        // Handle exception
    }
}
