<?php

namespace GlpiPlugin\Glpinewentity\Builders;

use SolutionTemplate;
use SolutionType;

class SolutionLibraryBuilder
{
    private const GREETING = "{% set requesters = itemtype == 'Change' ? change.requesters.users : (itemtype == 'Problem' ? problem.requesters.users : ticket.requesters.users) %}{% if requesters|length > 0 %}Olá {{ requesters|first.fullname }},{% else %}Olá,{% endif %}";
    private const SOLVE_DATE = "{% set solve_date = itemtype == 'Change' ? change.solvedate : (itemtype == 'Problem' ? problem.solvedate : ticket.solvedate) %}{{ solve_date | date('d/m/Y H:i') }}";

    private static function getTypes(): array
    {
        return [
            [
                'name' => __('Assistência / Suporte ao usuário', 'glpinewentity'),
                'icon' => '🙋',
                'comment' => __('Ajuda fornecida ao usuário: explicações, orientação, treinamento — sem intervenção técnica.', 'glpinewentity'),
                'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
                'templates' => [
                    [
                        'name' => __('Acompanhamento do usuário concluído', 'glpinewentity'),
                        'icon' => '🙋',
                        'content' => self::GREETING . "\n\n" . __("Acompanhamos o usuário passo a passo para resolver sua solicitação.\n\nAção realizada: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                    [
                        'name' => __('Treinamento realizado', 'glpinewentity'),
                        'icon' => '🎓',
                        'content' => self::GREETING . "\n\n" . __("Foi realizada uma sessão de treinamento/conscientização com o usuário sobre a ferramenta em questão.\n\nTópicos abordados: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                ],
            ],
            [
                'name' => __('Resolução técnica', 'glpinewentity'),
                'icon' => '🔧',
                'comment' => __('Correção de hardware, software ou configuração de sistema/rede/aplicativo.', 'glpinewentity'),
                'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 1,
                'templates' => [
                    [
                        'name' => __('Resolução técnica aplicada', 'glpinewentity'),
                        'icon' => '🔧',
                        'content' => self::GREETING . "\n\n" . __("O problema foi identificado e corrigido.\n\nCausa: \nAção realizada: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                    [
                        'name' => __('Substituição de equipamento realizada', 'glpinewentity'),
                        'icon' => '🔩',
                        'content' => self::GREETING . "\n\n" . __("O equipamento com defeito foi substituído.\n\nEquipamento envolvido: \nNovo equipamento: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                ],
            ],
            [
                'name' => __('Informacional', 'glpinewentity'),
                'icon' => 'ℹ️',
                'comment' => __('Encerramentos sem intervenção técnica: comportamento normal, duplicata, cancelamento.', 'glpinewentity'),
                'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
                'templates' => [
                    [
                        'name' => __('Funcionamento normal constatado', 'glpinewentity'),
                        'icon' => '✅',
                        'content' => self::GREETING . "\n\n" . __("Após verificação, o comportamento relatado é normal, nenhuma anomalia foi detectada.\n\nAtenciosamente,", 'glpinewentity'),
                    ],
                    [
                        'name' => __('Chamado duplicado', 'glpinewentity'),
                        'icon' => '📑',
                        'content' => self::GREETING . "\n\n" . __("Este chamado é duplicado de uma solicitação já em andamento.\n\nChamado de referência: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                    [
                        'name' => __('Solicitação incompleta', 'glpinewentity'),
                        'icon' => '❓',
                        'content' => self::GREETING . "\n\n" . __("Sua solicitação não contém informações suficientes para ser processada. Pedimos que informe:\n\n- Descrição precisa do problema ou da solicitação\n- Captura de tela, se aplicável\n- Passos para reproduzir o problema\n\nRetomaremos o atendimento assim que recebermos essas informações.\n\nAtenciosamente,", 'glpinewentity'),
                    ],
                ],
            ],
            [
                'name' => __('Gerenciamento de acessos', 'glpinewentity'),
                'icon' => '🔑',
                'comment' => __('Contas de usuário, direitos de acesso, senhas.', 'glpinewentity'),
                'is_incident' => 1, 'is_request' => 1, 'is_problem' => 1, 'is_change' => 0,
                'templates' => [
                    [
                        'name' => __('Conta criada ou modificada', 'glpinewentity'),
                        'icon' => '👤',
                        'content' => self::GREETING . "\n\n" . __("A conta foi criada/modificada conforme solicitado.\n\nAcessos concedidos: \n\nAtenciosamente,", 'glpinewentity'),
                    ],
                    [
                        'name' => __('Senha redefinida', 'glpinewentity'),
                        'icon' => '🔑',
                        'content' => self::GREETING . "\n\n" . __("Sua senha foi redefinida. Você receberá as credenciais por um canal separado.\n\nAtenciosamente,", 'glpinewentity'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param int $entities_id
     * @param array $configs Dados vindos do formulário (JSON)
     * @return int Number of solution templates created/reused.
     */
    public function build(int $entities_id, array $configs = []): int
    {
        $count = 0;
        foreach ($configs as $config) {
            $this->getOrCreateTemplate($config, $entities_id);
            $count++;
        }
        return $count;
    }

    private function getOrCreateTemplate(array $config, int $entities_id): int
    {
        $name = trim($config['name'] ?? '');
        $content = trim($config['content'] ?? '');
        
        if (empty($name)) {
            return 0;
        }

        $item = new SolutionTemplate();
        if ($item->getFromDBByCrit(['name' => $name, 'entities_id' => $entities_id])) {
            return (int) $item->getID();
        }

        $sourceId = (int)($config['copy_from'] ?? 0);
        $sourceData = [];
        if ($sourceId > 0 && $item->getFromDB($sourceId)) {
            $sourceData = $item->fields;
        }

        $insertData = [
            'name' => $name,
            'content' => !empty($content) ? $content : ($sourceData['content'] ?? ''),
            'solutiontypes_id' => $sourceData['solutiontypes_id'] ?? 0,
            'entities_id' => $entities_id,
            'is_recursive' => 1,
        ];

        return (int) $item->add($insertData);
    }
}
