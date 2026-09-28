<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login_api();
$pdo = db();
$registry = resource_registry();

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        $rows = $pdo->query('SELECT * FROM archive ORDER BY archived_at DESC')->fetchAll();
        foreach ($rows as &$r) {
            $r['data'] = json_decode($r['data_json'], true);
            unset($r['data_json']);
        }
        json_response($rows);
        break;

    case 'POST':
        require_admin_api();
        $input = read_json_body();
        $action = $input['action'] ?? '';

        if ($action === 'restore') {
            $id = (int) ($input['id'] ?? 0);
            $entry = $pdo->prepare('SELECT * FROM archive WHERE id = :id');
            $entry->execute(['id' => $id]);
            $entry = $entry->fetch();
            if (!$entry) json_response(['error' => 'Archived record not found.'], 404);

            $data = json_decode($entry['data_json'], true);
            unset($data['id']); // let the source table assign a fresh id
            $table = $entry['source_table'];
            $cols = array_keys($data);
            $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', array_map(fn($c) => ":$c", $cols)) . ")";
            $pdo->prepare($sql)->execute($data);

            $pdo->prepare('DELETE FROM archive WHERE id = :id')->execute(['id' => $id]);
            $label = $registry[$entry['type']]['label'] ?? $entry['type'];
            log_activity("$label restored from archive");
            json_response(['ok' => true]);

        } elseif ($action === 'purge') {
            $id = (int) ($input['id'] ?? 0);
            $pdo->prepare('DELETE FROM archive WHERE id = :id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        } elseif ($action === 'clear') {
            $pdo->exec('DELETE FROM archive');
            log_activity('Archive emptied');
            json_response(['ok' => true]);

        } else {
            json_response(['error' => 'Unknown action.'], 422);
        }
        break;

    default:
        json_response(['error' => 'Method not allowed.'], 405);
}
