<?php

declare(strict_types=1);

/**
 * Activity Logging System
 * 
 * Logs all user actions: logins, page views, CRUD operations, file operations, etc.
 * Provides helper functions for logging and querying activity.
 */

/**
 * Ensure activity_logs table exists
 */
function dmportal_ensure_activity_logs_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS activity_logs ('
        . '  log_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,'
        . '  user_id BIGINT(20) UNSIGNED DEFAULT NULL,'
        . '  username VARCHAR(100) DEFAULT NULL,'
        . '  role ENUM(\'admin\',\'management\',\'teacher\',\'student\') DEFAULT NULL,'
        . '  action_type VARCHAR(50) NOT NULL,'
        . '  action_category VARCHAR(50) NOT NULL,'
        . '  target_type VARCHAR(50) DEFAULT NULL,'
        . '  target_id VARCHAR(100) DEFAULT NULL,'
        . '  target_name VARCHAR(255) DEFAULT NULL,'
        . '  details TEXT DEFAULT NULL,'
        . '  old_value TEXT DEFAULT NULL,'
        . '  new_value TEXT DEFAULT NULL,'
        . '  ip_address VARCHAR(45) DEFAULT NULL,'
        . '  user_agent TEXT DEFAULT NULL,'
        . '  page_url VARCHAR(500) DEFAULT NULL,'
        . '  result ENUM(\'success\',\'failure\',\'error\') NOT NULL DEFAULT \'success\','
        . '  error_message TEXT DEFAULT NULL,'
        . '  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
        . '  PRIMARY KEY (log_id),'
        . '  KEY idx_user_id (user_id),'
        . '  KEY idx_username (username),'
        . '  KEY idx_action_type (action_type),'
        . '  KEY idx_action_category (action_category),'
        . '  KEY idx_created_at (created_at),'
        . '  KEY idx_result (result)'
        . ') ENGINE=InnoDB'
    );
}

/**
 * Get client IP address (handles proxies and load balancers)
 */
function dmportal_get_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',   // Standard proxy header
        'HTTP_X_REAL_IP',         // Nginx proxy
        'REMOTE_ADDR'             // Direct connection
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (X-Forwarded-For can contain multiple)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return 'unknown';
}

/**
 * Log an activity
 * 
 * @param PDO $pdo Database connection
 * @param string $actionType Specific action (e.g., 'login', 'create_student', 'update_grade')
 * @param string $actionCategory Category (e.g., 'auth', 'crud', 'file', 'view', 'system')
 * @param array $options Additional data:
 *   - targetType: Type of target (e.g., 'student', 'course', 'assignment')
 *   - targetId: ID of target
 *   - targetName: Display name of target
 *   - details: Additional details (string or will be JSON encoded)
 *   - oldValue: Previous value (for updates)
 *   - newValue: New value (for updates)
 *   - result: 'success', 'failure', 'error'
 *   - errorMessage: Error details if result is failure/error
 *   - userId: Override user ID (if not using session)
 *   - username: Override username
 *   - role: Override role
 */
