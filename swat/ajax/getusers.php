<?php
/**
 * SWAT Plugin - AJAX: Search Users & Contacts
 */
include('../../../inc/includes.php');

Session::checkLoginUser();
header('Content-Type: application/json; charset=utf-8');

global $DB;

$term    = trim($_GET['term'] ?? '');
$type    = $_GET['type'] ?? 'all'; // user | contact | all
$results = [];

if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $DB->escape($term) . '%';

// ── Users ─────────────────────────────────────────────────────────────────────
if (in_array($type, ['user', 'all'])) {
    foreach ($DB->request([
        'SELECT' => ['id', 'firstname', 'realname', 'name'],
        'FROM'   => 'glpi_users',
        'WHERE'  => [
            'is_deleted' => 0,
            'is_active'  => 1,
            ['OR' => [
                ['realname'  => ['LIKE', $like]],
                ['firstname' => ['LIKE', $like]],
                ['name'      => ['LIKE', $like]],
            ]],
        ],
        'ORDER'  => 'realname ASC',
        'LIMIT'  => 20,
    ]) as $row) {
        $display = trim($row['firstname'] . ' ' . $row['realname']);
        if (!$display) $display = $row['name'];
        $results[] = [
            'id'           => (int)$row['id'],
            'type'         => 'user',
            'display_name' => $display,
            'label'        => $display . ' (User)',
        ];
    }
}

// ── Contacts ──────────────────────────────────────────────────────────────────
if (in_array($type, ['contact', 'all'])) {
    foreach ($DB->request([
        'SELECT' => ['id', 'firstname', 'name', 'email'],
        'FROM'   => 'glpi_contacts',
        'WHERE'  => [
            'is_deleted' => 0,
            ['OR' => [
                ['name'      => ['LIKE', $like]],
                ['firstname' => ['LIKE', $like]],
                ['email'     => ['LIKE', $like]],
            ]],
        ],
        'ORDER'  => 'name ASC',
        'LIMIT'  => 20,
    ]) as $row) {
        $display = trim($row['firstname'] . ' ' . $row['name']);
        $results[] = [
            'id'           => (int)$row['id'],
            'type'         => 'contact',
            'display_name' => $display,
            'label'        => $display . ' (Contact)',
        ];
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
