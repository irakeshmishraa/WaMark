# WaMark - Deployment Guide

## Deployment on cPanel Shared Hosting

### Step 1: Upload Files

1. Log into your cPanel account
2. Open **File Manager** → navigate to `public_html/`
3. Upload the entire `WaMark/` folder (or upload as ZIP and extract)
4. Final path: `/home/username/public_html/WaMark/`

### Step 2: Set Permissions

Via SSH or File Manager:
```bash
chmod -R 755 /home/username/public_html/WaMark/
chmod -R 777 /home/username/public_html/WaMark/storage/
chmod -R 777 /home/username/public_html/WaMark/uploads/
chmod 777 /home/username/public_html/WaMark/config/
chmod 666 /home/username/public_html/WaMark/.env  # After installation
```

### Step 3: Create MySQL Database

1. In cPanel → **MySQL Databases**
2. Create a new database (e.g., `username_wamark`)
3. Create a new user with a strong password
4. Add user to database with **ALL PRIVILEGES**
5. Note down: database name, username, password

### Step 4: Run Installation Wizard

1. Open: `https://yourdomain.com/WaMark/`
2. The installer will automatically redirect you
3. Complete all 5 steps:
   - Welcome screen
   - Server requirements check
   - Database configuration
   - Admin account creation
   - Complete!

### Step 5: Configure Cron Job

In cPanel → **Cron Jobs**:
- Frequency: `Every Minute (* * * * *)`
- Command: `php /home/username/public_html/WaMark/cron/run.php`

### Step 6: Post-Installation Security

1. **Delete installer:** Remove or rename `/WaMark/installer/` directory
2. **Verify .htaccess:** Ensure mod_rewrite is enabled
3. **Set config read-only:** `chmod 644 /path/WaMark/config/installed.lock`
4. **Configure SMTP:** Admin → Settings → Email
5. **Set up WhatsApp:** Admin → Settings → WhatsApp API

---

## Deployment on VPS (Ubuntu/CentOS)

### Prerequisites
```bash
# Ubuntu
apt update && apt install -y apache2 php8.2 php8.2-{mysql,mbstring,curl,gd,zip,xml} mysql-server
a2enmod rewrite
systemctl restart apache2

# CentOS
yum install -y httpd php php-{mysqlnd,mbstring,curl,gd,zip,xml} mariadb-server
systemctl start httpd mariadb
```

### Apache Virtual Host
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html

    <Directory /var/www/html/WaMark>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Setup
```bash
cp -r WaMark/ /var/www/html/
chown -R www-data:www-data /var/www/html/WaMark/
chmod -R 755 /var/www/html/WaMark/
chmod -R 777 /var/www/html/WaMark/storage/ /var/www/html/WaMark/uploads/
```

### Cron (VPS)
```bash
crontab -e
# Add:
* * * * * php /var/www/html/WaMark/cron/run.php >> /var/log/wamark-cron.log 2>&1
```

---

## Environment Variables (.env)

Key configuration options:

| Variable | Description | Example |
|----------|-------------|---------|
| APP_URL | Full URL to WaMark | https://domain.com/WaMark |
| APP_DEBUG | Enable debug mode | false |
| DB_HOST | Database host | localhost |
| DB_NAME | Database name | wamark_db |
| DB_USER | Database username | wamark_user |
| DB_PASS | Database password | secret |
| WA_API_MODE | WhatsApp mode | cloud |
| WA_CLOUD_API_TOKEN | Meta API token | EAABx... |
| STRIPE_SECRET | Stripe secret key | sk_live_... |
| CRON_SECRET_KEY | Cron URL security | auto-generated |

---

## Upgrading

1. Backup your database (Admin → Backups → Create Backup)
2. Backup your `.env` file and `uploads/` directory
3. Upload new files (overwrite existing, keeping `.env` and `uploads/`)
4. Run: `https://yourdomain.com/WaMark/cron/run.php?key=YOUR_KEY`
5. Database migrations run automatically

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 500 Error | Check PHP version (8.0+ required), check .htaccess |
| DB connection failed | Verify credentials in .env |
| Cron not running | Check cron job path, PHP binary location |
| Messages not sending | Verify WhatsApp API token, check error logs |
| Permission denied | Set storage/ and uploads/ to 777 |
| White screen | Enable APP_DEBUG=true in .env temporarily |

### Log Files
- Application logs: `storage/logs/`
- Cron logs: Database table `wm_cron_logs`
- Error logs: PHP error log (check `phpinfo()`)

---

## Support

- Documentation: This file
- Logs: Admin → Audit Logs
- System Info: Admin → Settings → Advanced
