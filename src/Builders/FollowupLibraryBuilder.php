<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Builders/FollowupLibraryBuilder.php
 * Construtor responsável por criar os modelos de respostas básicas (acompanhamentos).
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity\Builders;

use ITILFollowupTemplate;

class FollowupLibraryBuilder
{
    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return array Array contendo 'count' e 'configs' atualizado.
     */
    public function build(int $entities_id, array $configs = []): array
    {
        $count = 0;
        foreach ($configs as &$config) {
            $this->getOrCreateTemplate($config, $entities_id);
            $count++;
        }
        unset($config);
        return ['count' => $count, 'configs' => $configs];
    }

    private function getOrCreateTemplate(array &$config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $content = trim($config['content'] ?? '');
        $generatedId = (int)($config['generated_id'] ?? 0);
        
        if (empty($name)) {
            return 0;
        }

        $item = new ITILFollowupTemplate();

        if ($generatedId > 0 && $item->getFromDB($generatedId)) {
            $item->update([
                 'id' => $generatedId,
                 'name' => $name,
                 'entities_id' => $entities_id,
                 'content' => !empty($content) ? $content : $item->fields['content']
            ]);
            return $generatedId;
        }

        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            $config['generated_id'] = $item->getID();
            return (int) $item->getID();
        }

        $sourceId = (int)($config['copy_from'] ?? 0);
        $sourceData = [];
        if ($sourceId > 0 && $item->getFromDB($sourceId)) {
            $sourceData = $item->fields;
        }

        // Mistura sourceData com name e content preenchidos (se content estiver vazio, usa do source)
        $insertData = [
            'name' => $name,
            'content' => !empty($content) ? $content : ($sourceData['content'] ?? ''),
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'requesttypes_id' => $sourceData['requesttypes_id'] ?? 0,
            'is_private' => $sourceData['is_private'] ?? 0,
        ];

        $newId = (int) $item->add($insertData);
        $config['generated_id'] = $newId;
        return $newId;
    }
}
