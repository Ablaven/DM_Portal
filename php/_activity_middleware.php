<?php

declare(strict_types=1);

/**
 * Activity Logging Middleware
 * 
 * Include this file at the top of pages/endpoints to automatically log page views
 */

require_once __DIR__ . '/_activity_log_helpers.php';

/**
 * Log page view automatically
 * Call this after auth in page files
 */
function dmportal_log_page_view(string $pageName): void
{
    try {
        $pdo = get_pdo();
        
        $user = auth_current_user();
        
        // Determine category based on page
        $category = 'view';
        if (strpos($pageName, 'admin_') === 0) {
            $category = 'admin_view';
        } elseif (strpos($pageName, 'php/') === 0) {
            $category = 'api';
        }
        
        dmportal_log_activity($pdo, 'page_view', $category, [
            'targetType' => 'page',
            'targetName' => $pageName,
            'details' => [
                'page' => $pageName,
                'user_role' => $user['role'] ?? 'guest',
                'user_id' => $user['user_id'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        // Don't break the page if logging fails
        error_log('Page view logging error: ' . $e->getMessage());
    }
}

/**
 * Wrapper for authentication + auto-logging
 */
function dmportal_auth_and_log(string $page, array $requiredRoles = null): void
{
    // Do authentication
    auth_require_page_access($page);
    
    if ($requiredRoles !== null) {
        auth_require_roles($requiredRoles);
    }
    
    // Log page view
    dmportal_log_page_view($page);
}
