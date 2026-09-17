<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Builders/WaitReasonBuilder.php
 * Construtor responsável por criar os motivos de pendências (PendingReasons).
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity\Builders;

use PendingReason;

class WaitReasonBuilder
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
            $name = trim($config['name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $this->getOrCreatePendingReason($config, $entities_id);
            $count++;
        }
        unset($config);

        return ['count' => $count, 'configs' => $configs];
    }

    private function getOrCreatePendingReason(array &$config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $generatedId = (int)($config['generated_id'] ?? 0);
        $item = new PendingReason();
        
        if ($generatedId > 0 && $item->getFromDB($generatedId)) {
            $item->update([
                 'id' => $generatedId,
                 'name' => $name,
                 'entities_id' => $entities_id
            ]);
            return $generatedId;
        }

        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            $config['generated_id'] = $item->getID();
            return (int) $item->getID();
        }

        $insertData = [
            'name'                        => $name,
            'entities_id'                 => $entities_id,
            'is_recursive'                => 1,
            'is_default'                  => (int)($config['is_default'] ?? 0),
            'is_pending_per_default'      => (int)($config['is_pending_per_default'] ?? 0),
            'calendars_id'                => (int)($config['calendars_id'] ?? 0),
            'followup_frequency'          => (int)($config['followup_frequency'] ?? 0),
            'followups_before_resolution' => (int)($config['followups_before_resolution'] ?? 0),
            'itilfollowuptemplates_id'    => (int)($config['itilfollowuptemplates_id'] ?? 0),
            'solutiontemplates_id'        => (int)($config['solutiontemplates_id'] ?? 0),
            'comment'                     => $config['comment'] ?? '',
        ];

        $newId = (int) $item->add($insertData);
        $config['generated_id'] = $newId;
        return $newId;
    }
}
