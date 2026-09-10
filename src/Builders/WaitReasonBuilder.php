<?php

namespace GlpiPlugin\Glpinewentity\Builders;

use ITILFollowupTemplate;
use PendingReason;
use SolutionTemplate;

class WaitReasonBuilder
{
    private static function getReasons(): array
    {
        return [
            [
                'name' => __('Aguardando retorno do usuário', 'glpinewentity'),
                'icon' => '⏳',
                'followup_weeks' => 2,
                'followups_before_resolution' => 3,
                'followup_content' => __("Olá,\n\nEstamos aguardando informações adicionais de sua parte para dar continuidade ao atendimento deste chamado. Por favor, responda o mais breve possível.\n\nCaso não tenhamos retorno, este chamado será encerrado automaticamente após algumas tentativas de contato.", 'glpinewentity'),
                'solution_content' => __("Chamado solucionado automaticamente após diversas tentativas de contato sem retorno por parte do usuário. Sinta-se à vontade para reabri-lo ou criar um novo chamado caso o problema persista.", 'glpinewentity'),
            ],
            [
                'name' => __('Aguardando entrega de fornecedor', 'glpinewentity'),
                'icon' => '🚚',
                'followup_weeks' => 0,
                'followups_before_resolution' => 0,
            ],
            [
                'name' => __('Intervenção planejada', 'glpinewentity'),
                'icon' => '🗓️',
                'followup_weeks' => 0,
                'followups_before_resolution' => 0,
            ],
            [
                'name' => __('Aguardando validação interna', 'glpinewentity'),
                'icon' => '✅',
                'followup_weeks' => 1,
                'followups_before_resolution' => 0,
                'followup_content' => __("Olá,\n\nEste chamado está aguardando uma validação interna da equipe. Manteremos você informado sobre o andamento desta validação.", 'glpinewentity'),
            ],
        ];
    }

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return int Number of wait reasons created/reused.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $count = 0;
        foreach ($configs as $config) {
            $this->getOrCreateReason($config, $entities_id);
            $count++;
        }
        return $count;
    }

    private function getOrCreateReason(array $config, int $entities_id): void
    {
        $pendingReason = new PendingReason();
        
        $name = trim($config['name'] ?? '');
        if (empty($name)) {
            return;
        }

        // Verifica se já existe o motivo para esta entidade
        if ($pendingReason->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            return; // Já existe
        }

        $sourceId = (int)($config['copy_from'] ?? 0);
        $sourceData = [];
        if ($sourceId > 0 && $pendingReason->getFromDB($sourceId)) {
            $sourceData = $pendingReason->fields;
        }

        // Monta os dados para inserção misturando o source com os preenchidos
        $insertData = [
            'name' => $name,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
            'followup_frequency' => $sourceData['followup_frequency'] ?? (2 * WEEK_TIMESTAMP),
            'followups_before_resolution' => $sourceData['followups_before_resolution'] ?? 3,
        ];

        // Se copiamos de um modelo, ele pode ter templates de followup/solução atrelados.
        // Vamos cloná-los ou usá-los? O melhor é clonar para a nova entidade se existirem e o usuário não passou novo texto.
        // Porém, como não temos campo de texto na aba 4, apenas copiamos o id do template se ele existir.
        if (isset($sourceData['itilfollowuptemplates_id'])) {
            $insertData['itilfollowuptemplates_id'] = $sourceData['itilfollowuptemplates_id'];
        }
        if (isset($sourceData['solutiontemplates_id'])) {
            $insertData['solutiontemplates_id'] = $sourceData['solutiontemplates_id'];
        }

        $id = $pendingReason->add($insertData);
    }
}
