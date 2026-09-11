<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/save_draft_configs.php
 * 
 * Este script é um endpoint AJAX que recebe os dados do formulário 
 * preenchidos na tela (nome, calendários, frequências, etc.) e salva 
 * um rascunho em formato JSON no campo "metadata" da tabela de Setores. 
 * Isso permite que o usuário salve seu progresso antes de rodar a 
 * padronização completa, sem perder dados caso a aba seja recarregada.
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

// Tab 4 special fields
$is_default = $_POST['items_is_default'] ?? [];
$is_pending = $_POST['items_is_pending_per_default'] ?? [];
$calendars_id = $_POST['items_calendars_id'] ?? [];
$followup_frequency = $_POST['items_followup_frequency'] ?? [];
$foltpl_id = $_POST['items_itilfollowuptemplates_id'] ?? [];
$fbr = $_POST['items_followups_before_resolution'] ?? [];
$soltpl_id = $_POST['items_solutiontemplates_id'] ?? [];
$comments = $_POST['items_comment'] ?? [];

$configsToSave = [];
for ($i = 0; $i < count($names); $i++) {
    $name = trim($names[$i] ?? '');
    if (empty($name)) {
        continue;
    }
    
    $itemConf = [
        'name'      => $name,
        'content'   => trim($contents[$i] ?? ''),
        'copy_from' => (int)($copyFrom[$i] ?? 0),
        'type'      => (int)($types[$i] ?? 0),
        'itilcategories_id' => (int)($categories[$i] ?? 0),
    ];
    
    if ($tabnum == 4) {
        $itemConf['is_default'] = (int)($is_default[$i] ?? 0);
        $itemConf['is_pending_per_default'] = (int)($is_pending[$i] ?? 0);
        $itemConf['calendars_id'] = (int)($calendars_id[$i] ?? 0);
        $itemConf['followup_frequency'] = (int)($followup_frequency[$i] ?? 0);
        $itemConf['itilfollowuptemplates_id'] = (int)($foltpl_id[$i] ?? 0);
        $itemConf['followups_before_resolution'] = (int)($fbr[$i] ?? 0);
        $itemConf['solutiontemplates_id'] = (int)($soltpl_id[$i] ?? 0);
        $itemConf['comment'] = trim($comments[$i] ?? '');
    }
    
    $configsToSave[] = $itemConf;
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
