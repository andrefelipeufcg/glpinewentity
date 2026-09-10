<?php

use GlpiPlugin\Glpinewentity\Builders\TicketTemplateBuilder;
use GlpiPlugin\Glpinewentity\Builders\FollowupLibraryBuilder;
use GlpiPlugin\Glpinewentity\Builders\SolutionLibraryBuilder;
use GlpiPlugin\Glpinewentity\Builders\WaitReasonBuilder;
use GlpiPlugin\Glpinewentity\Builders\NotificationBuilder;
use GlpiPlugin\Glpinewentity\Builders\FormBuilder;
use GlpiPlugin\Glpinewentity\Sector;

include('../../../inc/includes.php');

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

$entities_id = (int)$sector->fields['entities_id'];
if ($entities_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'A entidade deste setor ainda não foi gerada ou está corrompida. Edite e salve os dados da entidade antes.']);
    exit;
}

$meta = json_decode($sector->fields['metadata'] ?? '{}', true) ?: [];
$tabKey = 'tab_' . $tabnum;
$savedConfigs = $meta['configs'][$tabKey] ?? [];

if (empty($savedConfigs)) {
    echo json_encode(['success' => false, 'error' => 'Nenhuma configuração definida para esta aba. Salve o rascunho primeiro.']);
    exit;
}

try {
    $count = 0;
    
    switch ($tabnum) {
        case 1:
            $builder = new TicketTemplateBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        case 2:
            $builder = new FollowupLibraryBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        case 3:
            $builder = new SolutionLibraryBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        case 4:
            $builder = new WaitReasonBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        case 5:
            $builder = new NotificationBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        case 6:
            $builder = new FormBuilder();
            $count = $builder->build($entities_id, $savedConfigs);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Aba desconhecida.']);
            exit;
    }

    echo json_encode(['success' => true, 'count' => $count]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
