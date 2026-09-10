<?php

namespace GlpiPlugin\Glpinewentity\Builders;

use Notification;
use Notification_NotificationTemplate;
use NotificationTemplate;
use NotificationTemplateTranslation;

class NotificationBuilder
{
    private const TEMPLATE_NAME = 'Template Padrão do Setor';
    private const EVENTS = ['new', 'update', 'solved', 'closed'];

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return int Number of notifications configured.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $count = 0;
        foreach ($configs as $config) {
            $this->createNotification($config, $entities_id);
            $count++;
        }
        return $count;
    }

    private function createNotification(array $config, int $entities_id): void
    {
        $name = trim($config['name'] ?? '');
        $content = trim($config['content'] ?? '');
        $sourceId = (int)($config['copy_from'] ?? 0);

        if (empty($name)) {
            return;
        }

        $notification = new Notification();
        
        // Verifica se já existe
        if ($notification->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            return;
        }

        $sourceData = [];
        if ($sourceId > 0 && $notification->getFromDB($sourceId)) {
            $sourceData = $notification->fields;
        }

        // 1. Cria a notificação
        $notificationId = cloneItem(
            new Notification(), 
            $sourceData, 
            [
                'name' => $name,
                'entities_id' => $entities_id,
                'is_recursive' => 1,
                'itemtype' => $sourceData['itemtype'] ?? 'Ticket',
                'event' => $sourceData['event'] ?? 'new',
                'mode' => $sourceData['mode'] ?? 'mailing',
                'is_active' => 1
            ]
        );

        if (!$notificationId) {
            return;
        }

        // 2. Lida com o Template
        $templateId = 0;

        // Se copiamos de uma notificação, vamos ver qual template ela usava
        if ($sourceId > 0) {
            global $DB;
            $iterator = $DB->request([
                'FROM' => 'glpi_notifications_notificationtemplates',
                'WHERE' => ['notifications_id' => $sourceId]
            ]);
            
            $sourceTemplateId = 0;
            $mode = 'mailing';
            foreach ($iterator as $row) {
                $sourceTemplateId = $row['notificationtemplates_id'];
                $mode = $row['mode'];
                break;
            }

            if ($sourceTemplateId > 0) {
                // Clona o template
                $template = new NotificationTemplate();
                if ($template->getFromDB($sourceTemplateId)) {
                    $templateId = cloneItem(
                        new NotificationTemplate(), 
                        $template->fields, 
                        [
                            'name' => $name . ' Template',
                            'entities_id' => $entities_id,
                            'is_recursive' => 1
                        ]
                    );

                    // Clona traduções
                    if ($templateId > 0) {
                        $transIter = $DB->request([
                            'FROM' => 'glpi_notificationtemplatetranslations',
                            'WHERE' => ['notificationtemplates_id' => $sourceTemplateId]
                        ]);
                        
                        $trans = new NotificationTemplateTranslation();
                        foreach ($transIter as $tRow) {
                            $tRow['notificationtemplates_id'] = $templateId;
                            if (!empty($content)) {
                                $tRow['content_text'] = $content;
                                $tRow['content_html'] = nl2br(htmlentities($content));
                            }
                            unset($tRow['id']);
                            $trans->add($tRow);
                        }
                    }
                }
            }
        }

        // Se não conseguiu clonar ou não tinha origem, cria um básico
        if ($templateId === 0) {
            $template = new NotificationTemplate();
            $templateId = (int) $template->add([
                'name' => $name . ' Template',
                'itemtype' => $sourceData['itemtype'] ?? 'Ticket',
                'entities_id' => $entities_id,
                'is_recursive' => 1,
            ]);

            if (!empty($content)) {
                $trans = new NotificationTemplateTranslation();
                $trans->add([
                    'notificationtemplates_id' => $templateId,
                    'language' => '',
                    'subject' => $name,
                    'content_html' => nl2br(htmlentities($content)),
                    'content_text' => $content,
                ]);
            }
        }

        // 3. Vincular notificação ao template
        if ($templateId > 0) {
            $join = new Notification_NotificationTemplate();
            $join->add([
                'notifications_id' => $notificationId,
                'mode' => $sourceData['mode'] ?? 'mailing',
                'notificationtemplates_id' => $templateId,
            ]);
            
            // Clona também os targets (recipients) da notificação original
            if ($sourceId > 0) {
                cloneTargets($sourceId, $notificationId);
            }
        }
    }
}

// Funções utilitárias no mesmo arquivo (poderiam estar numa trait/classe Base)
function cloneItem($obj, array $sourceData, array $overrides = []) {
    if (empty($sourceData)) {
        return (int) $obj->add($overrides);
    }
    $data = $sourceData;
    unset($data['id']);
    foreach ($overrides as $k => $v) {
        $data[$k] = $v;
    }
    return (int) $obj->add($data);
}

function cloneTargets($sourceId, $newId) {
    global $DB;
    $iterator = $DB->request([
        'FROM' => 'glpi_notificationtargets',
        'WHERE' => ['notifications_id' => $sourceId]
    ]);
    
    $target = new \NotificationTarget();
    foreach ($iterator as $row) {
        unset($row['id']);
        $row['notifications_id'] = $newId;
        $target->add($row);
    }
}
