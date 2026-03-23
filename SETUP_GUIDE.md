# SelamatRide SmartSchoolBus - RFID System Setup Guide

## 🎯 QUICK START

### 1. **Test Login**
- URL: `http://localhost/SmartSchoolBus/login.php`
- Test Credentials:
  - **Username:** `admin`
  - **Password:** Generate one using the password tool below
  
### 2. **Generate Admin Password Hash**
Create a file `admin_reset.php` and run it once to get credentials:
```php
<?php
// Generate a test password for admin
$testPassword = "admin123";
$hash = password_hash($testPassword, PASSWORD_BCRYPT, ['cost' => 10]);
echo "Use this password hash in the database:\n";
echo $hash . "\n";
echo "\nLogin with:\n";
echo "Username: admin\n";
echo "Password: " . $testPassword . "\n";
?>
```

### 3. **Setup Your RFID Reader (ESP32)**

#### Step 1: Find Your Computer's Local IP
1. Open Command Prompt (Windows)
2. Type: `ipconfig`
3. Look for "IPv4 Address" under your network adapter
4. Note the IP (e.g., `192.168.1.100` or `172.20.10.4`)

#### Step 2: Update ESP32 Code
Edit `Arduino/ESP32_RFID_Reader.ino` line ~24:
```cpp
// Update this IP to your computer's local IP
String serverURL = "http://YOUR_IP/SmartSchoolBus/check.php?uid=";
// Example:
String serverURL = "http://192.168.1.100/SmartSchoolBus/check.php?uid=";
```

#### Step 3: Upload Code to ESP32
1. Connect ESP32 via USB to your computer
2. Open Arduino IDE
3. Open `Arduino/ESP32_RFID_Reader.ino`
4. Select Board: `ESP32 Dev Module`
5. Select COM Port (your USB port)
6. Click **Upload**

#### Step 4: Test RFID Reader
1. Open Serial Monitor in Arduino IDE (Ctrl+Shift+M)
2. Set baud rate to `115200`
3. You should see connection logs
4. Bring RFID card close to reader
5. Card UID should appear in Serial Monitor

### 4. **Test RFID System**

#### Direct API Test
Visit in your browser:
```
http://localhost/SmartSchoolBus/check.php?uid=4A3B2C1D
```
Expected Response: `GRANTED` or `DENIED`

#### Sample Student RFIDs (for testing):
- **Ahmad bin Ali:** `4A3B2C1D`
- **Nur Aisyah binti Hassan:** `E8F94A23`
- **Wong Li Ming:** `3F8A9B4C`
- **Priya Rajesh:** `7B2D4E6F`
- **Muhammad Amin bin Omar:** `9C3E5F1A`

#### Test Using curl (Command Prompt):
```cmd
curl "http://localhost/SmartSchoolBus/check.php?uid=4A3B2C1D"
```

### 5. **Check Attendance Records**
After scanning, check recorded attendance:
```url
http://localhost/SmartSchoolBus/admin/dashboard.php
```
Login and navigate to **Attendance** or **Reports** to see scanned records.

## 📊 Database Sample Data

### Users
| Username | Role   | Default Password |
|----------|--------|------------------|
| admin    | Admin  | (see setup)      |
| staff1   | Staff  | (see setup)      |
| driver1  | Driver | (see setup)      |

### Buses
| Bus Number | Device ID      |
|-----------|----------------|
| Bus #01   | ESP32_BUS01    |
| Bus #02   | ESP32_BUS02    |
| Bus #03   | ESP32_BUS03    |

### System Tables
- `users` - Login credentials
- `students` - Student records with RFID UIDs
- `buses` - Bus information
- `attendance_records` - RFID scan logs
- `payments` - Payment tracking
- `incidents` - Staff reports
- `notifications` - System alerts

## 🔧 Troubleshooting

### ESP32 Won't Connect to WiFi
- Check WiFi SSID and password in code
- Make sure ESP32 is in range
- Check Serial Monitor for error messages

### RFID Scan Returns "DENIED"
- Verify UID is in the students table
- Check student status is 'active'
- Confirm the UID format matches exactly (case-sensitive)

### Login shows "System temporarily unavailable"
- Check database is running: `xampp/mysql` should be running
- Verify database was imported: `mysql -u root smartschoolbus_db -e "SHOW TABLES"`
- Check logs at `/logs/php_errors.log`

### API Returns "ERROR"
- Check check.php has permission to access database
- Verify database connection parameters in config.php
- Check error logs

## 📝 Next Steps

1. ✅ Database created
2. ✅ API endpoint ready (`check.php`)
3. ⬜ Update ESP32 with your local IP
4. ⬜ Upload ESP32 code
5. ⬜ Test RFID scanning
6. ⬜ Login to dashboard
7. ⬜ View attendance records

## 🎓 System Features

- **Real-time RFID Scanning** - Automatic attendance recording
- **Student Management** - Add/edit students and their RFID cards
- **Bus Tracking** - Assign students to buses
- **Payment System** - Track student payments
- **Reporting** - Attendance and payment reports
- **Multi-role System** - Admin, Staff, Driver roles
- **Incident Tracking** - Report system issues

---

For questions or issues, check logs in `/logs/` directory.