function dmportal_log_activity(PDO $pdo, string $actionType, string $actionCategory, array $options = []): void
{
    try {
        // Ensure table exists
        dmportal_ensure_activity_logs_table($pdo);
        
        // Get current user info from session
        $userId = null;
        $username = null;
        $role = null;
        
        // Allow overrides FIRST (before checking session)
        if (isset($options['userId'])) {
            $userId = (int)$options['userId'];
        } elseif (isset($_SESSION['portal_user']['user_id'])) {
            $userId = (int)$_SESSION['portal_user']['user_id'];
        }
        
        if (isset($options['username'])) {
            $username = (string)$options['username'];
        } elseif (isset($_SESSION['portal_user']['username'])) {
            $username = (string)$_SESSION['portal_user']['username'];
        }
        
        if (isset($options['role'])) {
            $role = (string)$options['role'];
        } elseif (isset($_SESSION['portal_user']['role'])) {
            $role = (string)$_SESSION['portal_user']['role'];
        }
        
        // Get request details
        $ipAddress = dmportal_get_client_ip();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
        
        // Process details - make it MORE detailed
        $details = $options['details'] ?? [];
        
        // Add automatic context if not provided
        if (!isset($details['request_method'])) {
            $details['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        }
        
        if (!isset($details['request_time'])) {
            $details['request_time'] = date('Y-m-d H:i:s');
        }
        
        if (!isset($details['session_id'])) {
            $details['session_id'] = session_id() ?: 'no_session';
        }
        
        if (!isset($details['referer']) && !empty($_SERVER['HTTP_REFERER'])) {
            $details['referer'] = $_SERVER['HTTP_REFERER'];
        }
        
        // Add query parameters for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
            $sanitizedGet = $_GET;
            // Remove sensitive data
            unset($sanitizedGet['password'], $sanitizedGet['token'], $sanitizedGet['key']);
            if (!empty($sanitizedGet)) {
                $details['query_params'] = $sanitizedGet;
            }
        }
        
        // Add POST data for POST requests (sanitized)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
            $sanitizedPost = $_POST;
            // Remove sensitive data
            unset($sanitizedPost['password'], $sanitizedPost['password_hash'], $sanitizedPost['token'], $sanitizedPost['key']);
            if (!empty($sanitizedPost)) {
                $details['post_data'] = $sanitizedPost;
            }
        }
        
        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        }
        
        $oldValue = $options['oldValue'] ?? null;
        if (is_array($oldValue)) {
            $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
        }
        
        $newValue = $options['newValue'] ?? null;
        if (is_array($newValue)) {
            $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);
        }
        
        $result = $options['result'] ?? 'success';
        $errorMessage = $options['errorMessage'] ?? null;
        
        $targetType = $options['targetType'] ?? null;
        $targetId = $options['targetId'] ?? null;
        $targetName = $options['targetName'] ?? null;
        
        // Insert log
        $sql = 'INSERT INTO activity_logs (
            user_id, username, role,
            action_type, action_category,
            target_type, target_id, target_name,
            details, old_value, new_value,
            ip_address, user_agent, page_url,
            result, error_message
        ) VALUES (
            :user_id, :username, :role,
            :action_type, :action_category,
            :target_type, :target_id, :target_name,
            :details, :old_value, :new_value,
            :ip_address, :user_agent, :page_url,
            :result, :error_message
        )';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':role' => $role,
            ':action_type' => $actionType,
            ':action_category' => $actionCategory,
            ':target_type' => $targetType,
            ':target_id' => $targetId ? (string)$targetId : null,
            ':target_name' => $targetName,
            ':details' => $details,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':page_url' => $pageUrl,
            ':result' => $result,
            ':error_message' => $errorMessage,
        ]);
        
    } catch (Throwable $e) {
        // Don't let logging failures break the application
        error_log('Activity log error: ' . $e->getMessage());
    }
}

/**
 * Get activity logs with filters
 * 
 * @param PDO $pdo
 * @param array $filters:
 *   - dateFrom: Start date (Y-m-d)
 *   - dateTo: End date (Y-m-d)
 *   - userId: Filter by user ID
 *   - username: Filter by username (partial match)
 *   - role: Filter by role
 *   - actionType: Filter by action type
 *   - actionCategory: Filter by category
 *   - result: Filter by result
 *   - search: Free text search in details/target_name
 *   - limit: Limit results (default 1000)
 *   - offset: Offset for pagination
 * @return array
 */
