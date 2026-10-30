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

## Automatic deploys from GitHub

Every push to `main` (including merging a pull request) runs the tests, and if they pass, the **Deploy** job in `.github/workflows/ci.yml`:

1. builds the CSS/JS and uploads `public/build` to the server (swapped in all at once);
2. SSHes in, fast-forwards the server checkout to `main`, and runs `scripts/deploy.sh`, which puts the site in maintenance mode, runs `composer install --no-dev`, migrates the database, rebuilds the caches, and brings the site back up. If any step fails, the job goes red and the site is taken out of maintenance mode; read the job log, fix the cause and re-run the job.

The job does nothing until the secrets below exist, so CI stays green before the server is ready.

### One-time setup

Do the **First deploy** steps above by hand first (clone, `.env`, `key:generate`, `migrate`, `oneseller:create-admin`, document root, cron). Automatic deploys only update an existing install.

**1. A key that lets GitHub log in to your server.** On your own computer:

```bash
ssh-keygen -t ed25519 -C "github-deploy" -f oneseller_github_deploy -N ""
```

Add the contents of `oneseller_github_deploy.pub` to the server's `~/.ssh/authorized_keys` (cPanel: **SSH Access → Manage SSH Keys → Import**, then **Authorize**).

**2. The server's fingerprint**, so GitHub can be sure it is talking to your server (replace the host and port):

```bash
ssh-keyscan -p 22 your-server-host
```

**3. Secrets in GitHub:** repo **Settings → Secrets and variables → Actions → New repository secret**:

| Secret | Value |
|---|---|
| `DEPLOY_SSH_HOST` | Server hostname or IP |
| `DEPLOY_SSH_USER` | SSH username |
| `DEPLOY_SSH_PORT` | SSH port (leave unset for 22) |
| `DEPLOY_SSH_KEY` | Full contents of the private key file `oneseller_github_deploy` |
| `DEPLOY_SSH_KNOWN_HOSTS` | Output of the `ssh-keyscan` command (recommended) |
| `DEPLOY_PATH` | Absolute path of the project on the server, e.g. `/home/youruser/oneseller` |

Optional **variables** (same page, *Variables* tab) if the host's commands differ:

| Variable | Default | Example |
|---|---|---|
| `DEPLOY_PHP_BIN` | `php` | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| `DEPLOY_COMPOSER_BIN` | `composer` | `php83 /home/youruser/composer.phar` |

Delete the private key file from your computer once it is saved in GitHub.

**4. Try it:** merge a pull request into `main`, or re-run the latest CI run on `main` from the **Actions** tab. The run's **Deploy** job log ends with `Deployed <commit>`. Past deploys are listed under **Environments → production** on the repo page, where you can also add a required reviewer if you want to approve each deploy.

### Notes

- The server pulls from GitHub itself, so it keeps using the read-only deploy key from the first-deploy steps.
- Never edit code directly on the server: the deploy only fast-forwards to `main`, so it stops with a red job, before touching the site, if the server copy has its own commits or edited files in the way.
- To deploy by hand instead, run the commands under **Later deploys**, or `bash scripts/deploy.sh` after `git pull`.

## Later deploys

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Upload a freshly built `public/build/` if the front end changed. Moving to a dedicated server later only changes this setup (a supervisor-managed queue worker, Redis), not the application.
