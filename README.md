# AWS Fetch Secret Manager

This project is a PHP script designed to fetch secrets from AWS Secrets Manager, cache them locally, and schedule cron jobs for secret rotation. It also includes functionality to send email notifications when secrets are refreshed.

## Requirements

- PHP 8.1 or higher
- Composer
- AWS account with Secrets Manager and SES configured
- Cron jobs enabled on your server

## Installation

1. Install AWS CLI and configure your keys:
    ```sh
    aws configure
    ```

2. Clone the repository and install dependencies:
    ```sh
    git clone https://github.com/deadmantfa/aws-fetch-secret.git
    cd aws-fetch-secret
    composer install
    ```

3. Configure the `.env` file:
    ```sh
    cp config/.env.example config/.env
    ```

4. Update `.env` with your AWS details and other configurations.
    ```dotenv
    AWS_REGION=your-aws-region
    AWS_SECRET_IDS=your-secret-id1,your-secret-id2
    RECIPIENT_EMAIL=your-email@example.com
    CACHE_DIR=/path/to/cache
    ```

5. **Ensure necessary directories exist:**
   Make sure the directory for cached secrets exists and has appropriate permissions:
    ```bash
    mkdir -p /path/to/cache
    chmod 750 /path/to/cache
    ```

## Usage


1. **Initial Run:**
   Run the `check_and_run.php` script to fetch all secrets listed in the `.env` file and set up the cron jobs:
    ```bash
    php src/check_and_run.php
    ```

2. **Fetch Specific Secret:**
   Run the `check_and_run.php` script with a secret ID to fetch and set up the cron job for that specific secret:
    ```bash
    php src/check_and_run.php your-secret-id
    ```

3. **Fetch Secret Script:**
   This script fetches the secret from AWS Secrets Manager and caches it locally. It also sends an email notification:
    ```bash
    php src/fetch_secret.php
    ```

## File Descriptions

- **`common.php`**: Contains common functions for logging, configuration loading, AWS client creation, email notification, and cron job management.
- **`check_and_run.php`**: Main script that checks if the secret needs to be refreshed and schedules the next rotation.
- **`fetch_secret.php`**: Script to fetch the secret from AWS Secrets Manager and cache it locally.

## Error Handling

Errors are logged using PHP's `error_log` function. You can configure your server to handle these logs appropriately or send them to a monitoring system.

## Contributing

Feel free to fork this repository and submit pull requests. For major changes, please open an issue first to discuss what you would like to change.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
