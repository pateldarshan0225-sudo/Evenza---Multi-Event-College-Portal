<?php
/**
 * Evenza - Production-Ready Tab-Isolated Authentication Core
 * Central authority for managing $_SESSION['authenticated_tabs'][$tab_id]
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['authenticated_tabs']) || !is_array($_SESSION['authenticated_tabs'])) {
    $_SESSION['authenticated_tabs'] = [];
}

/**
 * Validates whether a given tab_id string is a valid format (UUID-v4 format or safe random token)
 */
function is_valid_tab_id(?string $tab_id): bool {
    if (!$tab_id || !is_string($tab_id)) {
        return false;
    }
    // Reject illegal or predictable tab IDs
    $illegal = ['admin', 'student', 'organizer', '123', 'test', 'null', 'undefined', 'true', 'false'];
    if (in_array(strtolower(trim($tab_id)), $illegal, true)) {
        return false;
    }
    // Check length (min 16 chars, max 64) and allowed characters (alphanumeric, hyphens, underscores)
    return preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', trim($tab_id)) === 1;
}

/**
 * Extracts current tab_id from request headers, POST body, or Cookie
 */
function get_current_tab_id(): ?string {
    $tab_id = null;
    
    // 1. Check HTTP header X-Tab-ID (from fetch / AJAX)
    if (!empty($_SERVER['HTTP_X_TAB_ID'])) {
        $tab_id = trim($_SERVER['HTTP_X_TAB_ID']);
    }
    // 2. Check POST field
    elseif (!empty($_POST['evenza_tab_id'])) {
        $tab_id = trim($_POST['evenza_tab_id']);
    }
    // 3. Check Cookie
    elseif (!empty($_COOKIE['evenza_tab_id'])) {
        $tab_id = trim($_COOKIE['evenza_tab_id']);
    }

    if ($tab_id && is_valid_tab_id($tab_id)) {
        return $tab_id;
    }
    return null;
}

/**
 * Retrieves authentication data for a given tab_id
 */
function get_tab_auth(?string $tab_id = null): ?array {
    cleanup_stale_tab_sessions();

    if ($tab_id === null) {
        $tab_id = get_current_tab_id();
    }

    if (!$tab_id || !is_valid_tab_id($tab_id)) {
        return null;
    }

    if (isset($_SESSION['authenticated_tabs'][$tab_id]) && is_array($_SESSION['authenticated_tabs'][$tab_id])) {
        // Update last activity timestamp
        $_SESSION['authenticated_tabs'][$tab_id]['last_activity'] = time();
        return $_SESSION['authenticated_tabs'][$tab_id];
    }

    return null;
}

/**
 * Stores authentication data under $_SESSION['authenticated_tabs'][$tab_id]
 */
function set_tab_auth(string $tab_id, array $data): bool {
    if (!is_valid_tab_id($tab_id)) {
        return false;
    }

    $data['login_time'] = time();
    $data['last_activity'] = time();

    // Store in session map
    $_SESSION['authenticated_tabs'][$tab_id] = $data;

    // Securely regenerate session ID if appropriate, keeping authenticated_tabs intact
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }

    return true;
}

/**
 * Removes authentication data for a specific tab_id using unset()
 */
function unset_tab_auth(?string $tab_id = null): void {
    if ($tab_id === null) {
        $tab_id = get_current_tab_id();
    }

    if ($tab_id && isset($_SESSION['authenticated_tabs'][$tab_id])) {
        unset($_SESSION['authenticated_tabs'][$tab_id]);
    }
}

/**
 * Checks if a tab is authenticated and optionally matches a specific role
 */
function is_tab_authenticated(?string $tab_id = null, ?string $required_role = null): bool {
    $auth = get_tab_auth($tab_id);
    if (!$auth) {
        return false;
    }
    if ($required_role !== null && strtolower((string)($auth['role'] ?? '')) !== strtolower($required_role)) {
        return false;
    }
    return true;
}

/**
 * Enforces role authentication for protected pages
 */
function require_tab_role(string $required_role): array {
    $tab_id = get_current_tab_id();
    $auth = get_tab_auth($tab_id);

    if (!$auth || strtolower((string)($auth['role'] ?? '')) !== strtolower($required_role)) {
        header("Location: ../Frontend/login.php");
        exit();
    }

    return $auth;
}

/**
 * Automatically cleans up stale tab authentication records older than 2 hours (7200s)
 */
function cleanup_stale_tab_sessions(int $timeout_seconds = 7200): void {
    if (!empty($_SESSION['authenticated_tabs']) && is_array($_SESSION['authenticated_tabs'])) {
        $now = time();
        foreach ($_SESSION['authenticated_tabs'] as $tId => $data) {
            $lastAct = $data['last_activity'] ?? $data['login_time'] ?? 0;
            if (($now - $lastAct) > $timeout_seconds) {
                unset($_SESSION['authenticated_tabs'][$tId]);
            }
        }
    }
}
