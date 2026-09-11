<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/save_draft_configs.php
 * Salva as configurações editadas no JSON do banco (metadata) antes da aplicação.
 * -----------------------------------------------------------------------
 */

define('GLPI_KEEP_CSRF_TOKEN', true);

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }
include $inc;

use GlpiPlugin\Glpinewentity\Sector;

header('Content-Type: application/json');

if (!Session::haveRight('plugin_glpinewentity', UPDATE)) {
    echo json_encode(['success' => false, 'error' => __('Acesso negado.', 'glpinewentity')]);
    exit;
}

$action   = $_POST['action'] ?? '';
$sectorId = (int)($_POST['sector_id'] ?? 0);
$tabnum   = (int)($_POST['tabnum'] ?? 0);

if ($action !== 'save_draft' || $sectorId <= 0 || $tabnum <= 0) {
    echo json_encode(['success' => false, 'error' => __('Parâmetros inválidos.', 'glpinewentity')]);
    exit;
}

$sector = new Sector();
if (!$sector->getFromDB($sectorId)) {
    echo json_encode(['success' => false, 'error' => __('Setor não encontrado.', 'glpinewentity')]);
    exit;
}

// Montar o array de configs baseado no form submetido
$names    = $_POST['items_name'] ?? [];
$contents = $_POST['items_content'] ?? [];
$copyFrom = $_POST['items_copy_from'] ?? [];
$types    = $_POST['items_type'] ?? [];
$categories = $_POST['items_category'] ?? [];

$configsToSave = [];
for ($i = 0; $i < count($names); $i++) {
    $name = trim($names[$i] ?? '');
    if (empty($name)) {
        continue;
    }
    $configsToSave[] = [
        'name'      => $name,
        'content'   => trim($contents[$i] ?? ''),
        'copy_from' => (int)($copyFrom[$i] ?? 0),
        'type'      => (int)($types[$i] ?? 0),
        'itilcategories_id' => (int)($categories[$i] ?? 0),
    ];
}

// Puxa metadata atual
$meta = json_decode($sector->fields['metadata'] ?? '{}', true) ?: [];

// Atualiza a chave específica da aba
$tabKey = 'tab_' . $tabnum;
if (!isset($meta['configs'])) {
    $meta['configs'] = [];
}
$meta['configs'][$tabKey] = $configsToSave;

// Salva de volta
if ($sector->update([
    'id'       => $sectorId,
    'metadata' => json_encode($meta)
])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => __('Falha ao atualizar o banco de dados.', 'glpinewentity')]);
}
