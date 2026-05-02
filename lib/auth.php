<?php
declare(strict_types=1);

function auth_user(): ?array {
    return $_SESSION['admin'] ?? null;
}

function auth_require(): void {
    if (!auth_user()) {
        redirect('/admin/login.php');
    }
}

function auth_login(int $id, string $email, ?string $name = null): void {
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => $id, 'email' => $email, 'name' => $name];
    $stmt = db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_attempt(string $email, string $password): bool {
    $stmt = db()->prepare('SELECT id, email, name, password_hash FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user) {
        // Constant-time-ish: hash anyway to prevent username enumeration timing
        password_verify($password, '$2y$12$abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUV');
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) return false;
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $upd = db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
        $upd->execute([':h' => $newHash, ':id' => $user['id']]);
    }
    auth_login((int)$user['id'], $user['email'], $user['name']);
    return true;
}
