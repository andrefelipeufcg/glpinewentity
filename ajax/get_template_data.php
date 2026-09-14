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

global $DB, $CFG_GLPI;

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
            // Campos pré-definidos
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

            // Fallback para o tipo da configuração da entidade se não for encontrado nos campos pré-definidos
            if (!isset($data['type'])) {
                $data['type'] = Entity::getUsedConfig('tickettype', $item->fields['entities_id'], '', 1); // 1 = Incidente por padrão
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
            $data['is_active'] = $item->fields['is_active'] ?? 0;
            $data['itemtype'] = $item->fields['itemtype'] ?? 'Ticket';
            $data['event'] = $item->fields['event'] ?? 'new';
            $data['attach_documents'] = $item->fields['attach_documents'] ?? -2;
            $data['allow_response'] = $item->fields['allow_response'] ?? 1;
            $data['comment'] = $item->fields['comment'] ?? '';

            // Tentar descobrir o template atual
            global $DB;
            $iterator = $DB->request([
                'FROM' => 'glpi_notifications_notificationtemplates',
                'WHERE' => ['notifications_id' => $id]
            ]);
            $data['notificationtemplates_id'] = 0;
            foreach ($iterator as $row) {
                $data['notificationtemplates_id'] = $row['notificationtemplates_id'];
                break;
            }

            // Instanciar NotificationTarget com o evento correto para que os hooks de plugins
            // (como Behaviors) sejam disparados e os labels sejam resolvidos corretamente.
            $targetObj = null;
            $event = $data['event'] ?? '';
            $itemtype = $data['itemtype'] ?? 'Ticket';
            if (class_exists('\NotificationTarget')) {
                $baseTarget = \NotificationTarget::getInstanceByType($itemtype);
                if ($baseTarget) {
                    $targetClass = get_class($baseTarget);
                    try {
                        $targetObj = new $targetClass(null, $event);
                    } catch (\Throwable $e) {
                        $targetObj = $baseTarget;
                    }
                }
            }
            $data['targets_list'] = [];
            $data['exclusions_list'] = [];
            $targetsDb = $DB->request([
                'FROM' => 'glpi_notificationtargets',
                'WHERE' => ['notifications_id' => $id]
            ]);
            foreach ($targetsDb as $t) {
                $type = $t['type'];
                $items_id = $t['items_id'];
                $label = '';
                // Primeiro tentar resolver via NotificationTarget labels (inclui plugins)
                if ($targetObj && isset($targetObj->notification_targets_labels[$type][$items_id])) {
                    $label = $targetObj->notification_targets_labels[$type][$items_id];
                }
                // Fallback: resolver por tipo (Perfil, Grupo, Usuário)
                if (empty($label)) {
                    if ($items_id > 0) {
                        if ($type == \Notification::PROFILE_TYPE) {
                            $prof = new \Profile();
                            if ($prof->getFromDB($items_id)) { $label = "Perfil: " . $prof->fields['name']; }
                        } elseif ($type == \Notification::GROUP_TYPE) {
                            $grp = new \Group();
                            if ($grp->getFromDB($items_id)) { $label = "Grupo: " . $grp->fields['name']; }
                        } elseif ($type == \Notification::USER_TYPE) {
                            $usr = new \User();
                            if ($usr->getFromDB($items_id)) { $label = "Usuário: " . $usr->getName(); }
                        }
                    }
                }
                if (empty($label)) {
                    $label = "Tipo $type (Item $items_id)";
                }
                $key = $type . '_' . $items_id;
                if ($t['is_exclusion']) {
                    $data['exclusions_list'][] = $label;
                    if (!isset($first_exclusion)) $first_exclusion = $key;
                } else {
                    $data['targets_list'][] = $label;
                    if (!isset($first_target)) $first_target = $key;
                }
            }
            $data['targets_text'] = implode(', ', $data['targets_list']);
            $data['exclusions_text'] = implode(', ', $data['exclusions_list']);
            $data['target'] = !empty($first_target) ? $first_target : '';
            $data['exclusion'] = !empty($first_exclusion) ? $first_exclusion : '';
        }
        break;
    case 6: // Form
        global $DB;
        if (class_exists('Glpi\\Plugin\\Formcreator\\Form') || class_exists('PluginFormcreatorForm') || $DB->tableExists('glpi_forms_forms')) {
            // Usando DB diretamente para segurança caso a classe não seja facilmente instanciável
            $iter = $DB->request('glpi_forms_forms', ['id' => $id]);
            if ($iter->count() > 0) {
                $row = $iter->current();
                $data['name'] = $row['name'] ?? '';
                $data['description'] = $row['description'] ?? '';
                $data['forms_categories_id'] = $row['forms_categories_id'] ?? 0;
            }
        }
        break;
}

echo json_encode(['success' => true, 'data' => $data]);
