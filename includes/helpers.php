<?php

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Reads and JSON-decodes the raw request body (used by POST/PUT). */
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function log_activity(string $text): void {
    $stmt = db()->prepare('INSERT INTO activity_log (text) VALUES (:text)');
    $stmt->execute(['text' => $text]);
}

function recent_activity(int $limit = 8): array {
    $stmt = db()->prepare('SELECT text, created_at FROM activity_log ORDER BY id DESC LIMIT :lim');
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Resource registry for the generic CRUD endpoint (api/records.php).
 * `fields` is the whitelist of columns a client is allowed to write —
 * anything not listed here is silently dropped from incoming requests.
 * `numeric` fields are cast with (float)/(int) before insert/update.
 */
function resource_registry(): array {
    return [
        'vehicles' => [
            'table'   => 'vehicles',
            'fields'  => ['plate', 'type', 'make', 'model', 'year', 'odometer', 'status'],
            'numeric' => ['year', 'odometer'],
            'label'   => 'Vehicle',
        ],
        'reservations' => [
            'table'   => 'reservations',
            'fields'  => ['vehicle', 'requester', 'purpose', 'pickup', 'destination', 'date', 'status'],
            'numeric' => [],
            'label'   => 'Reservation',
        ],
        'drivers' => [
            'table'   => 'drivers',
            'fields'  => ['name', 'license', 'phone', 'trips', 'on_time', 'safety', 'status'],
            'numeric' => ['trips', 'on_time', 'safety'],
            'label'   => 'Driver',
        ],
        'fuel' => [
            'table'   => 'fuel_logs',
            'fields'  => ['vehicle', 'date', 'liters', 'price_per_liter', 'odometer'],
            'numeric' => ['liters', 'price_per_liter', 'odometer'],
            'label'   => 'Fuel entry',
        ],
        'routes' => [
            'table'   => 'routes',
            'fields'  => ['name', 'origin', 'destination', 'distance', 'duration', 'status'],
            'numeric' => ['distance'],
            'label'   => 'Route',
        ],
    ];
}
