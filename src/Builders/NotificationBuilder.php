<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Builders/NotificationBuilder.php
 * Construtor responsável por configurar e gerar as notificações da entidade.
 * -----------------------------------------------------------------------
 */

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
     * @return array Array contendo 'count' e 'configs' atualizado.
     */
    public function build(int $entities_id, array $configs = []): array
    {
        $count = 0;
        foreach ($configs as &$config) {
            $this->createNotification($config, $entities_id);
            $count++;
        }
        unset($config);
        return ['count' => $count, 'configs' => $configs];
    }

    private function createNotification(array &$config, int $entities_id): void
    {
        $name = trim($config['name'] ?? '');
        $sourceId = (int)($config['copy_from'] ?? 0);
        $generatedId = (int)($config['generated_id'] ?? 0);

        if (empty($name)) {
            return;
        }

        $notification = new Notification();
        
        // Verifica se já existe
        if ($generatedId > 0 && $notification->getFromDB($generatedId)) {
            $notification->update([
                 'id' => $generatedId,
                 'name' => $name,
                 'entities_id' => $entities_id
            ]);
            return;
        }

        if ($notification->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            $config['generated_id'] = $notification->getID();
            return;
        }

        $sourceData = [];
        if ($sourceId > 0 && $notification->getFromDB($sourceId)) {
            $sourceData = $notification->fields;
        }

        // 1. Cria a notificação
        $input = [
            'name' => $name,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'itemtype' => $config['itemtype'] ?? ($sourceData['itemtype'] ?? 'Ticket'),
            'event' => $config['event'] ?? ($sourceData['event'] ?? 'new'),
            'mode' => $config['mode'] ?? 'mailing',
            'is_active' => $config['is_active'] ?? 1,
            'attach_documents' => $config['attach_documents'] ?? -2,
            'allow_response' => $config['allow_response'] ?? 1,
            'comment' => $config['comment'] ?? '',
        ];

        $notificationId = 0;
        if (!empty($sourceData)) {
            $notificationId = cloneItem(new Notification(), $sourceData, $input);
        } else {
            $notificationId = (int)$notification->add($input);
        }

        if (!$notificationId) {
            return;
        }

        $config['generated_id'] = $notificationId;

        // 2. Lida com o Template (apenas vincula o que foi selecionado na interface)
        $templateId = (int)($config['notificationtemplates_id'] ?? 0);

        // Se o usuário selecionou "Padrão da Origem" mas a origem tinha um, a gente usa
        if ($templateId === 0 && $sourceId > 0) {
            global $DB;
            $iterator = $DB->request([
                'FROM' => 'glpi_notifications_notificationtemplates',
                'WHERE' => ['notifications_id' => $sourceId]
            ]);
            foreach ($iterator as $row) {
                $templateId = (int)$row['notificationtemplates_id'];
                break;
            }
        }

        // 3. Vincular notificação ao template
        if ($templateId > 0) {
            $join = new Notification_NotificationTemplate();
            $join->add([
                'notifications_id' => $notificationId,
                'notificationtemplates_id' => $templateId,
                'mode' => 'mailing'
            ]);
        }

        // 4. Inserir Destinatário e Exclusão selecionados na interface
        $targetStr = trim($config['target'] ?? '');
        $exclusionStr = trim($config['exclusion'] ?? '');

        if (!empty($targetStr)) {
            $parts = explode('_', $targetStr);
            if (count($parts) >= 2) {
                $target = new \NotificationTarget();
                $target->add([
                    'notifications_id' => $notificationId,
                    'type' => $parts[0],
                    'items_id' => $parts[1],
                    'is_exclusion' => 0
                ]);
            }
        }

        if (!empty($exclusionStr)) {
            $parts = explode('_', $exclusionStr);
            if (count($parts) >= 2) {
                $target = new \NotificationTarget();
                $target->add([
                    'notifications_id' => $notificationId,
                    'type' => $parts[0],
                    'items_id' => $parts[1],
                    'is_exclusion' => 1
                ]);
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
