<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('ensure_csrf_token')) {
    function ensure_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        $token = ensure_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token(?string $token): bool
    {
        $session_token = (string)($_SESSION['csrf_token'] ?? '');
        if ($session_token === '' || $token === null) {
            return false;
        }

        return hash_equals($session_token, $token);
    }
}

if (!function_exists('auth_rate_limit_ip')) {
    function auth_rate_limit_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            return 'unknown';
        }

        return $ip;
    }
}

if (!function_exists('ensure_auth_rate_limits_table')) {
    function ensure_auth_rate_limits_table(mysqli $conn): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS auth_rate_limits (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(64) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_attempt DATETIME NOT NULL,
                blocked_until DATETIME DEFAULT NULL,
                UNIQUE KEY uq_ip_action (ip_address, action),
                INDEX idx_blocked_until (blocked_until),
                INDEX idx_last_attempt (last_attempt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        return (bool)mysqli_query($conn, $sql);
    }
}

if (!function_exists('auth_rate_limit_message')) {
    function auth_rate_limit_message(int $seconds): string
    {
        $minutes = (int)ceil($seconds / 60);
        if ($minutes <= 1) {
            return 'Too many attempts. Try again in 1 minute.';
        }

        return 'Too many attempts. Try again in ' . $minutes . ' minutes.';
    }
}

if (!function_exists('auth_rate_limit_check')) {
    function auth_rate_limit_check(mysqli $conn, string $action, int $limit = 5, int $window_seconds = 900, int $block_seconds = 900): string
    {
        if (!ensure_auth_rate_limits_table($conn)) {
            return '';
        }

        $ip = auth_rate_limit_ip();
        $stmt = mysqli_prepare(
            $conn,
            "SELECT attempts, last_attempt, blocked_until
             FROM auth_rate_limits
             WHERE ip_address = ? AND action = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return '';
        }

        mysqli_stmt_bind_param($stmt, 'ss', $ip, $action);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return '';
        }

        $now = time();
        $blocked_until = !empty($row['blocked_until']) ? strtotime((string)$row['blocked_until']) : false;
        if ($blocked_until !== false && $blocked_until > $now) {
            return auth_rate_limit_message($blocked_until - $now);
        }

        $last_attempt = strtotime((string)$row['last_attempt']);
        if ($last_attempt !== false && ($now - $last_attempt) > $window_seconds) {
            $reset_stmt = mysqli_prepare(
                $conn,
                "UPDATE auth_rate_limits
                 SET attempts = 0, blocked_until = NULL
                 WHERE ip_address = ? AND action = ?"
            );
            if ($reset_stmt) {
                mysqli_stmt_bind_param($reset_stmt, 'ss', $ip, $action);
                mysqli_stmt_execute($reset_stmt);
                mysqli_stmt_close($reset_stmt);
            }

            return '';
        }

        if ((int)$row['attempts'] >= $limit) {
            $block_until_value = date('Y-m-d H:i:s', $now + $block_seconds);
            $block_stmt = mysqli_prepare(
                $conn,
                "UPDATE auth_rate_limits
                 SET blocked_until = ?
                 WHERE ip_address = ? AND action = ?"
            );
            if ($block_stmt) {
                mysqli_stmt_bind_param($block_stmt, 'sss', $block_until_value, $ip, $action);
                mysqli_stmt_execute($block_stmt);
                mysqli_stmt_close($block_stmt);
            }

            return auth_rate_limit_message($block_seconds);
        }

        return '';
    }
}

if (!function_exists('auth_rate_limit_register_attempt')) {
    function auth_rate_limit_register_attempt(
        mysqli $conn,
        string $action,
        bool $success,
        int $limit = 5,
        int $window_seconds = 900,
        int $block_seconds = 900,
        bool $reset_on_success = true
    ): void {
        if (!ensure_auth_rate_limits_table($conn)) {
            return;
        }

        $ip = auth_rate_limit_ip();
        $now = date('Y-m-d H:i:s');

        if ($success && $reset_on_success) {
            $clear_stmt = mysqli_prepare(
                $conn,
                "DELETE FROM auth_rate_limits WHERE ip_address = ? AND action = ?"
            );
            if ($clear_stmt) {
                mysqli_stmt_bind_param($clear_stmt, 'ss', $ip, $action);
                mysqli_stmt_execute($clear_stmt);
                mysqli_stmt_close($clear_stmt);
            }
            return;
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO auth_rate_limits (ip_address, action, attempts, last_attempt, blocked_until)
             VALUES (?, ?, 1, ?, NULL)
             ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                last_attempt = VALUES(last_attempt)"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sss', $ip, $action, $now);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $block_check_stmt = mysqli_prepare(
            $conn,
            "SELECT attempts, last_attempt
             FROM auth_rate_limits
             WHERE ip_address = ? AND action = ?
             LIMIT 1"
        );
        if (!$block_check_stmt) {
            return;
        }

        mysqli_stmt_bind_param($block_check_stmt, 'ss', $ip, $action);
        mysqli_stmt_execute($block_check_stmt);
        $result = mysqli_stmt_get_result($block_check_stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($block_check_stmt);

        if (!$row) {
            return;
        }

        $attempts = (int)($row['attempts'] ?? 0);
        $last_attempt = strtotime((string)($row['last_attempt'] ?? ''));
        if ($last_attempt !== false && (time() - $last_attempt) > $window_seconds) {
            return;
        }

        if ($attempts >= $limit) {
            $blocked_until = date('Y-m-d H:i:s', time() + $block_seconds);
            $block_stmt = mysqli_prepare(
                $conn,
                "UPDATE auth_rate_limits
                 SET blocked_until = ?
                 WHERE ip_address = ? AND action = ?"
            );
            if ($block_stmt) {
                mysqli_stmt_bind_param($block_stmt, 'sss', $blocked_until, $ip, $action);
                mysqli_stmt_execute($block_stmt);
                mysqli_stmt_close($block_stmt);
            }
        }
    }
}
?>