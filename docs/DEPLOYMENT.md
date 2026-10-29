# Deploying OneSeller to shared hosting

The MVP runs on shared hosting under a talkforweb.in subdomain (example: `shop.talkforweb.in`).

## Hosting requirements

- PHP 8.3 or newer with `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `fileinfo`
- MySQL 8 or MariaDB 10.6+
- The subdomain's document root can point at the project's `public/` folder
- A cron job that runs every minute
- SSH access (strongly preferred). Without it, deploy by uploading a built bundle over FTP.

## First deploy

1. Create the MySQL database and user in the hosting panel.
2. Upload the project (or `git clone` it over SSH) outside the web root, e.g. `~/oneseller`.
3. Point the subdomain's document root at `~/oneseller/public`.
4. On the server:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env    # then edit it, see below
   php artisan key:generate
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan oneseller:create-admin
   ```
   Build the front-end assets (`npm ci && npm run build`) locally or in CI and upload `public/build/`. The server does not need Node.
5. Add the cron entry:
   ```
   * * * * * cd ~/oneseller && php artisan schedule:run >> /dev/null 2>&1
   ```

## Production `.env`

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shop.talkforweb.in

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=...          # from Brevo / Amazon SES / Postmark
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="orders@talkforweb.in"
MAIL_FROM_NAME="OneSeller"
```

Set up SPF, DKIM and DMARC DNS records for the sending domain as the email provider instructs, so order emails reach the inbox.

## Queued jobs on shared hosting

Shared hosting cannot keep a `queue:work` process running. Once queued emails arrive in later milestones, the scheduler processes the queue every minute:

```php
// routes/console.php
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
```

## Later deploys

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Upload a freshly built `public/build/` if the front end changed. Moving to a dedicated server later only changes this setup (a supervisor-managed queue worker, Redis), not the application.
