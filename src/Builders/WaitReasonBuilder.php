<?php

namespace GlpiPlugin\Glpinewentity\Builders;

use PendingReason;

class WaitReasonBuilder
{
    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return int Number of pending reasons created/reused.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $count = 0;

        foreach ($configs as $config) {
            $name = trim($config['name'] ?? '');
            if (empty($name)) {
                continue;
            }

            $this->getOrCreatePendingReason($config, $entities_id);
            $count++;
        }

        return $count;
    }

    private function getOrCreatePendingReason(array $config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $item = new PendingReason();
        
        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
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

        return (int) $item->add($insertData);
    }
}
