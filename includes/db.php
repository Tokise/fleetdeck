<?php
require_once __DIR__ . '/../config.php';

/**
 * Returns a shared PDO connection. Dies with a friendly message
 * (instead of a raw stack trace) if MySQL isn't reachable or the
 * fleetdeck database hasn't been created yet — the most common
 * first-run issue on a fresh XAMPP install.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed. Have you run setup.php yet? (' . $e->getMessage() . ')']);
        } else {
            echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #e5484d;border-radius:8px;">'
               . '<h2 style="margin-top:0;">Can\'t connect to the database</h2>'
               . '<p>Make sure the <b>Apache</b> and <b>MySQL</b> services are both running in the XAMPP control panel, '
               . 'then visit <a href="setup.php">setup.php</a> to create the <code>fleetdeck</code> database and its tables.</p>'
               . '<p style="color:#888;font-size:13px;">Details: ' . htmlspecialchars($e->getMessage()) . '</p>'
               . '</div>';
        }
        exit;
    }
}
