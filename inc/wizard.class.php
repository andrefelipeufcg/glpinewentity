<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — inc/wizard.class.php
 * Lógica de negócio: cria Entidade, Admin, Grupos, Técnicos Atendentes e Categorias.
 * -----------------------------------------------------------------------
 */

class PluginGlpinewentityWizard {

    /**
     * Processa a criação de toda a infraestrutura do novo setor.
     *
     * @param array $input Dados vindos do formulário ($_POST)
     * @return array Resumo com IDs criados e eventuais erros
     */
    public static function processCreation(array $input): array {

        $result = [
            'entity_id'      => 0,
            'sub_entities'   => [],
            'admin_user_id'  => 0,
            'admin_login'    => '',
            'groups'         => [],
            'technicians'    => [],
            'categories'     => [],
            'errors'         => [],
        ];

        // ── Sanitização dos inputs ──
        $sectorName   = trim($input['sector_name'] ?? '');
        $sectorAbbr   = trim($input['sector_abbr'] ?? '');
        $parentEntity  = (int)($input['parent_entity'] ?? 0);
        $subEntities   = trim($input['sub_entities'] ?? '');
        $adminEmail    = trim($input['admin_email'] ?? '');
        $subgroupsData = is_array($input['subgroups'] ?? null) ? $input['subgroups'] : [];
        $categoryNames = trim($input['category_names'] ?? '');

        // ── Validação básica ──
        if (empty($sectorName) || empty($sectorAbbr)) {
            $result['errors'][] = 'O nome do setor e a sigla são obrigatórios.';
            return $result;
        }
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $result['errors'][] = 'E-mail do Administrador é obrigatório e deve ser válido.';
            return $result;
        }

        // ── Validação de Subgrupos e Técnicos ──
        $hasAnySubgroup = false;
        foreach ($subgroupsData as $sg) {
            if (!empty(trim($sg['name'] ?? ''))) {
                $hasAnySubgroup = true;
                break;
            }
        }
        
        foreach ($subgroupsData as $index => $sg) {
            $name = trim($sg['name'] ?? '');
            $techs = trim($sg['techs'] ?? '');
            $hasName = !empty($name);
            $hasTechs = !empty($techs);
            
            if ($hasName && !$hasTechs) {
                $result['errors'][] = "O subgrupo '{$name}' foi informado, mas nenhum e-mail de técnico atendente foi preenchido.";
                return $result;
            }
            if (!$hasName && $hasTechs) {
                if ($hasAnySubgroup) {
                    $result['errors'][] = "Não é permitido adicionar técnicos avulsos ao Grupo Pai quando há subgrupos informados. Preencha o nome do subgrupo no Bloco " . ($index + 1) . ".";
                    return $result;
                }
            }
        }

        if (empty($categoryNames)) {
            $result['errors'][] = 'Informe pelo menos uma Categoria de Serviço.';
            return $result;
        }

        // =================================================================
        // PASSO 1 — Criar Entidade Principal
        // =================================================================
        $entityName = strtoupper($sectorAbbr);

        $entity = new Entity();
        $entityId = $entity->add([
            'name'        => $entityName,
            'entities_id' => $parentEntity,
        ]);

        if (!$entityId) {
            $result['errors'][] = "Falha ao criar a entidade '{$entityName}'.";
            return $result;
        }
        $result['entity_id'] = $entityId;

        // Sub-entidades (se houver)
        if (!empty($subEntities)) {
            $subList = array_filter(array_map('trim', explode(',', $subEntities)));
            foreach ($subList as $subName) {
                $subEntity = new Entity();
                $subId = $subEntity->add([
                    'name'        => $subName,
                    'entities_id' => $entityId,
                ]);
                if ($subId) {
                    $result['sub_entities'][] = [
                        'id'   => $subId,
                        'name' => $subName,
                    ];
                } else {
                    $result['errors'][] = "Falha ao criar sub-entidade '{$subName}'.";
                }
            }
        }

        // =================================================================
        // PASSO 2 — Criar/Encontrar Administrador e Atribuir Perfil
        // =================================================================
        $adminUserId = self::findUserByEmail($adminEmail);

        if (!$adminUserId) {
            $result['errors'][] = "O usuário admin '{$adminEmail}' não foi encontrado no GLPI. Cadastre-o antes de prosseguir.";
        } else {
            $result['admin_user_id'] = $adminUserId;
            $result['admin_login']   = $adminEmail;

            // Perfil "Admin" no GLPI padrão tem id = 4
            $profileId = self::getProfileIdByName('Admin');

            $profileUser = new Profile_User();
            $puId = $profileUser->add([
                'users_id'     => $adminUserId,
                'profiles_id'  => $profileId,
                'entities_id'  => $entityId,
                'is_recursive' => 1,
            ]);

            if (!$puId) {
                $result['errors'][] = "Falha ao atribuir perfil Admin ao usuário '{$adminEmail}' na entidade criada.";
            }
        }

        // =================================================================
        // PASSO 3 — Criar Grupo Pai, Subgrupos e Associar Técnicos
        // =================================================================