function dmportal_get_activity_logs(PDO $pdo, array $filters = []): array
{
    dmportal_ensure_activity_logs_table($pdo);
    
    $where = [];
    $params = [];
    
    // Exclude admin role logs (only show management, teacher, student)
    $where[] = "(role != 'admin' OR role IS NULL)";
    
    if (!empty($filters['dateFrom'])) {
        $where[] = 'created_at >= :date_from';
        $params[':date_from'] = $filters['dateFrom'] . ' 00:00:00';
    }
    
    if (!empty($filters['dateTo'])) {
        $where[] = 'created_at <= :date_to';
        $params[':date_to'] = $filters['dateTo'] . ' 23:59:59';
    }
    
    if (!empty($filters['userId'])) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = (int)$filters['userId'];
    }
    
    if (!empty($filters['username'])) {
        $where[] = 'username LIKE :username';
        $params[':username'] = '%' . $filters['username'] . '%';
    }
    
    if (!empty($filters['role'])) {
        $where[] = 'role = :role';
        $params[':role'] = $filters['role'];
    }
    
    if (!empty($filters['actionType'])) {
        $where[] = 'action_type = :action_type';
        $params[':action_type'] = $filters['actionType'];
    }
    
    if (!empty($filters['actionCategory'])) {
        $where[] = 'action_category = :action_category';
        $params[':action_category'] = $filters['actionCategory'];
    }
    
    if (!empty($filters['result'])) {
        $where[] = 'result = :result';
        $params[':result'] = $filters['result'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = '(target_name LIKE :search OR details LIKE :search OR action_type LIKE :search)';
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 1000;
    $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
    
    $sql = "SELECT * FROM activity_logs $whereClause ORDER BY created_at DESC, log_id DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Get count of activity logs with filters
 */
function dmportal_get_activity_logs_count(PDO $pdo, array $filters = []): int
{
    dmportal_ensure_activity_logs_table($pdo);
    
    $where = [];
    $params = [];
    
    // Exclude admin role logs (only show management, teacher, student)
    $where[] = "(role != 'admin' OR role IS NULL)";
    
    if (!empty($filters['dateFrom'])) {
        $where[] = 'created_at >= :date_from';
        $params[':date_from'] = $filters['dateFrom'] . ' 00:00:00';
    }
    
    if (!empty($filters['dateTo'])) {
        $where[] = 'created_at <= :date_to';
        $params[':date_to'] = $filters['dateTo'] . ' 23:59:59';
    }
    
    if (!empty($filters['userId'])) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = (int)$filters['userId'];
    }
    
    if (!empty($filters['username'])) {
        $where[] = 'username LIKE :username';
        $params[':username'] = '%' . $filters['username'] . '%';
    }
    
    if (!empty($filters['role'])) {
        $where[] = 'role = :role';
        $params[':role'] = $filters['role'];
    }
    
    if (!empty($filters['actionType'])) {
        $where[] = 'action_type = :action_type';
        $params[':action_type'] = $filters['actionType'];
    }
    
    if (!empty($filters['actionCategory'])) {
        $where[] = 'action_category = :action_category';
        $params[':action_category'] = $filters['actionCategory'];
    }
    
    if (!empty($filters['result'])) {
        $where[] = 'result = :result';
        $params[':result'] = $filters['result'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = '(target_name LIKE :search OR details LIKE :search OR action_type LIKE :search)';
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT COUNT(*) FROM activity_logs $whereClause";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return (int)$stmt->fetchColumn();
}

/**
 * Delete activity logs older than N days
 * 
 * @param PDO $pdo
 * @param int $days Number of days to keep
 * @return int Number of deleted rows
 */
function dmportal_delete_old_activity_logs(PDO $pdo, int $days = 3): int
{
    dmportal_ensure_activity_logs_table($pdo);
    
    $sql = 'DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':days' => $days]);
    
    return $stmt->rowCount();
}

/**
 * Get unique action types from logs (for filter dropdown)
 */
function dmportal_get_activity_action_types(PDO $pdo): array
{
    dmportal_ensure_activity_logs_table($pdo);
    
    $sql = 'SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type';
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get unique action categories from logs (for filter dropdown)
 */
function dmportal_get_activity_action_categories(PDO $pdo): array
{
    dmportal_ensure_activity_logs_table($pdo);
    
    $sql = 'SELECT DISTINCT action_category FROM activity_logs ORDER BY action_category';
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


/**
 * Quick helper to log CRUD operations (create, update, delete)
 * Use this in API endpoints for easy one-line logging
 * 
 * @param PDO $pdo
 * @param string $operation 'create', 'update', or 'delete'
 * @param string $entityType 'student', 'course', 'assignment', etc.
 * @param mixed $entityId ID of the entity
 * @param string $entityName Display name
 * @param array $extraDetails Additional details to log
 */
function dmportal_log_crud(PDO $pdo, string $operation, string $entityType, $entityId, string $entityName, array $extraDetails = []): void
{
    $actionType = $operation . '_' . $entityType;
    
    dmportal_log_activity($pdo, $actionType, 'crud', [
        'targetType' => $entityType,
        'targetId' => (string)$entityId,
        'targetName' => $entityName,
        'details' => $extraDetails,
        'result' => 'success',
    ]);
}

/**
 * Quick helper to log file operations (upload, download, delete)
 * 
 * @param PDO $pdo
 * @param string $operation 'upload', 'download', or 'delete'
 * @param string $fileType 'lecture', 'assignment', etc.
 * @param string $filename Name of the file
 * @param array $extraDetails Additional details
 */
function dmportal_log_file(PDO $pdo, string $operation, string $fileType, string $filename, array $extraDetails = []): void
{
    $actionType = $operation . '_' . $fileType;
    
    dmportal_log_activity($pdo, $actionType, 'file', [
        'targetType' => $fileType,
        'targetName' => $filename,
        'details' => $extraDetails,
        'result' => 'success',
    ]);
}
