# SmartSchoolBus on DigitalOcean (Production Guide)

This guide puts your PHP + MySQL system online so your ESP32 RFID reader can send scans to a public HTTPS URL.

## 1. Architecture

- Droplet OS: Ubuntu 22.04 LTS
- Web stack: Apache + PHP 8.1+ + MariaDB
- Domain: example `bus.yourdomain.com`
- SSL: Let's Encrypt
- App path: `/var/www/smartschoolbus`

## 2. Create DigitalOcean Droplet

1. Create a new Droplet:
- Region close to your location
- Size: at least 1 vCPU / 1 GB RAM (2 GB recommended)
- Image: Ubuntu 22.04 LTS

2. Add SSH key during creation.

3. Point your domain to Droplet public IP:
- Create `A` record: `bus.yourdomain.com -> <droplet_ip>`

## 3. Initial Server Setup

SSH into server:

```bash
ssh root@<droplet_ip>
```

Create non-root user and secure SSH:

```bash
adduser deploy
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy
```

Reconnect as deploy user:

```bash
ssh deploy@<droplet_ip>
```

Enable firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow "Apache Full"
sudo ufw enable
sudo ufw status
```

## 4. Install LAMP

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-json php-mbstring php-xml php-curl php-zip unzip git
```

Enable Apache rewrite:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## 5. Database Setup

Secure MariaDB:

```bash
sudo mysql_secure_installation
```

Create DB and user:

```bash
sudo mysql -u root -p
```

Inside MySQL:

```sql
CREATE DATABASE smartschoolbus_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartschoolbus_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON smartschoolbus_db.* TO 'smartschoolbus_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import your schema from project root:

```bash
mysql -u smartschoolbus_user -p smartschoolbus_db < database.sql
```

## 6. Deploy Application Files

On local machine (project folder), upload files:

```bash
scp -r . deploy@<droplet_ip>:/home/deploy/smartschoolbus
```

On server, move into web root:

```bash
sudo mkdir -p /var/www/smartschoolbus
sudo rsync -av /home/deploy/smartschoolbus/ /var/www/smartschoolbus/
sudo chown -R www-data:www-data /var/www/smartschoolbus
sudo find /var/www/smartschoolbus -type d -exec chmod 755 {} \;
sudo find /var/www/smartschoolbus -type f -exec chmod 644 {} \;
```

Write permissions for runtime folders:

```bash
sudo chmod -R 775 /var/www/smartschoolbus/logs
sudo chmod -R 775 /var/www/smartschoolbus/uploads
```

## 7. Apache Virtual Host

Create config:

```bash
sudo nano /etc/apache2/sites-available/smartschoolbus.conf
```

Use:

```apache
<VirtualHost *:80>
    ServerName bus.yourdomain.com
    DocumentRoot /var/www/smartschoolbus

    <Directory /var/www/smartschoolbus>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/smartschoolbus_error.log
    CustomLog ${APACHE_LOG_DIR}/smartschoolbus_access.log combined
</VirtualHost>
```

Enable site:

```bash
sudo a2dissite 000-default.conf
sudo a2ensite smartschoolbus.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 8. Update Application Config

Edit `config.php` production values:

- `DB_HOST` -> `localhost`
- `DB_NAME` -> `smartschoolbus_db`
- `DB_USER` -> `smartschoolbus_user`
- `DB_PASS` -> strong password you created
- `SITE_URL` -> `https://bus.yourdomain.com`
- `ini_set('display_errors', 0)` keep disabled in production

Also remove one-time setup/reset files from production if not needed:

- `admin_password_reset.php`
- `setup.php`

## 9. Enable HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d bus.yourdomain.com
```

Test auto-renew:

```bash
sudo certbot renew --dry-run
```

## 10. RFID Endpoint Test (Public)

Test browser/API:

```text
https://bus.yourdomain.com/check.php?uid=4A3B2C1D
```

Expected response:
- `GRANTED` (known active student)
- `DENIED` (unknown card)

## 11. Update ESP32 for Production URL

In your ESP32 sketch, set:

```cpp
String serverURL = "https://bus.yourdomain.com/check.php?uid=";
```

Important:
- If you use HTTPS on ESP32, you may need certificate handling in firmware.
- For fastest initial bring-up, you can start with HTTP endpoint, verify flow, then migrate firmware to HTTPS.

## 12. Security Checklist

- Use strong DB password and unique admin password.
- Restrict SSH: disable password auth, use keys only.
- Keep system updated:

```bash
sudo apt update && sudo apt upgrade -y
```

- Remove debug/test files from public web root.
- Keep `logs/` and `uploads/` writable only as needed.
- Backup database daily.

Example backup cron:

```bash
crontab -e
```

```cron
0 2 * * * mysqldump -u smartschoolbus_user -p'CHANGE_THIS_STRONG_PASSWORD' smartschoolbus_db > /home/deploy/backups/smartschoolbus_$(date +\%F).sql
```

## 13. Troubleshooting

- Apache not serving app:
  - `sudo systemctl status apache2`
  - `sudo tail -n 100 /var/log/apache2/smartschoolbus_error.log`

- DB connection problems:
  - verify credentials in `config.php`
  - `mysql -u smartschoolbus_user -p smartschoolbus_db`

- RFID not recording:
  - test endpoint directly from browser
  - check app log: `/var/www/smartschoolbus/logs/php_errors.log`
  - check Apache access/error logs

## 14. Go-Live Validation

1. Open login page over HTTPS.
2. Login with admin.
3. Test sample RFID UID via URL.
4. Verify new row in `attendance_records`.
5. Scan real RFID from ESP32 device.
6. Verify dashboard attendance updates.

---

When this is complete, your SmartSchoolBus system is online and ready for RFID reader usage from real devices.
