<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function is_admin(): bool {
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}

/** Page-level guard: bounce anonymous visitors to the login screen. */
function require_login_page(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** API-level guard: anonymous requests get a 401 JSON body instead of a redirect. */
function require_login_api(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not signed in.']);
        exit;
    }
}

/** API-level guard: only the admin role may create, edit, archive, restore or purge. */
function require_admin_api(): void {
    require_login_api();
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Only admin can do that.']);
        exit;
    }
}
