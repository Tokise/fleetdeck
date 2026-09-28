<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login_api();

$registry = resource_registry();
$type = $_GET['type'] ?? '';
if (!isset($registry[$type])) {
    json_response(['error' => 'Unknown resource type.'], 404);
}
$res = $registry[$type];
$table = $res['table'];
$pdo = db();

/** Whitelists + casts incoming fields for this resource before insert/update. */
function clean_input(array $input, array $res): array {
    $out = [];
    foreach ($res['fields'] as $field) {
        if (!array_key_exists($field, $input)) continue;
        $val = $input[$field];
        if (in_array($field, $res['numeric'], true)) {
            $val = is_numeric($val) ? $val + 0 : 0;
        } else {
            $val = trim((string) $val);
        }
        $out[$field] = $val;
    }
    return $out;
}

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC")->fetchAll();
        json_response($rows);
        break;

    case 'POST':
        require_admin_api();
        $data = clean_input(read_json_body(), $res);
        if (empty($data)) {
            json_response(['error' => 'No valid fields supplied.'], 422);
        }
        $cols = array_keys($data);
        $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', array_map(fn($c) => ":$c", $cols)) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $id = (int) $pdo->lastInsertId();
        log_activity($res['label'] . ' — record added');
        $row = $pdo->query("SELECT * FROM `$table` WHERE id = $id")->fetch();
        json_response($row, 201);
        break;

    case 'PUT':
        require_admin_api();
        $input = read_json_body();
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            json_response(['error' => 'Missing id.'], 422);
        }
        $data = clean_input($input, $res);
        if (empty($data)) {
            json_response(['error' => 'No valid fields supplied.'], 422);
        }
        $set = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $data['id'] = $id;
        $stmt = $pdo->prepare("UPDATE `$table` SET $set WHERE id = :id");
        $stmt->execute($data);
        log_activity($res['label'] . ' — record updated');
        $row = $pdo->query("SELECT * FROM `$table` WHERE id = $id")->fetch();
        json_response($row);
        break;

    case 'DELETE':
        // "Delete" moves the row into the archive table instead of
        // dropping it, so it can be restored later from the Archive view.
        require_admin_api();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_response(['error' => 'Missing id.'], 422);
        }
        $row = $pdo->query("SELECT * FROM `$table` WHERE id = $id")->fetch();
        if (!$row) {
            json_response(['error' => 'Record not found.'], 404);
        }
        $ins = $pdo->prepare('INSERT INTO archive (type, source_table, data_json) VALUES (:type, :src, :data)');
        $ins->execute(['type' => $type, 'src' => $table, 'data' => json_encode($row)]);
        $pdo->prepare("DELETE FROM `$table` WHERE id = :id")->execute(['id' => $id]);
        log_activity($res['label'] . ' moved to archive');
        json_response(['ok' => true]);
        break;

    default:
        json_response(['error' => 'Method not allowed.'], 405);
}
