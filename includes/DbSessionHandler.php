<?php
/**
 * Database Session Handler for Multi-Concurrent User Sessions
 * Allows multiple users to be logged in simultaneously (one per role)
 * 
 * @author SelamatRide Development Team
 * @version 2.0
 */

if (!defined('APP_STARTED')) {
    die('Direct access not permitted');
}

class DbSessionHandler {
    private $pdo;
    private $session_lifetime = 3600; // 1 hour
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new session for a user
     * 
     * @param int $user_id
     * @param string $role_name
     * @param array $session_data
     * @param string $ip_address
     * @param string $user_agent
     * @return string session_id
     */
    public function createSession($user_id, $role_name, $session_data = [], $ip_address = '', $user_agent = '') {
        try {
            $session_id = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + $this->session_lifetime);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions 
                (session_id, user_id, role_name, session_data, ip_address, user_agent, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $session_id,
                $user_id,
                $role_name,
                json_encode($session_data),
                $ip_address,
                $user_agent,
                $expires_at
            ]);
            
            return $session_id;
        } catch (PDOException $e) {
            error_log("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get session data by session ID
     * 
     * @param string $session_id
     * @return array|false
     */
    public function getSession($session_id) {
        try {
            // First, clean up expired sessions
            $this->cleanupExpiredSessions();
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_sessions 
                WHERE session_id = ? 
                AND expires_at > NOW()
            ");
            
            $stmt->execute([$session_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Session retrieval error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update session last activity
     * 
     * @param string $session_id
     * @return bool
     */
    public function updateActivity($session_id) {
        try {
            $new_expires = date('Y-m-d H:i:s', time() + $this->session_lifetime);
            
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW(), expires_at = ?
                WHERE session_id = ?
            ");
            
            return $stmt->execute([$new_expires, $session_id]);
        } catch (PDOException $e) {
            error_log("Session update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destroy specific session
     * 
     * @param string $session_id
     * @return bool
     */
    public function destroySession($session_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            return $stmt->execute([$session_id]);
        } catch (PDOException $e) {
            error_log("Session deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active sessions for a user
     * 
     * @param int $user_id
     * @return array
     */
    public function getUserSessions($user_id) {
        try {
            $this->cleanupExpiredSessions();
            
            $stmt = $this->pdo->prepare("
                SELECT session_id, user_id, role_name, ip_address, created_at, last_activity 
                FROM user_sessions 
                WHERE user_id = ?
                AND expires_at > NOW()
                ORDER BY created_at DESC
            ");
            
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("User sessions retrieval error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if User is alreadylogged in with this role
     * 
     * @param int $user_id
     * @param string $role_name
     * @return array|false
     */
    public function getActiveSessionByRole($user_id, $role_name) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_sessions 
                WHERE user_id = ? 
                AND role_name = ?
                AND expires_at > NOW()
                LIMIT 1
            ");
            
            $stmt->execute([$user_id, $role_name]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Role session check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired sessions
     * 
     * @return int number of deleted sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update session data
     * 
     * @param string $session_id
     * @param array $data
     * @return bool
     */
    public function updateSessionData($session_id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET session_data = ?, last_activity = NOW()
                WHERE session_id = ?
            ");
            
            return $stmt->execute([json_encode($data), $session_id]);
        } catch (PDOException $e) {
            error_log("Session data update error: " . $e->getMessage());
            return false;
        }
    }
}
?>
