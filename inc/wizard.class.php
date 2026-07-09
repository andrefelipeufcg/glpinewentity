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

        $subgroupsData = is_array($input['subgroups'] ?? null) ? $input['subgroups'] : [];
        $categoryNames = trim($input['category_names'] ?? '');

        // ── Validação básica ──
        if (empty($sectorName) || empty($sectorAbbr)) {
            $result['errors'][] = 'O nome do setor e a sigla são obrigatórios.';
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



        // =================================================================
        // PASSO 2 — Criar Perfis (clone) e Atribuir Usuários
        // =================================================================
        $profileAssignments = [];
        $profileNames = $input['profiles_default'] ?? [];
        
        // Admin
        if (!empty($input['copy_profile_admin']) && $input['copy_profile_admin'] > 0 && !empty(trim($input['users_profile_admin'] ?? ''))) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_admin'],
                'new_name'          => trim($profileNames[0] ?? ($sectorAbbr . ' - Admin')),
                'users'             => trim($input['users_profile_admin']),
                'label'             => 'Admin'
            ];
        }
        // Atendimento
        if (!empty($input['copy_profile_support']) && $input['copy_profile_support'] > 0 && !empty(trim($input['users_profile_support'] ?? ''))) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_support'],
                'new_name'          => trim($profileNames[1] ?? ($sectorAbbr . ' - Atendimento')),
                'users'             => trim($input['users_profile_support']),
                'label'             => 'Atendimento'
            ];
        }
        // Transferência
        if (!empty($input['copy_profile_transfer']) && $input['copy_profile_transfer'] > 0 && !empty(trim($input['users_profile_transfer'] ?? ''))) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_transfer'],
                'new_name'          => trim($profileNames[2] ?? ($sectorAbbr . ' - Transferência de Chamados')),
                'users'             => trim($input['users_profile_transfer']),
                'label'             => 'Transferência de Chamados'
            ];
        }
        // Customizados
        if (!empty($input['copy_profile_custom']) && is_array($input['copy_profile_custom'])) {
            foreach ($input['copy_profile_custom'] as $idx => $pId) {
                if ($pId > 0 && !empty(trim($input['users_profile_custom'][$idx] ?? ''))) {
                    $customName = trim($input['name_profile_custom'][$idx] ?? ('Customizado ' . ($idx + 1)));
                    $profileAssignments[] = [
                        'source_profile_id' => (int)$pId,
                        'new_name'          => $customName,
                        'users'             => trim($input['users_profile_custom'][$idx]),
                        'label'             => 'Customizado ' . ($idx + 1)
                    ];
                }
            }
        }

        $result['profiles'] = [];

        foreach ($profileAssignments as $assignment) {
            // 1. Clonar o perfil (criar novo Profile com os mesmos direitos)
            $newProfileId = self::cloneProfile(
                $assignment['source_profile_id'],
                $assignment['new_name']
            );

            if (!$newProfileId) {
                $result['errors'][] = "Falha ao criar o perfil '{$assignment['new_name']}' (clone de #{$assignment['source_profile_id']}).";
                continue;
            }

            $result['profiles'][] = [
                'id'   => $newProfileId,
                'name' => $assignment['new_name'],
            ];

            // 2. Associar usuários ao NOVO perfil na entidade criada
            $usersList = array_filter(array_map('trim', preg_split('/[\n,]+/', $assignment['users'])));
            foreach ($usersList as $userEmail) {
                if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $result['errors'][] = "E-mail de usuário inválido para perfil '{$assignment['label']}': '{$userEmail}'. Ignorado.";
                    continue;
                }
                
                $userId = self::findUserByEmail($userEmail);
                if (!$userId) {
                    $result['errors'][] = "Usuário '{$userEmail}' não encontrado no GLPI. Ignorado.";
                    continue;
                }
                
                $profileUser = new Profile_User();
                $puId = $profileUser->add([
                    'users_id'     => $userId,
                    'profiles_id'  => $newProfileId,
                    'entities_id'  => $entityId,
                    'is_recursive' => 1,
                ]);

                if (!$puId) {
                    $result['errors'][] = "Falha ao atribuir perfil '{$assignment['new_name']}' ao usuário '{$userEmail}'.";
                }
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
        $lastIdAtDepth = [];

        foreach ($catList as $line) {
            // Conta hífens no início da linha
            preg_match('/^-+/', $line, $matches);
            $hyphensCount = !empty($matches[0]) ? strlen($matches[0]) : 0;
            
            // Remove hífens e espaços do início
            $cleanName = trim(substr($line, $hyphensCount));
            
            if (empty($cleanName)) {
                continue;
            }

            // Descobre o parentId verificando os níveis acima
            $parentId = 0;
            for ($d = $hyphensCount - 1; $d >= 0; $d--) {
                if (isset($lastIdAtDepth[$d])) {
                    $parentId = $lastIdAtDepth[$d];
                    break;
                }
            }

            $category = new ITILCategory();
            $catId = $category->add([
                'name'              => $cleanName,
                'entities_id'       => $entityId,
                'itilcategories_id' => $parentId,
                'is_recursive'      => 1,
                'is_incident'       => 1,
                'is_request'        => 1,
            ]);

            if ($catId) {
                // Atualiza o ID mais recente para a profundidade atual
                $lastIdAtDepth[$hyphensCount] = $catId;
                
                // Limpa os IDs das profundidades maiores, pois agora estamos em um novo galho
                foreach (array_keys($lastIdAtDepth) as $d) {
                    if ($d > $hyphensCount) {
                        unset($lastIdAtDepth[$d]);
                    }
                }
                
                $result['categories'][] = [
                    'id'   => $catId,
                    'name' => $cleanName,
                ];
            } else {
                $result['errors'][] = "Falha ao criar categoria '{$cleanName}'.";
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

    public static function processUpdate(array $input, array $existingFields): array {
        $result = json_decode($existingFields['metadata'] ?? '{}', true) ?: [];
        if (!isset($result['errors'])) $result['errors'] = [];
        
        $sectorName   = trim($input['sector_name'] ?? '');
        $sectorAbbr   = trim($input['sector_abbr'] ?? '');
        $parentEntity = (int)($input['parent_entity'] ?? 0);
        
        if (empty($sectorName) || empty($sectorAbbr)) {
            $result['errors'][] = 'O nome do setor e a sigla são obrigatórios.';
            return $result;
        }
        
        $entityId = $result['entity_id'] ?? 0;

        // =================================================================
        // 1. Atualizar Entidade
        // =================================================================
        if ($entityId > 0) {
            $entity = new Entity();
            if ($entity->getFromDB($entityId)) {
                $entity->update([
                    'id' => $entityId,
                    'name' => strtoupper($sectorAbbr),
                    'entities_id' => $parentEntity
                ]);
            }
        }
        
        // =================================================================
        // 2. Atualizar Grupo Pai
        // =================================================================
        global $DB;
        $parentGroupId = 0;
        
        // Buscar grupo pai na entidade
        $pgIter = $DB->request([
            'SELECT' => ['id', 'groups_id'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => ['entities_id' => $entityId]
        ]);
        foreach ($pgIter as $row) {
            if (empty($row['groups_id'])) {
                $parentGroupId = $row['id'];
                break;
            }
        }
        
        if ($parentGroupId > 0) {
            $group = new Group();
            if ($group->getFromDB($parentGroupId)) {
                $group->update([
                    'id' => $parentGroupId,
                    'name' => "({$sectorAbbr})"
                ]);
            }
        }

        // =================================================================
        // 3. Sincronizar Perfis
        // =================================================================
        $profileNames = $input['profiles_default'] ?? [];
        $profileAssignments = [];

        // Admin
        if (!empty($input['copy_profile_admin']) && $input['copy_profile_admin'] > 0) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_admin'],
                'new_name'          => trim($profileNames[0] ?? ($sectorAbbr . ' - Admin')),
                'users'             => trim($input['users_profile_admin'] ?? ''),
                'label'             => 'Admin'
            ];
        }
        // Atendimento
        if (!empty($input['copy_profile_support']) && $input['copy_profile_support'] > 0) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_support'],
                'new_name'          => trim($profileNames[1] ?? ($sectorAbbr . ' - Atendimento')),
                'users'             => trim($input['users_profile_support'] ?? ''),
                'label'             => 'Atendimento'
            ];
        }
        // Transferência
        if (!empty($input['copy_profile_transfer']) && $input['copy_profile_transfer'] > 0) {
            $profileAssignments[] = [
                'source_profile_id' => (int)$input['copy_profile_transfer'],
                'new_name'          => trim($profileNames[2] ?? ($sectorAbbr . ' - Transferência de Chamados')),
                'users'             => trim($input['users_profile_transfer'] ?? ''),
                'label'             => 'Transferência de Chamados'
            ];
        }
        // Customizados
        if (!empty($input['copy_profile_custom']) && is_array($input['copy_profile_custom'])) {
            foreach ($input['copy_profile_custom'] as $idx => $pId) {
                if ($pId > 0) {
                    $customName = trim($input['name_profile_custom'][$idx] ?? ('Customizado ' . ($idx + 1)));
                    $profileAssignments[] = [
                        'source_profile_id' => (int)$pId,
                        'new_name'          => $customName,
                        'users'             => trim($input['users_profile_custom'][$idx] ?? ''),
                        'label'             => 'Customizado ' . ($idx + 1)
                    ];
                }
            }
        }

        // Processar cada perfil
        $result['profiles'] = [];
        foreach ($profileAssignments as $assignment) {
            $newName = $assignment['new_name'];
            
            // Verificar se o perfil já existe por nome
            $existingProfile = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_profiles',
                'WHERE'  => ['name' => $newName],
                'LIMIT'  => 1
            ]);
            
            $profileId = 0;
            if (count($existingProfile) > 0) {
                $row = $existingProfile->current();
                $profileId = (int)$row['id'];
            } else {
                // Criar perfil novo (clonar do fonte)
                $profileId = self::cloneProfile(
                    $assignment['source_profile_id'],
                    $newName
                );
                if (!$profileId) {
                    $result['errors'][] = "Falha ao criar o perfil '{$newName}'.";
                    continue;
                }
            }

            $result['profiles'][] = [
                'id'   => $profileId,
                'name' => $newName,
            ];

            // Sincronizar usuários: remover os antigos da entidade e adicionar os novos
            // Remover vinculações existentes deste perfil nesta entidade
            $DB->delete('glpi_profiles_users', [
                'profiles_id' => $profileId,
                'entities_id' => $entityId
            ]);

            // Adicionar os novos
            if (!empty($assignment['users'])) {
                $usersList = array_filter(array_map('trim', preg_split('/[\n,]+/', $assignment['users'])));
                foreach ($usersList as $userEmail) {
                    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) continue;
                    $userId = self::findUserByEmail($userEmail);
                    if (!$userId) {
                        $result['errors'][] = "Usuário '{$userEmail}' não encontrado no GLPI. Ignorado.";
                        continue;
                    }
                    $profileUser = new Profile_User();
                    $puId = $profileUser->add([
                        'users_id'     => $userId,
                        'profiles_id'  => $profileId,
                        'entities_id'  => $entityId,
                        'is_recursive' => 1,
                    ]);
                    if (!$puId) {
                        $result['errors'][] = "Falha ao atribuir perfil '{$assignment['new_name']}' ao usuário '{$userEmail}'.";
                    }
                }
            }
        }

        // =================================================================
        // 4. Sincronizar Subgrupos e Técnicos
        // =================================================================
        $subgroupsData = is_array($input['subgroups'] ?? null) ? $input['subgroups'] : [];
        
        if ($parentGroupId > 0) {
            // Buscar subgrupos atuais
            $currentSubgroups = [];
            $sgIter = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_groups',
                'WHERE'  => ['groups_id' => $parentGroupId]
            ]);
            foreach ($sgIter as $row) {
                $currentSubgroups[$row['name']] = $row['id'];
            }

            $result['groups'] = [['id' => $parentGroupId, 'name' => "({$sectorAbbr})"]];
            $result['technicians'] = [];

            foreach ($subgroupsData as $sg) {
                $sgName  = trim($sg['name'] ?? '');
                $sgTechs = trim($sg['techs'] ?? '');

                if (empty($sgName) && empty($sgTechs)) continue;

                // Definir o grupo-alvo
                $targetGroupId = $parentGroupId; // Padrão: Grupo Pai

                if (!empty($sgName)) {
                    if (isset($currentSubgroups[$sgName])) {
                        $targetGroupId = $currentSubgroups[$sgName];
                        unset($currentSubgroups[$sgName]); // Marca como processado
                    } else {
                        // Criar subgrupo novo
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
                    }
                    $result['groups'][] = ['id' => $targetGroupId, 'name' => $sgName];
                }

                // Sincronizar técnicos: remover os atuais do grupo e adicionar os novos
                $DB->delete('glpi_groups_users', ['groups_id' => $targetGroupId]);

                if (!empty($sgTechs)) {
                    $techList = array_filter(array_map('trim', preg_split('/[\n,]+/', $sgTechs)));
                    foreach ($techList as $techEmail) {
                        if (!filter_var($techEmail, FILTER_VALIDATE_EMAIL)) {
                            $result['errors'][] = "E-mail de técnico inválido: '{$techEmail}'. Ignorado.";
                            continue;
                        }
                        $techUserId = self::findUserByEmail($techEmail);
                        if ($techUserId) {
                            $groupUser = new Group_User();
                            $guId = $groupUser->add([
                                'users_id'  => $techUserId,
                                'groups_id' => $targetGroupId,
                            ]);
                            if (!$guId) {
                                $result['errors'][] = "Falha ao associar técnico '{$techEmail}' ao subgrupo.";
                            } else {
                                $result['technicians'][] = [
                                    'id'    => $techUserId,
                                    'email' => $techEmail . ($sgName ? " -> {$sgName}" : " -> Pai"),
                                ];
                            }
                        } else {
                            $result['errors'][] = "Técnico '{$techEmail}' não encontrado no GLPI. Ignorado.";
                        }
                    }
                }
            }

            // Subgrupos que sobraram (não estão mais no form) — deixar intactos
            // (não apagamos para não perder dados acidentalmente)
        }

        return $result;
    }

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

    /**
     * Clona um perfil existente: cria um novo Profile com o nome informado
     * e copia todos os direitos (ProfileRight) do perfil-fonte.
     *
     * @param int    $sourceProfileId ID do perfil a ser clonado
     * @param string $newName         Nome do novo perfil
     * @return int|false ID do novo perfil criado, ou false em caso de falha
     */
    private static function cloneProfile(int $sourceProfileId, string $newName) {
        global $DB;

        // Carrega o perfil-fonte
        $sourceProfile = new Profile();
        if (!$sourceProfile->getFromDB($sourceProfileId)) {
            return false;
        }

        // Cria o novo perfil copiando os campos do fonte
        $newProfileData = $sourceProfile->fields;
        unset($newProfileData['id']);
        unset($newProfileData['date_mod']);
        unset($newProfileData['date_creation']);
        $newProfileData['name'] = $newName;
        
        // Decodifica campos serializados para array (GLPI 11 prepareInputForAdd exige array nestes campos)
        $arrayFields = ['helpdesk_item_type', 'managed_domainrecordtypes', 'ticket_status', 'problem_status', 'change_status'];
        foreach ($arrayFields as $f) {
            if (isset($newProfileData[$f]) && is_string($newProfileData[$f])) {
                $newProfileData[$f] = importArrayFromDB($newProfileData[$f]);
            }
        }

        $newProfile = new Profile();
        $newProfileId = $newProfile->add($newProfileData);

        if (!$newProfileId) {
            return false;
        }

        // Copia todos os direitos (ProfileRight) do perfil-fonte para o novo
        $rightsIterator = $DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => $sourceProfileId]
        ]);

        foreach ($rightsIterator as $right) {
            $DB->updateOrInsert('glpi_profilerights', [
                'profiles_id' => $newProfileId,
                'name'        => $right['name'],
                'rights'      => $right['rights'],
            ], [
                'profiles_id' => $newProfileId,
                'name'        => $right['name'],
            ]);
        }

        return $newProfileId;
    }
}
