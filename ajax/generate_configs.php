<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/generate_configs.php
 *
 * Este script é um endpoint AJAX acionado pelo botão "Aplicar Padronização".
 * Ele lê o rascunho salvo no banco de dados e repassa para as classes
 * Builder corretas (TicketTemplateBuilder, WaitReasonBuilder, etc.), que
 * farão a criação final dos modelos e registros na Entidade do sistema.
 * -----------------------------------------------------------------------
 */

use GlpiPlugin\Glpinewentity\Builders\TicketTemplateBuilder;
use GlpiPlugin\Glpinewentity\Builders\FollowupLibraryBuilder;
use GlpiPlugin\Glpinewentity\Builders\SolutionLibraryBuilder;
use GlpiPlugin\Glpinewentity\Builders\WaitReasonBuilder;
use GlpiPlugin\Glpinewentity\Builders\NotificationBuilder;
use GlpiPlugin\Glpinewentity\Builders\FormBuilder;
use GlpiPlugin\Glpinewentity\Sector;

define('GLPI_KEEP_CSRF_TOKEN', true);

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }

if (!file_exists($inc) && isset($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $parts = explode('/', trim($scriptDir, '/'));
    $pluginPos = array_search('plugins', $parts);
    if ($pluginPos !== false && $pluginPos > 0) {
        $sub = implode('/', array_slice($parts, 0, $pluginPos));
        $check = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $sub . '/inc/includes.php';
        if (file_exists($check)) {
            $inc = $check;
        }
    }
}

if (!file_exists($inc) && isset($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $check = rtrim($_SERVER['CONTEXT_DOCUMENT_ROOT'], '/') . '/inc/includes.php';
    if (file_exists($check)) {
        $inc = $check;
    }
}

include $inc;

header('Content-Type: application/json');

// Verificar sessão
if (!Session::haveRight('plugin_glpinewentity', UPDATE)) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

$action = $_POST['action'] ?? '';
$sector_id = (int)($_POST['sector_id'] ?? 0);
$tabnum = (int)($_POST['tabnum'] ?? 0);

if ($action !== 'generate' || $sector_id <= 0 || $tabnum <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

$sector = new Sector();
if (!$sector->getFromDB($sector_id)) {
    echo json_encode(['success' => false, 'error' => 'Setor não encontrado.']);
    exit;
}

$meta = json_decode($sector->fields['metadata'] ?? '{}', true) ?: [];
$new_entity_id = (int)($meta['entity_id'] ?? 0);

if ($new_entity_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'A entidade deste setor ainda não foi gerada ou está corrompida. Edite e salve os dados da entidade antes.']);
    exit;
}

$tabKey = 'tab_' . $tabnum;
$savedConfigs = $meta['configs'][$tabKey] ?? [];

if (empty($savedConfigs)) {
    echo json_encode(['success' => false, 'error' => 'Nenhuma configuração definida para esta aba. Salve o rascunho primeiro.']);
    exit;
}

try {
    $count = 0;
    $updatedConfigs = [];

    switch ($tabnum) {
        case 1:
            $builder = new TicketTemplateBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        case 2:
            $builder = new FollowupLibraryBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        case 3:
            $builder = new SolutionLibraryBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        case 4:
            $builder = new WaitReasonBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        case 5:
            $builder = new NotificationBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        case 6:
            $builder = new FormBuilder();
            $result = $builder->build($new_entity_id, $savedConfigs);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Aba desconhecida.']);
            exit;
    }

    $count = $result['count'];
    $updatedConfigs = $result['configs'];

    $meta['configs'][$tabKey] = $updatedConfigs;
    $sector->update([
        'id'       => $sector_id,
        'metadata' => json_encode($meta)
    ]);

    Session::addMessageAfterRedirect('Configurações aplicadas com sucesso!', true, INFO);

    echo json_encode(['success' => true, 'count' => $count]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
