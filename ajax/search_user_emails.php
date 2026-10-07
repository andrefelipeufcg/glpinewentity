<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/search_user_emails.php
 *
 * Este script é um endpoint AJAX responsável por pesquisar e confirmar
 * endereços de e-mail associados a usuários cadastrados no GLPI.
 * É utilizado pelos campos de autocomplete do wizard para exibir somente
 * e-mails existentes na base e filtrar endereços colados pelo usuário.
 * -----------------------------------------------------------------------
 */

define('GLPI_KEEP_CSRF_TOKEN', true);

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) {
    $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php';
}
if (!file_exists($inc)) {
    $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php';
}
include $inc;

use GlpiPlugin\Glpinewentity\Sector;

global $DB;
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !Session::haveRight('plugin_glpinewentity', READ)
    || !Sector::canView()
) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

$action = $_POST['action'] ?? '';
$emails = [];

if ($action === 'search') {
    $term = trim((string)($_POST['term'] ?? ''));
    if (mb_strlen($term) < 2) {
        echo json_encode(['results' => []]);
        exit;
    }

    $userEmails = $DB->request([
        'SELECT' => 'glpi_useremails.email',
        'FROM' => 'glpi_useremails',
        'INNER JOIN' => [
            'glpi_users' => [
                'ON' => [
                    'glpi_useremails' => 'users_id',
                    'glpi_users' => 'id',
                ],
            ],
        ],
        'WHERE' => ['glpi_useremails.email' => ['LIKE', '%' . $term . '%']],
        'ORDER' => 'glpi_useremails.email ASC',
        'LIMIT' => 20,
    ]);
    foreach ($userEmails as $row) {
        $emails[] = (string)$row['email'];
    }

    $users = $DB->request([
        'SELECT' => 'glpi_users.name',
        'FROM' => 'glpi_users',
        'WHERE' => ['glpi_users.name' => ['LIKE', '%' . $term . '%']],
        'ORDER' => 'glpi_users.name ASC',
        'LIMIT' => 20,
    ]);
    foreach ($users as $row) {
        $emails[] = (string)$row['name'];
    }
} elseif ($action === 'resolve') {
    $requested = array_slice((array)($_POST['emails'] ?? []), 0, 100);
    $requested = array_values(array_unique(array_filter(array_map(
        static fn($email) => trim((string)$email),
        $requested
    ), static fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))));

    if ($requested) {
        $userEmails = $DB->request([
            'SELECT' => 'glpi_useremails.email',
            'FROM' => 'glpi_useremails',
            'INNER JOIN' => [
                'glpi_users' => [
                    'ON' => [
                        'glpi_useremails' => 'users_id',
                        'glpi_users' => 'id',
                    ],
                ],
            ],
            'WHERE' => ['glpi_useremails.email' => $requested],
        ]);
        foreach ($userEmails as $row) {
            $emails[] = (string)$row['email'];
        }

        $users = $DB->request([
            'SELECT' => 'glpi_users.name',
            'FROM' => 'glpi_users',
            'WHERE' => ['glpi_users.name' => $requested],
        ]);
        foreach ($users as $row) {
            $emails[] = (string)$row['name'];
        }
    }
} else {
    http_response_code(400);
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
$seen = [];
foreach ($emails as $email) {
    $key = mb_strtolower($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($seen[$key])) {
        $seen[$key] = true;
        $results[] = ['id' => $email, 'text' => $email];
    }
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
