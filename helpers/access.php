<?php
// app/helpers/access.php
// RBAC & akses berbasis peran untuk PHP native

/**
 * current_user: ambil user aktif dari session
 * Pastikan saat login Anda set $_SESSION['user'] = ['id'=>..., 'role_id'=>..., 'role_slug'=>..., 'guru_id'=>...]
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * require_login: redirect ke login jika belum login
 */
function require_login(): void {
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * require_role: izinkan hanya role tertentu (array of role_slug)
 */
function require_role(array $allowed): void {
    $u = current_user();
    if (!$u || !in_array($u['role_slug'] ?? '', $allowed, true)) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}

/**
 * is_role: cek role saat ini
 */
function is_role(string $role): bool {
    $u = current_user();
    return $u && ($u['role_slug'] ?? '') === $role;
}

/**
 * guru_id: ambil guru_id dari user jika ada mapping user -> guru
 */
function current_guru_id(): ?int {
    $u = current_user();
    if (!$u) return null;
    return isset($u['guru_id']) && $u['guru_id'] ? (int)$u['guru_id'] : null;
}
