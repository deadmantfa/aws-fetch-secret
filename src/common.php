<?php

namespace AwsSecretFetcher;

require __DIR__ . '/../vendor/autoload.php';

use Aws\Exception\AwsException;
use Aws\Sdk;
use DateInterval;
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

function scheduleCronJob($dateTime, $scriptPath, $exec = 'exec'): void
{
    $dateTime->add(new DateInterval('PT1M')); // Add 1-minute delay
    $cronTime = $dateTime->format('i H d m *');
    $cronCommand = "php $scriptPath";
    $cronJob = "$cronTime $cronCommand\n";

    // Read existing cron jobs
    $output = [];
    $status = 0;
    $exec('crontab -l', $output, $status);

    if ($status !== 0) {
        // No crontab for this user
        $output = [];
    }

    // Check if the cron job already exists
    foreach ($output as $line) {
        if (str_contains($line, $cronCommand)) {
            echo "Cron job already scheduled: $line\n";
            return;
        }
    }

    // Add new cron job
    $output[] = $cronJob;
    file_put_contents('/tmp/crontab.txt', implode("\n", $output));
    $execOutput = [];
    $execStatus = 0;
    $exec('crontab /tmp/crontab.txt', $execOutput, $execStatus);
    unlink('/tmp/crontab.txt'); // Clean up temp file

    if ($execStatus === 0) {
        echo "Cron job scheduled: $cronJob\n";
    } else {
        logError("Failed to schedule cron job: $cronJob\n" . implode("\n", $execOutput));
    }
}

function removeTemporaryCronJob($scriptPath): void
{
    $cronCommand = "php $scriptPath";
    $output = [];
    exec('crontab -l', $output);
    $newCrontab = [];

    foreach ($output as $line) {
        if (!str_contains($line, $cronCommand)) {
            $newCrontab[] = $line;
        }
    }

    file_put_contents('/tmp/crontab.txt', implode("\n", $newCrontab));
    exec('crontab /tmp/crontab.txt');
    unlink('/tmp/crontab.txt'); // Clean up temp file

    echo "Temporary cron job removed.\n";
}
