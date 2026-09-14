<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Builders/TicketTemplateBuilder.php
 * Construtor responsável por criar e configurar os modelos de chamados.
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity\Builders;

use Entity;
use TicketTemplate;
use TicketTemplateMandatoryField;

class TicketTemplateBuilder
{
    private const TEMPLATE_NAME = 'Modelo Padrão do Setor';
    private const ICON = '📝';

    // Campos obrigatórios no modelo completo: o mínimo para roteamento/priorização.
    private const MANDATORY_FIELDS = ['content', 'itilcategories_id', 'urgency'];

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return int Number of ticket templates created/reused.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $so = array_flip(\TicketTemplate::getAllowedFields(true));
        $count = 0;
        $firstTemplateId = 0;

        foreach ($configs as $config) {
            $name = trim($config['name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $templateId = $this->getOrCreateTemplate($config, $entities_id);
            if ($templateId > 0) {
                if ($firstTemplateId === 0) {
                    $firstTemplateId = $templateId;
                }
                
                // Se não foi copiado de um modelo existente (que já teria os campos)
                // ou se quisermos garantir os mínimos de urgencia, category e content, podemos adicionar.
                // Para manter simples, só forçamos se for criação do zero.
                if (empty($config['copy_from'])) {
                    $this->setPredefinedField($templateId, $so['name'] ?? -1, $name);
                    $this->setPredefinedField($templateId, $so['type'] ?? -1, $config['type'] ?? 1);
                    $this->setPredefinedField($templateId, $so['itilcategories_id'] ?? -1, $config['itilcategories_id'] ?? 0);
                    if (!empty($config['content'])) {
                        $this->setPredefinedField($templateId, $so['content'] ?? -1, $config['content']);
                    }

                    foreach (self::MANDATORY_FIELDS as $key) {
                        $this->ensureMandatory($templateId, $so[$key] ?? -1);
                    }
                }
                $count++;
            }
        }

        // Definir o PRIMEIRO modelo criado/selecionado como o padrão da Entidade do setor
        if ($firstTemplateId > 0) {
            $entity = new Entity();
            if ($entity->getFromDB($entities_id)) {
                $entity->update([
                    'id' => $entities_id,
                    'tickettemplates_id' => $firstTemplateId
                ]);
            }
        }

        return $count;
    }

    private function getOrCreateTemplate(array $config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $template = new TicketTemplate();
        
        if ($template->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            return (int) $template->getID();
        }

        $sourceId = (int)($config['copy_from'] ?? 0);
        $sourceData = [];
        if ($sourceId > 0 && $template->getFromDB($sourceId)) {
            $sourceData = $template->fields;
        }

        $insertData = [
            'name'         => $name,
            'entities_id'  => $entities_id,
            'is_recursive' => 1,
        ];

        // Copiar outros campos do template se existir (ex: itilcategories_id, type)
        $fieldsToCopy = ['type', 'itilcategories_id', 'urgency', 'impact', 'priority', 'users_id_assign', 'groups_id_assign'];
        foreach ($fieldsToCopy as $field) {
            if (isset($sourceData[$field])) {
                $insertData[$field] = $sourceData[$field];
            }
        }

        $newId = (int) $template->add($insertData);

        // Clona os mandatory, hidden e predefined fields do template original se houver
        if ($newId > 0 && $sourceId > 0) {
            $this->cloneTemplateFields($sourceId, $newId, 'TicketTemplateMandatoryField');
            $this->cloneTemplateFields($sourceId, $newId, 'TicketTemplateHiddenField');
            $this->cloneTemplateFields($sourceId, $newId, 'TicketTemplatePredefinedField');
        }

        return $newId;
    }

    private function cloneTemplateFields(int $sourceId, int $newId, string $itemtype): void
    {
        global $DB;
        $table = \getTableForItemType($itemtype);
        if (!$DB->tableExists($table)) {
            return;
        }

        $iterator = $DB->request([
            'FROM' => $table,
            'WHERE' => ['tickettemplates_id' => $sourceId]
        ]);

        $item = new $itemtype();
        foreach ($iterator as $row) {
            unset($row['id']);
            $row['tickettemplates_id'] = $newId;
            $item->add($row);
        }
    }

    private function ensureMandatory(int $templateId, int $num): void
    {
        if ($num < 0) {
            return;
        }
        $field = new TicketTemplateMandatoryField();
        if (!$field->getFromDBByCrit(['tickettemplates_id' => $templateId, 'num' => $num])) {
            $field->add(['tickettemplates_id' => $templateId, 'num' => $num]);
        }
    }

    private function setPredefinedField(int $templateId, int $num, $value): void
    {
        if ($num < 0 || empty($value)) {
            return;
        }
        $field = new \TicketTemplatePredefinedField();
        if (!$field->getFromDBByCrit(['tickettemplates_id' => $templateId, 'num' => $num])) {
            $field->add(['tickettemplates_id' => $templateId, 'num' => $num, 'value' => $value]);
        } else {
            $field->update(['id' => $field->getID(), 'value' => $value]);
        }
    }
}
