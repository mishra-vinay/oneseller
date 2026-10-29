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

RAZORPAY_KEY_ID=rzp_live_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
```

Set up SPF, DKIM and DMARC DNS records for the sending domain as the email provider instructs, so order emails reach the inbox.

## Scheduled jobs (the one cron entry drives all of these)

Defined in `routes/console.php`:

| Job | When | Why |
|---|---|---|
| `queue:work --stop-when-empty` | every minute | Sends queued emails. Shared hosting cannot keep a worker running. |
| `oneseller:expire-unpaid-orders` | every 5 minutes | Cancels online orders not paid within 30 minutes and returns their stock. |
| `oneseller:low-stock-digest` | daily 09:00 IST | Emails each seller their low-stock products. |

## Razorpay

1. Complete Razorpay KYC and switch the dashboard to Live mode.
2. Copy the Key ID and Key Secret into `.env`.
3. Webhooks: add `https://shop.talkforweb.in/webhooks/razorpay` with events `payment.captured` and `order.paid`, set a secret, and put it in `RAZORPAY_WEBHOOK_SECRET`. The webhook confirms payments even if the buyer closes the browser before returning.
4. Refunds are issued from the Razorpay dashboard; then press "Mark refunded" on the order in `/admin/orders`.

## Backups and monitoring

- Turn on the host's daily MySQL backups, or add a cron job: `mysqldump --single-transaction oneseller | gzip > ~/backups/oneseller-$(date +\%F).sql.gz` and keep 14 days.
- Back up `storage/app/public` (product images and logos) too.
- Errors are logged to `storage/logs/laravel.log`. For alerts, add an error tracker such as Sentry (`composer require sentry/sentry-laravel` and set `SENTRY_LARAVEL_DSN`).

## Later deploys

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Upload a freshly built `public/build/` if the front end changed. Moving to a dedicated server later only changes this setup (a supervisor-managed queue worker, Redis), not the application.
