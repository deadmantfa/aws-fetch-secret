
# AWS Fetch Secret Manager

This project is a PHP script designed to fetch secrets from AWS Secrets Manager, cache them locally, and schedule cron jobs for secret rotation. It also includes functionality to send email notifications when secrets are refreshed.

## Requirements

- PHP 8.1 or higher
- Composer
- AWS account with Secrets Manager and SES configured
- Cron jobs enabled on your server

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/aws-fetch-secret.git
   cd aws-fetch-secret
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Create and configure the `.env` file:**
   Copy the `.env.example` to `.env` and fill in your AWS configuration and recipient email:
   ```bash
   cp .env.example .env
   ```
   Edit the `.env` file and add your AWS region, secret ID, and recipient email:
   ```dotenv
   AWS_REGION=your-aws-region
   AWS_SECRET_ID=your-secret-id
   RECIPIENT_EMAIL=your-email@example.com
   ```

4. **Ensure necessary directories exist:**
   Make sure the directory for cached secrets exists and has appropriate permissions:
   ```bash
   sudo mkdir -p /opt/aws-fetch-secret
   ```

## Usage

1. **Initial Run:**
   Run the `check_and_run.php` script to fetch the secret and set up the cron job:
   ```bash
   php check_and_run.php
   ```

2. **Fetch Secret Script:**
   This script fetches the secret from AWS Secrets Manager and caches it locally. It also sends an email notification:
   ```bash
   php fetch_secret.php
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
