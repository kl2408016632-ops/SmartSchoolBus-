# Multi-Concurrent User Sessions Setup

## What's Changed

Your SmartSchoolBus system now supports **multiple concurrent user sessions**. This allows you to:
- Login as **Admin** in one browser tab
- Login as **Staff** in another tab
- Login as **Driver** in a third tab
- All simultaneously in the **same browser**

## Setup Instructions

### Step 1: Create the Sessions Table

Run the SQL command to create the `user_sessions` table:

```bash
cd C:\xampp\mysql\bin
mysql -u root smartschoolbus_db < "C:\xampp\htdocs\SmartSchoolBus\database.sql"
```

Or, manually run this SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    session_data LONGTEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_role (user_id, role_name),
    INDEX idx_expires (expires_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;
```

### Step 2: Verify New Files Are in Place

Check that these files exist:
- `includes/DbSessionHandler.php` ✓ (NEW)
- `includes/auth_middleware.php` ✓ (UPDATED)
- `login.php` ✓ (UPDATED)

## How It Works

### System Files Modified:

1. **database.sql** 
   - Added `user_sessions` table to store multi-concurrent session data

2. **includes/DbSessionHandler.php** (NEW)
   - Handles database-backed session storage
   - Allows multiple sessions per user (one per role)
   - Manages session lifecycle (create, update, destroy)

3. **includes/auth_middleware.php** (UPDATED)
   - Added multi-role session methods:
     - `createMultiRoleSession()` - Create role-specific session
     - `isRoleLoggedIn()` - Check if specific role is logged in
     - `getActiveSessionByRole()` - Get session for a role
     - `getUserByRole()` - Get user logged in for a role
     - `logoutRole()` - Logout a specific role
     - `getActiveRoles()` - Get all logged-in roles

4. **login.php** (UPDATED)
   - Uses new multi-role session system
   - Creates role-specific sessions instead of global sessions
   - Maintains backward compatibility with traditional sessions

## Testing Multi-Concurrent Login

### Test Case 1: Same Browser, Different Tabs
1. Login as **admin** → Tab 1
2. Open new tab, go to login.php
3. Login as **staff** → Tab 2
4. Open new tab, go to login.php
5. Login as **driver** → Tab 3
   
✓ All three should work simultaneously!

### Test Case 2: View Active Sessions

Add this debug code to see active sessions (temporary):

```php
<?php
require_once 'config.php';
require_once 'includes/DbSessionHandler.php';

$handler = new DbSessionHandler($pdo);
$sessions = $handler->cleanupExpiredSessions();
$records = $pdo->query("SELECT * FROM user_sessions WHERE expires_at > NOW()")->fetchAll();

echo "<pre>";
echo "Active Sessions:\n";
foreach($records as $session) {
    echo "User: {$session['user_id']}, Role: {$session['role_name']}, Created: {$session['created_at']}\n";
}
echo "</pre>";
?>
```

## Technical Details

### Session Storage:

**Before:**
- All sessions stored in `$_SESSION` (PHP file-based)
- Only one PHPSESSID cookie allowed
- Logging in with different role would destroy previous session

**Now:**
- Sessions stored in `user_sessions` database table
- Each role has separate cookie: `ROLE_SESSION_ADMIN`, `ROLE_SESSION_STAFF`, `ROLE_SESSION_DRIVER`
- Multiple sessions can coexist independently
- Sessions auto-expire after 1 hour of inactivity

### Cookies Created:

After login, you'll see these cookies in your browser:
- `PHPSESSID` - Traditional session (backward compatibility)
- `ROLE_SESSION_ADMIN` - Admin role session
- `ROLE_SESSION_STAFF` - Staff role session  
- `ROLE_SESSION_DRIVER` - Driver role session

### Session Lifecycle:

1. **Create**: Login creates role-specific session in database
2. **Maintain**: Each page load updates `last_activity` timestamp
3. **Timeout**: Sessions expire after 3600 seconds (1 hour) of inactivity
4. **Cleanup**: Expired sessions automatically cleaned from database
5. **Destroy**: Logout deletes the role-specific session

## Troubleshooting

### Issue: Sessions not persisting across pages

**Solution:** Ensure new `user_sessions` table was created in database

```bash
mysql -u root smartschoolbus_db -e "SHOW TABLES LIKE 'user_sessions';"
```

### Issue: Getting database errors

**Solution:** Check that `DbSessionHandler.php` exists and is in the correct location:
```
c:\xampp\htdocs\SmartSchoolBus\includes\DbSessionHandler.php
```

### Issue: Rolelogout not working

**Solution:** Make sure `auth_middleware.php` is updated with the new methods

## Rollback (if needed)

To revert to single-session mode:

1. Delete `includes/DbSessionHandler.php`
2. Restore `includes/auth_middleware.php` from backup
3. Restore `login.php` from backup

## Technical Support

If multi-session feature causes issues, you can:

1. **Temporarily disable**: Comment out the `createMultiRoleSession()` call in `login.php`
2. **Clear all sessions**: 
   ```sql
   DELETE FROM user_sessions;
   ```

3. **View all active sessions**:
   ```sql
   SELECT * FROM user_sessions WHERE expires_at > NOW();
   ```

## Performance Impact

- **Minimal**: Uses indexed database queries
- **Indexes**: `user_sessions` table has indexes on: `user_id`, `role_name`, `expires_at`, `last_activity`
- **Cleanup**: Expired sessions cleaned automatically when new session is created

## Security Features

✓ Role-based session isolation (different users per role)
✓ Session timeout (1 hour inactivity)
✓ IP address & User-Agent validation
✓ HttpOnly cookies (prevents JavaScript access)
✓ SameSite=Strict (prevents CSRF)
✓ Automatic expired session cleanup

---

**Enjoy testing multiple concurrent users in the same browser!** 🎉
