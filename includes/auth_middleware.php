<?php
/**
 * SelamatRide SmartSchoolBus - Authentication Middleware
 * Production-Grade Security Layer
 * 
 * This file provides:
 * - Session security
 * - Role-based access control
 * - CSRF protection
 * - Activity logging
 * 
 * @author SelamatRide Development Team
 * @version 2.0
 */

// Prevent direct access
if (!defined('APP_STARTED')) {
    die('Direct access not permitted');
}

class AuthMiddleware {
    private $pdo;
    private $session_timeout = 3600; // 1 hour in seconds
    private $multi_session_available = null;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeSession();
    }
    
    /**
     * Initialize secure session
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
            
            session_start();
            
            // Session hijacking prevention
            if (!isset($_SESSION['initialized'])) {
                session_regenerate_id(true);
                $_SESSION['initialized'] = true;
                $_SESSION['created_at'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            }
            
            // Validate session integrity
            if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
                $this->destroySession();
                $this->redirectToLogin('Session security violation detected');
            }
            
            // Check session timeout
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->session_timeout)) {
                $this->destroySession();
                $this->redirectToLogin('Session expired due to inactivity');
            }
            
            $_SESSION['last_activity'] = time();
        }
    }
    
    /**
     * Detect current section from URL to determine which role session to check
     * Returns 'admin', 'staff', 'driver', or null
     */
    private function getCurrentSection() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/admin/') !== false) return 'admin';
        if (strpos($uri, '/staff/') !== false) return 'staff';
        if (strpos($uri, '/driver/') !== false) return 'driver';
        return null;
    }
    
    /**
     * Get current role session from cookies
     */
    private function getCurrentRoleSession() {
        $section = $this->getCurrentSection();
        if (!$section) return null;

        if (!$this->isMultiSessionAvailable()) {
            return null;
        }
        
        $cookie_name = 'ROLE_SESSION_' . strtoupper($section);
        if (!isset($_COOKIE[$cookie_name])) {
            return null;
        }
        
        try {
            require_once __DIR__ . '/DbSessionHandler.php';
            $handler = new DbSessionHandler($this->pdo);
            $session = $handler->getSession($_COOKIE[$cookie_name]);
            
            if ($session && $session['expires_at'] > date('Y-m-d H:i:s')) {
                // Update activity
                $handler->updateActivity($_COOKIE[$cookie_name]);
                return $session;
            }
            return null;
        } catch (Exception $e) {
            error_log("Role session retrieval failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check whether DB-backed multi-session storage is available.
     */
    private function isMultiSessionAvailable() {
        if ($this->multi_session_available !== null) {
            return $this->multi_session_available;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'user_sessions'");
            $this->multi_session_available = (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            $this->multi_session_available = false;
        }

        return $this->multi_session_available;
    }
    
    /**
     * Check if user is authenticated using role-specific session
     */
    public function isAuthenticated() {
        // First, try to get role-specific session from cookie
        $session = $this->getCurrentRoleSession();
        if ($session) {
            return true;
        }

        // Safe fallback to global SESSION for backward compatibility
        if (isset($_SESSION['user_id']) && isset($_SESSION['role_name'])) {
            $section = $this->getCurrentSection();
            if (!$section || strtolower((string)$_SESSION['role_name']) === $section) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Require authentication - redirect if not logged in
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->logSecurityEvent('unauthorized_access_attempt', [
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $this->redirectToLogin('Please login to continue');
        }
    }
    
    /**
     * CRITICAL SAFETY: Detect session contamination
     * If the global SESSION role doesn't match the actual role-specific session, something is wrong
     */
    private function detectSessionContamination() {
        $section = $this->getCurrentSection();
        $roleSession = $this->getCurrentRoleSession();
        
        // If we have a role-specific session
        if ($roleSession) {
            $sessionRole = $roleSession['role_name'];
            
            // But global SESSION has a different role, we have contamination!
            if (isset($_SESSION['role_name']) && $_SESSION['role_name'] !== $sessionRole) {
                error_log("CRITICAL SECURITY: Session contamination detected!");
                error_log("Role-specific role: $sessionRole, Global SESSION role: {$_SESSION['role_name']}");
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
                error_log("Current section: $section, User ID: $user_id");
                error_log("Stack trace: " . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)));
                
                // Clear the contaminated global session immediately
                $_SESSION = [];
                
                $this->logSecurityEvent('session_contamination_detected', [
                    'section' => $section,
                    'role_from_cookie' => $sessionRole,
                    'role_from_session' => $_SESSION['role_name'] ?? 'unknown',
                    'user_id' => $_SESSION['user_id'] ?? 'unknown',
                    'url' => $_SERVER['REQUEST_URI'] ?? ''
                ]);
                
                // Force redirect to login
                $this->redirectToLogin('Session security issue detected. Please login again.');
            }
        }
    }
    
    /**
     * Require specific role(s)
     * 
     * @param array|string $allowed_roles
     */
    public function requireRole($allowed_roles) {
        $allowed_roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];

        // CRITICAL: Get role-specific session from database using cookie
        $section = $this->getCurrentSection();
        $session = $this->getCurrentRoleSession();

        if ($session) {
            // FIRST: Detect contamination only when role cookie/session is present
            $this->detectSessionContamination();

            $user_role = $session['role_name'];
            $user_id = $session['user_id'];
        
            // CRITICAL SECURITY CHECK: Verify the role matches the section
            if ($section && strtolower($user_role) !== $section) {
                $this->redirectToDashboard($user_role);
            }

            // Check if role is in allowed list
            if (!in_array($user_role, $allowed_roles, true)) {
                $this->redirectToDashboard($user_role);
            }

            // Update global SESSION with validated role data for compatibility
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['role_name'] = $session['role_name'];
            $_SESSION['current_role_session_id'] = $session['session_id'];
            $_SESSION['current_section'] = $section;
            $_SESSION['verified_role_match'] = true;

            $session_data = json_decode($session['session_data'], true);
            if ($session_data) {
                $_SESSION['username'] = $session_data['username'] ?? '';
                $_SESSION['full_name'] = $session_data['full_name'] ?? '';
                $_SESSION['email'] = $session_data['email'] ?? '';
                $_SESSION['login_time'] = $session_data['login_time'] ?? time();
            }
            return;
        }

        // Fallback mode when DB-backed multi-session is unavailable.
        if (isset($_SESSION['user_id'], $_SESSION['role_name'])) {
            $user_role = strtolower((string)$_SESSION['role_name']);
            if ((!$section || $user_role === $section) && in_array($user_role, $allowed_roles, true)) {
                return;
            }

            $this->redirectToDashboard($user_role);
        }

        $this->redirectToLogin('Please login to continue');
    }
    
    /**
     * Get current user information
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        // Try to get user_id from role-specific session first
        $user_id = null;
        $session = $this->getCurrentRoleSession();
        
        if ($session) {
            $user_id = $session['user_id'];
        } else if (isset($_SESSION['user_id'])) {
            // Fallback to global session
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return null;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.role_name, r.permissions 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.user_id = ? AND u.status = 'active'
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Auth error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->logSecurityEvent('csrf_validation_failed', [
                'user_id' => $_SESSION['user_id'] ?? 0,
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            return false;
        }
        return true;
    }
    
    /**
     * Require valid CSRF token for POST requests
     */
    public function requireCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!$this->validateCSRFToken($token)) {
                http_response_code(403);
                die(json_encode(['status' => 'error', 'message' => 'CSRF validation failed']));
            }
        }
    }
    
    /**
     * Log user activity
     */
    public function logActivity($action, $entity_type, $entity_id = null, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $entity_type,
                $entity_id,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("Activity logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $details = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (?, ?, 'security', NULL, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $event_type,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("Security logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Destroy session and logout (only current role)
     */
    public function destroySession() {
        // Get the user ID and role before destroying
        $user_id = null;
        $current_role = null;
        
        $session = $this->getCurrentRoleSession();
        if ($session) {
            $user_id = $session['user_id'];
            $current_role = $session['role_name'];
            $session_id = $session['session_id'];
            
            // Log the activity
            if ($user_id) {
                $this->logActivity('logout', 'user', $user_id);
            }
            
            // Destroy role-specific session in database
            try {
                require_once __DIR__ . '/DbSessionHandler.php';
                $handler = new DbSessionHandler($this->pdo);
                $handler->destroySession($session_id);
            } catch (Exception $e) {
                error_log("Failed to destroy role session: " . $e->getMessage());
            }
            
            // Clear role-specific cookie
            $cookie_name = 'ROLE_SESSION_' . strtoupper($current_role);
            setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
        }
        
        // Clear global SESSION only if it matches the current role
        if (isset($_SESSION['user_id']) && isset($_SESSION['role_name'])) {
            if ($_SESSION['role_name'] === $current_role) {
                $_SESSION = [];
            }
        }
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    /**
     * Redirect to login page
     */
    private function redirectToLogin($message = '') {
        $url = SITE_URL . '/login.php';
        if ($message) {
            $_SESSION['login_message'] = $message;
        }
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * Redirect to appropriate dashboard based on role
     */
    private function redirectToDashboard($role = null) {
        // Use provided role, otherwise get from SESSION
        if (!$role) {
            $role = $_SESSION['role_name'] ?? '';
        }
        
        switch ($role) {
            case 'admin':
                $url = SITE_URL . '/admin/dashboard.php';
                break;
            case 'staff':
                $url = SITE_URL . '/staff/dashboard.php';
                break;
            case 'driver':
                $url = SITE_URL . '/driver/dashboard.php';
                break;
            default:
                $url = SITE_URL . '/login.php';
        }
        
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * Check permission (based on role permissions JSON)
     */
    public function hasPermission($permission_key) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $permissions = json_decode($user['permissions'], true);
        return isset($permissions[$permission_key]) && $permissions[$permission_key] === true;
    }
    
    /**
     * Sanitize input data
     */
    public function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate input data
     */
    public function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule_string) {
            $rules_array = explode('|', $rule_string);
            $value = $data[$field] ?? null;
            
            foreach ($rules_array as $rule) {
                // Required validation
                if ($rule === 'required' && empty($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                    break;
                }
                
                // Email validation
                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Invalid email format';
                    break;
                }
                
                // Numeric validation
                if ($rule === 'numeric' && !empty($value) && !is_numeric($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                    break;
                }
                
                // Min length validation
                if (strpos($rule, 'min:') === 0 && !empty($value)) {
                    $min = (int)substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least $min characters";
                        break;
                    }
                }
                
                // Max length validation
                if (strpos($rule, 'max:') === 0 && !empty($value)) {
                    $max = (int)substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed $max characters";
                        break;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * ============================================
     * MULTI-ROLE CONCURRENT SESSION SUPPORT
     * ============================================
     */
    
    /**
     * Create multi-role session (allows different users per role)
     */
    public function createMultiRoleSession($user_id, $role_name, $session_data = []) {
        try {
            if (!$this->isMultiSessionAvailable()) {
                return false;
            }

            // Include session handler
            require_once __DIR__ . '/DbSessionHandler.php';
            $handler = new DbSessionHandler($this->pdo);
            
            // Create database session (stores separately by role)
            $session_id = $handler->createSession(
                $user_id,
                $role_name,
                $session_data,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            if ($session_id) {
                // Set role-specific cookie for this session
                $cookie_name = 'ROLE_SESSION_' . strtoupper($role_name);
                $cookie_expiry = time() + 3600; // 1 hour
                
                // Set the cookie with explicit parameters for maximum compatibility
                setcookie($cookie_name, $session_id, $cookie_expiry, '/', '', false, true);
                
                // Also send a Set-Cookie header as backup for better reliability
                header('Set-Cookie: ' . $cookie_name . '=' . urlencode($session_id) . '; Path=/; HttpOnly; SameSite=Strict; Max-Age=3600', false);
                
                error_log("Created role-specific cookie: $cookie_name for user_id=$user_id role=$role_name");
                return $session_id;
            }
            return false;
        } catch (Exception $e) {
            error_log("Multi-role session creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active session by role
     */
    public function getActiveSessionByRole($role_name) {
        try {
            $cookie_name = 'ROLE_SESSION_' . strtoupper($role_name);
            if (!isset($_COOKIE[$cookie_name])) {
                return false;
            }
            
            require_once __DIR__ . '/DbSessionHandler.php';
            $handler = new DbSessionHandler($this->pdo);
            $session = $handler->getSession($_COOKIE[$cookie_name]);
            
            if ($session) {
                // Update activity
                $handler->updateActivity($_COOKIE[$cookie_name]);
                return $session;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Role session retrieval failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a specific role is currently logged in (may be different user)
     */
    public function isRoleLoggedIn($role_name) {
        $session = $this->getActiveSessionByRole($role_name);
        return $session !== false;
    }
    
    /**
     * Get logged-in user for a specific role
     */
    public function getUserByRole($role_name) {
        $session = $this->getActiveSessionByRole($role_name);
        if (!$session) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, r.role_name, r.permissions 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.user_id = ? AND u.status = 'active'
            ");
            $stmt->execute([$session['user_id']]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("User retrieval by role failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout a specific role (keep other roles logged in)
     */
    public function logoutRole($role_name) {
        try {
            require_once __DIR__ . '/DbSessionHandler.php';
            $handler = new DbSessionHandler($this->pdo);
            
            $cookie_name = 'ROLE_SESSION_' . strtoupper($role_name);
            if (isset($_COOKIE[$cookie_name])) {
                $handler->destroySession($_COOKIE[$cookie_name]);
                setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Role logout failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active roles for current user
     */
    public function getActiveRoles() {
        $roles = ['admin', 'staff', 'driver'];
        $active_roles = [];
        
        foreach ($roles as $role) {
            if ($this->isRoleLoggedIn($role)) {
                $active_roles[] = $role;
            }
        }
        
        return $active_roles;
    }
}

