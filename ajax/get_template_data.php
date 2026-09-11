<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — ajax/get_template_data.php
 * 
 * Este script é um endpoint AJAX responsável por buscar no banco de dados 
 * as configurações de um modelo de infraestrutura selecionado na tela 
 * (ex: Modelo de Chamado, Motivo de Pendência) quando o usuário escolhe 
 * algo no dropdown "Copiar de...". Ele retorna um JSON com os campos 
 * preenchidos para que o JavaScript atualize a tela em tempo real.
 * -----------------------------------------------------------------------
 */

define('GLPI_KEEP_CSRF_TOKEN', true);

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php'; }
if (!file_exists($inc)) { $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php'; }
include $inc;

header('Content-Type: application/json');

if (!Session::haveRight('plugin_glpinewentity', UPDATE)) {
    echo json_encode(['success' => false, 'error' => __('Acesso negado.', 'glpinewentity')]);
    exit;
}

$tabnum = (int)($_POST['tabnum'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0 || $tabnum <= 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$data = [];

switch ($tabnum) {
    case 1: // TicketTemplate
        $item = new TicketTemplate();
        if ($item->getFromDB($id)) {
            $data['name'] = $item->fields['name'] ?? '';
            // Predefined fields
            global $DB;
            $so = array_flip(\TicketTemplate::getAllowedFields(true));
            
            $numType = $so['type'] ?? -1;
            $numCat = $so['itilcategories_id'] ?? -1;
            $numContent = $so['content'] ?? -1;
            
            $iter = $DB->request([
                'FROM' => 'glpi_tickettemplatepredefinedfields',
                'WHERE' => ['tickettemplates_id' => $id]
            ]);
            
            foreach ($iter as $row) {
                if ($row['num'] == $numType) {
                    $data['type'] = $row['value'];
                } elseif ($row['num'] == $numCat) {
                    $data['itilcategories_id'] = $row['value'];
                } elseif ($row['num'] == $numContent) {
                    $data['content'] = $row['value'];
                }
            }

            // Fallback for type from entity config if not found in predefined fields
            if (!isset($data['type'])) {
                $data['type'] = Entity::getUsedConfig('tickettype', $item->fields['entities_id'], '', 1); // 1 = Incident by default
            }
        }
        break;
    case 2: // ITILFollowupTemplate
        $item = new ITILFollowupTemplate();
        if ($item->getFromDB($id)) {
            $data['name'] = $item->fields['name'] ?? '';
            $data['content'] = $item->fields['content'] ?? '';
        }
        break;
    case 3: // SolutionTemplate
        $item = new SolutionTemplate();
        if ($item->getFromDB($id)) {
            $data['name'] = $item->fields['name'] ?? '';
            $data['content'] = $item->fields['content'] ?? '';
        }
        break;
    case 4: // PendingReason
        $item = new PendingReason();
        if ($item->getFromDB($id)) {
            $data['name'] = $item->fields['name'] ?? '';
            $data['comment'] = $item->fields['comment'] ?? '';
            $data['is_default'] = $item->fields['is_default'] ?? 0;
            $data['is_pending_per_default'] = $item->fields['is_pending_per_default'] ?? 0;
            $data['calendars_id'] = $item->fields['calendars_id'] ?? 0;
            $data['followup_frequency'] = $item->fields['followup_frequency'] ?? 0;
            $data['itilfollowuptemplates_id'] = $item->fields['itilfollowuptemplates_id'] ?? 0;
            $data['followups_before_resolution'] = $item->fields['followups_before_resolution'] ?? 0;
            $data['solutiontemplates_id'] = $item->fields['solutiontemplates_id'] ?? 0;
        }
        break;
    case 5: // Notification
        $item = new Notification();
        if ($item->getFromDB($id)) {
            $data['name'] = $item->fields['name'] ?? '';
            // Content might be tricky, it's in the template. Just name is fine.
        }
        break;
    case 6: // Form
        if (class_exists('Glpi\\Form\\Form')) {
            $item = new Glpi\Form\Form();
            if ($item->getFromDB($id)) {
                $data['name'] = $item->fields['name'] ?? '';
                $data['content'] = $item->fields['description'] ?? '';
            }
        }
        break;
}

echo json_encode(['success' => true, 'data' => $data]);
