# 🚀 SmartSchoolBus RFID System - COMPLETE SETUP

## ✅ What's Been Setup

### 1. **Database Created** ✓
- Database: `smartschoolbus_db`
- Tables: 16 (users, students, buses, attendance_records, payments, etc.)
- Sample data: 5 users, 5 students, 3 buses

### 2. **RFID API Endpoint Created** ✓
- File: `check.php`
- Function: Receives RFID card UID from ESP32
- Returns: `GRANTED` (card registered, attendance recorded) or `DENIED` (card not found)
- Automatically records attendance with action (boarded/dropped_off)

### 3. **ESP32 Code Updated** ✓
- File: `Arduino/ESP32_RFID_Reader.ino`
- Updated to use configurable server URL
- Ready for upload to ESP32

### 4. **Password Reset Tool Created** ✓
- File: `admin_password_reset.php`
- Allows setting up user passwords before first login
- Should be deleted after use

---

## 🎯 QUICK SETUP (5 Steps)

### **Step 1: Set Admin Password** 
1. Visit: `http://localhost/SmartSchoolBus/admin_password_reset.php`
2. Select "admin" user
3. Enter password: `admin123`
4. Click "Update Password"

### **Step 2: Find Your Computer's IP**
```cmd
ipconfig
```
Look for "IPv4 Address" (e.g., `192.168.1.5`, `172.20.10.4`)

### **Step 3: Update ESP32 Code**
Edit `Arduino/ESP32_RFID_Reader.ino` around line 24:
```cpp
String serverURL = "http://YOUR_IP_HERE/SmartSchoolBus/check.php?uid=";
// Example:
String serverURL = "http://192.168.1.5/SmartSchoolBus/check.php?uid=";
```

### **Step 4: Upload to ESP32**
1. Connect ESP32 via USB
2. Open Arduino IDE
3. Open `Arduino/ESP32_RFID_Reader.ino`
4. Upload to ESP32

### **Step 5: Test**
1. Scan RFID card in front of reader
2. Card UID appears in Serial Monitor
3. System returns `GRANTED` or `DENIED`
4. Attendance recorded in database

---

## 🧪 Testing the System

### **Test Without ESP32 (Using Browser)**

Visit these URLs to test:

```
✓ GRANTED (existing student):
http://localhost/SmartSchoolBus/check.php?uid=4A3B2C1D

✗ DENIED (non-existent card):
http://localhost/SmartSchoolBus/check.php?uid=FAKE1234
```

### **Sample Student UIDs (for testing)**
| Student Name | RFID UID |
|---|---|
| Ahmad bin Ali | 4A3B2C1D |
| Nur Aisyah | E8F94A23 |
| Wong Li Ming | 3F8A9B4C |
| Priya Rajesh | 7B2D4E6F |
| Muhammad Amin | 9C3E5F1A |

### **Check Recorded Attendance**
1. Login: `http://localhost/SmartSchoolBus/login.php`
2. Username: `admin`
3. Password: `admin123` (or what you set)
4. Go to Dashboard → Attendance or Reports

---

## 📋 System Workflow

```
┌─────────────────┐
│   RFID Reader   │ (ESP32 with MFRC522)
│   Scans Card    │
└────────┬────────┘
         │
         │ Sends UID via HTTP
         ↓
┌─────────────────┐
│   check.php     │ 
│ (API Endpoint)  │
└────────┬────────┘
         │
         ├─→ Query database for student
         ├─→ Check if UID exists
         ├─→ Check if student is active
         │
         ├─→ FOUND: Record attendance + Return "GRANTED"
         │
         └─→ NOT FOUND: Return "DENIED"
         
         ↓
┌─────────────────┐
│   LCD Display   │ Shows result
│   Buzzer Beeps  │ Green = OK, Red = Error
└─────────────────┘
         ↓
┌─────────────────┐
│  attendance_    │ Stored in database
│  records table  │
└─────────────────┘
```

---

## 🔐 Login Credentials

### **After Password Reset:**
- **Username:** admin
- **Password:** admin123 (or what you set)
- **Role:** Admin (full access)

### **Other Users Available:**
- **staff1** / **staff123** (Staff role)
- **driver1** / **driver123** (Driver role)

---

## 📁 Key Files

| File | Purpose |
|------|---------|
| `check.php` | RFID API endpoint |
| `login.php` | Login page |
| `config.php` | Database configuration |
| `admin_password_reset.php` | Password setup tool |
| `Arduino/ESP32_RFID_Reader.ino` | ESP32 firmware |
| `database.sql` | Database schema (imported) |

---

## ⚠️ Important Notes

1. **Delete Password Reset Tool After Use**
   - Delete `admin_password_reset.php` once passwords are set
   - This prevents unauthorized access

2. **Update ESP32 IP Address**
   - Must match your computer's local IP
   - Not 127.0.0.1 or localhost (ESP32 can't access localhost)
   - Use actual local network IP (192.168.x.x or 172.x.x.x)

3. **WiFi Network**
   - ESP32 and computer must be on same WiFi network
   - Update WiFi SSID and password in ESP32 code if needed

4. **Database Backup**
   - All attendance data stored in `attendance_records` table
   - Use MySQL backup tools to create backups

---

## 🛠️ Troubleshooting

### **"System temporarily unavailable" on Login**
- ✅ Fixed by importing database.sql

### **ESP32 Won't Connect to Server**
- Check IP address is correct
- Verify both devices on same network
- Check Serial Monitor for error details

### **RFID Card Returns "DENIED"**
- Verify card UID exists in students table
- Check student status is "active"
- UID must match exactly (case-sensitive)

### **Can't Access check.php**
- Ensure XAMPP is running (Apache + MySQL)
- Test: `http://localhost/SmartSchoolBus/login.php`
- If login page works, check.php should work too

---

## 📊 Database Structure

```
USERS
├─ admin (Admin role)
├─ staff1, staff2 (Staff role)
└─ driver1, driver2 (Driver role)

STUDENTS
├─ student_id
├─ student_name
├─ rfid_uid (linked to RFID cards)
├─ bus_id
├─ payment_status
└─ status (active/inactive)

BUSES
├─ bus_id
├─ bus_number
├─ assigned_driver_id
├─ device_id (ESP32 identifier)
└─ status

ATTENDANCE_RECORDS
├─ record_id
├─ student_id
├─ action (boarded/dropped_off)
├─ timestamp
├─ device_id (which ESP32 recorded it)
└─ verification_status
```

---

## 🎓 Next Steps

- [ ] Set admin password using `admin_password_reset.php`
- [ ] Find your local IP address
- [ ] Update ESP32 code with IP
- [ ] Upload ESP32 firmware
- [ ] Test RFID scan
- [ ] Verify attendance in dashboard
- [ ] Delete `admin_password_reset.php`

---

**System is now ready for production testing!** 🎉

For documentation, see: `SETUP_GUIDE.md`