        // 1. Cria o grupo pai (Obrigatório) usando a sigla
        $parentGroupName = "({$sectorAbbr})";
        $group = new Group();
        $parentGroupId = $group->add([
            'name'        => $parentGroupName,
            'entities_id' => $entityId,
        ]);

        if (!$parentGroupId) {
            $result['errors'][] = "Falha ao criar grupo pai '{$parentGroupName}'.";
        } else {
            $result['groups'][] = [
                'id'   => $parentGroupId,
                'name' => $parentGroupName,
            ];
            
            // 2. Itera sobre os blocos dinâmicos
            foreach ($subgroupsData as $sg) {
                $sgName  = trim($sg['name'] ?? '');
                $sgTechs = trim($sg['techs'] ?? '');
                
                if (empty($sgName) && empty($sgTechs)) {
                    continue; // Bloco vazio, ignora
                }
                
                // Processa técnicos deste bloco
                $techUserIds = [];
                if (!empty($sgTechs)) {
                    $techList = array_filter(array_map('trim', preg_split('/[\n,]+/', $sgTechs)));
                    foreach ($techList as $techEmail) {
                        if (!filter_var($techEmail, FILTER_VALIDATE_EMAIL)) {
                            $result['errors'][] = "E-mail de técnico atendente inválido: '{$techEmail}'. Ignorado.";
                            continue;
                        }
                        $techUserId = self::findUserByEmail($techEmail);
                        if ($techUserId) {
                            $techUserIds[] = $techUserId;
                            $result['technicians'][] = [
                                'id'    => $techUserId,
                                'email' => $techEmail . ($sgName ? " -> {$sgName}" : " -> Pai"),
                            ];
                        } else {
                            $result['errors'][] = "Técnico atendente '{$techEmail}' não encontrado no GLPI. Ignorado.";
                        }
                    }
                }
                
                // Onde alocar os técnicos?
                $targetGroupId = $parentGroupId; // Padrão: Grupo Pai
                
                if (!empty($sgName)) {
                    // Cria o subgrupo e muda o targetGroupId
                    $subg = new Group();
                    $targetGroupId = $subg->add([
                        'name'        => $sgName,
                        'entities_id' => $entityId,
                        'groups_id'   => $parentGroupId,
                    ]);
                    
                    if (!$targetGroupId) {
                        $result['errors'][] = "Falha ao criar subgrupo '{$sgName}'.";
                        continue;
                    }
                    
                    $result['groups'][] = [
                        'id'   => $targetGroupId,
                        'name' => $sgName,
                    ];
                }
                
                // Associa técnicos ao grupo alvo (seja o pai ou o subgrupo)
                foreach ($techUserIds as $tuid) {
                    $groupUser = new Group_User();
                    $groupUser->add([
                        'users_id'  => $tuid,
                        'groups_id' => $targetGroupId,
                    ]);
                }
            }
        }

        // =================================================================
        // PASSO 4 — Criar Categorias ITIL
        // =================================================================
        $catList = array_filter(array_map('trim', preg_split('/[\n]+/', $categoryNames)));

        foreach ($catList as $catName) {
            $category = new ITILCategory();
            $catId = $category->add([
                'name'         => $catName,
                'entities_id'  => $entityId,
                'is_recursive' => 1,
                'is_incident'  => 1,
                'is_request'   => 1,
            ]);

            if ($catId) {
                $result['categories'][] = [
                    'id'   => $catId,
                    'name' => $catName,
                ];
            } else {
                $result['errors'][] = "Falha ao criar categoria '{$catName}'.";
            }
        }

        // =================================================================
        // PASSO 5 — Roteamento e E-mail (stub V2)
        // =================================================================
        // Funcionalidade de RuleTicket e MailCollector será implementada na V2.
        // Reservado para: processRouting($input, $entityId, $result);

        return $result;
    }

    // =====================================================================
    // Métodos auxiliares
    // =====================================================================

    /**
     * Busca um usuário pelo e-mail (em glpi_useremails ou pelo login).
     * Se não existir, retorna false.
     *
     * @param string $email E-mail institucional
     * @return int|false ID do usuário ou false em caso de falha/não encontrado
     */
    private static function findUserByEmail(string $email) {
        global $DB;

        // Primeiro tenta encontrar pelo e-mail na tabela glpi_useremails
        $iterator = $DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_useremails',
            'WHERE'  => ['email' => $email],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) > 0) {
            $row = $iterator->current();
            return (int)$row['users_id'];
        }

        // Tenta encontrar pelo campo name (login) = email
        $user = new User();
        if ($user->getFromDBbyName($email)) {
            return (int)$user->fields['id'];
        }

        // Não encontrou
        return false;
    }

    /**
     * Obtém o ID de um perfil pelo nome.
     * Retorna 4 como fallback (Admin padrão do GLPI).
     *
     * @param string $name Nome do perfil (ex: 'Admin')
     * @return int ID do perfil
     */
    private static function getProfileIdByName(string $name): int {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_profiles',
            'WHERE'  => ['name' => $name],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) > 0) {
            $row = $iterator->current();
            return (int)$row['id'];
        }

        // Fallback: Admin padrão no GLPI = id 4
        return 4;
    }
}
