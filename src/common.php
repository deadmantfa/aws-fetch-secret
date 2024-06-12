<?php

namespace AwsSecretFetcher;

require __DIR__ . '/../vendor/autoload.php';

use Aws\Exception\AwsException;
use Aws\Sdk;
use Dotenv\Dotenv;

function logError($message): void
{
    error_log($message);
    // Optionally, send this error message to a monitoring system
}

function loadConfiguration(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../config');
    if (file_exists(__DIR__ . '/../tests/.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../tests');
    }
    $dotenv->load();
}

function createAwsClient($service, $region, $clientCreator = null)
{
    try {
        if (is_null($clientCreator)) {
            $clientCreator = function() use ($service, $region) {
                $sdk = new Sdk([
                    'region'   => $region,
                    'version'  => 'latest',
                ]);
                return $sdk->createClient($service);
            };
        }
        return $clientCreator();
    } catch (AwsException $e) {
        logError("Error creating AWS client: " . $e->getMessage());
        return null;
    }
}

function sendEmailNotification($recipientEmail, $subject, $body, $sesClient = null, $sendEmailCallable = null): void
{
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        logError("Invalid recipient email address: $recipientEmail");
        return;
    }

    if ($sesClient === null) {
        $sesClient = createAwsClient('Ses', $_ENV['AWS_REGION']);
    }

    if ($sendEmailCallable === null) {
        $sendEmailCallable = [$sesClient, 'sendEmail'];
    }

    try {
        $sendEmailCallable([
            'Source' => $recipientEmail,
            'Destination' => [
                'ToAddresses' => [$recipientEmail],
            ],
            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                    'Charset' => 'UTF-8',
                ],
                'Body' => [
                    'Text' => [
                        'Data' => $body,
                        'Charset' => 'UTF-8',
                    ],
                ],
            ],
        ]);

        echo "Email sent: $subject\n";
    } catch (AwsException $e) {
        logError('Error sending email: ' . $e->getMessage());
    }
}
