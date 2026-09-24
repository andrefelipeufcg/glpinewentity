<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — src/Wizard.php
 * Lógica de negócio: cria Entidade, Admin, Grupos, Técnicos Atendentes e Categorias.
 * -----------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpinewentity;

use Entity;
use Profile;
use Profile_User;
use Group;
use Group_User;
use ITILCategory;
use User;

class Wizard {
    
    /**
     * Verifica dinamicamente se um perfil possui poderes de configuração globais (Super-Admin)
     * independentemente do seu ID no banco de dados.
     */
    private static function isSuperAdminProfile(int $profileId): bool
    {
        try {
            // Método nativo do GLPI (disponível a partir do GLPI 10)
            if (method_exists('\Profile', 'getSuperAdminProfilesId')) {
                return in_array($profileId, \Profile::getSuperAdminProfilesId());
            }

            // Fallback dinâmico para versões antigas: verifica direito de config
            global $DB;
            $iter = $DB->request([
                'SELECT' => 'rights',
                'FROM'   => 'glpi_profilerights',
                'WHERE'  => [
                    'profiles_id' => $profileId,
                    'name'        => 'config'
                ]
            ]);
            if ($iter->count() > 0) {
                $row = $iter->current();
                return ($row['rights'] & UPDATE) === UPDATE;
            }
        } catch (\Throwable $e) {
            // Se qualquer chamada falhar, recorre ao ID padrão do GLPI
            return ($profileId == 4);
        }

        return false;
    }

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
            $result['errors'][] = __('O nome do setor e a sigla são obrigatórios.', 'glpinewentity');
            return $result;
        }

        if (!\Session::haveAccessToEntity($parentEntity)) {
            $result['errors'][] = __('Acesso negado à entidade pai escolhida.', 'glpinewentity');
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
            
            $safeName = htmlspecialchars($name, ENT_QUOTES);
            if ($hasName && !$hasTechs) {
                $result['errors'][] = sprintf(__('O subgrupo \'%s\' foi informado, mas nenhum e-mail de técnico atendente foi preenchido.', 'glpinewentity'), $safeName);
                return $result;
            }
            if (!$hasName && $hasTechs) {
                if ($hasAnySubgroup) {
                    $result['errors'][] = sprintf(__('Não é permitido adicionar técnicos avulsos ao Grupo Pai quando há subgrupos informados. Preencha o nome do subgrupo no Bloco %d.', 'glpinewentity'), $index + 1);
                    return $result;
                }
            }
        }

        if (empty($categoryNames)) {
            $result['errors'][] = __('Informe pelo menos uma Categoria de Serviço.', 'glpinewentity');
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
            $safeEntityName = htmlspecialchars($entityName, ENT_QUOTES);
            $result['errors'][] = sprintf(__('Falha ao criar a entidade \'%s\'.', 'glpinewentity'), $safeEntityName);
            return $result;
        }
        $result['entity_id'] = $entityId;

        // Confirma a entidade antes de criar os demais objetos dependentes.
        $createdEntity = new Entity();
        if (!$createdEntity->getFromDB($entityId)) {
            $result['errors'][] = __('A entidade criada não pôde ser recarregada. Nenhuma configuração dependente foi criada.', 'glpinewentity');
            return $result;
        }



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
            if (!\Profile::currentUserHaveMoreRightThan([$assignment['source_profile_id']])) {
                $result['errors'][] = sprintf(__('Sem permissão para clonar o perfil #%d.', 'glpinewentity'), $assignment['source_profile_id']);
                continue;
            }

            // Regra de Negócio: Proíbe explicitamente a clonagem ou atribuição do Super-Admin (ID 4)
            if (self::isSuperAdminProfile($assignment['source_profile_id'])) {
                $result['errors'][] = __('Por motivos de segurança, não é permitido clonar ou atribuir o perfil Super-Admin através deste assistente.', 'glpinewentity');
                continue;
            }

            // Clonar o perfil (criar novo Profile com os mesmos direitos)
            $newProfileId = self::cloneProfile(
                $assignment['source_profile_id'],
                $assignment['new_name']
            );

            if (!$newProfileId) {
                $safeNewName = htmlspecialchars($assignment['new_name'], ENT_QUOTES);
                $result['errors'][] = sprintf(__('Falha ao criar o perfil \'%1$s\' (clone de #%2$d).', 'glpinewentity'), $safeNewName, $assignment['source_profile_id']);
                continue;
            }

            $result['profiles'][] = [
                'id'   => $newProfileId,
                'name' => $assignment['new_name'],
            ];

            // Associar usuários ao NOVO perfil na entidade criada
            $usersList = array_filter(array_map('trim', preg_split('/[\n,]+/', $assignment['users'])));
            $usersList = array_slice($usersList, 0, 100); // Previne exaustão
            foreach ($usersList as $userEmail) {
                if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                    $result['errors'][] = sprintf(__('E-mail de usuário inválido para perfil %s: %s. Ignorado.', 'glpinewentity'), htmlspecialchars($assignment['label'], ENT_QUOTES), htmlspecialchars($userEmail, ENT_QUOTES));
                    continue;
                }
                
                $userId = self::findUserByEmail($userEmail);
                if (!$userId) {
                    $result['errors'][] = sprintf(__('Usuário \'%s\' não encontrado no GLPI. Ignorado.', 'glpinewentity'), htmlspecialchars($userEmail, ENT_QUOTES));
                    continue;
                }

                if (!self::canAssignProfile($userId, $newProfileId, $entityId)) {
                    $result['errors'][] = sprintf(__('Não foi possível atribuir o perfil \'%1$s\' ao usuário \'%2$s\': referência inválida.', 'glpinewentity'), htmlspecialchars($assignment['new_name'], ENT_QUOTES), htmlspecialchars($userEmail, ENT_QUOTES));
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
                    $result['errors'][] = sprintf(__('Falha ao atribuir perfil \'%1$s\' ao usuário \'%2$s\'.', 'glpinewentity'), htmlspecialchars($assignment['new_name'], ENT_QUOTES), htmlspecialchars($userEmail, ENT_QUOTES));
                } else {
                    // Define a nova entidade e o perfil atrelado como os padrões do usuário nas preferências.
                    // Se o usuário passar por várias atribuições em diferentes blocos (Admin, Atendimento, etc.), o último será o padrão definitivo.
                    $userObj = new User();
                    $userObj->update([
                        'id'          => $userId,
                        'entities_id' => $entityId,
                        'profiles_id' => $newProfileId
                    ]);
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
            $result['errors'][] = sprintf(__('Falha ao criar grupo pai \'%s\'.', 'glpinewentity'), $parentGroupName);
        } else {
            $result['groups'][] = [
                'id'   => $parentGroupId,
                'name' => $parentGroupName,
            ];
            
            // Mapeia o índice do subgrupo para o ID do grupo criado no GLPI
            // -1 representa o Grupo Pai (SIGLA)
            $createdGroupsByIndex = ['-1' => $parentGroupId];
            
            // 2. Itera sobre os blocos dinâmicos
            foreach ($subgroupsData as $index => $sg) {
                $sgName  = trim($sg['name'] ?? '');
                $sgTechs = trim($sg['techs'] ?? '');
                $sgParentIndex = isset($sg['parent']) ? trim($sg['parent']) : '-1';
                
                if (empty($sgName) && empty($sgTechs)) {
                    continue; // Bloco vazio, ignora
                }
                
                // Processa técnicos deste bloco
                $techUserIds = [];
                if (!empty($sgTechs)) {
                    $techList = array_filter(array_map('trim', preg_split('/[\n,]+/', $sgTechs)));
                    $techList = array_slice($techList, 0, 100); // Previne exaustão
                    foreach ($techList as $techEmail) {
                        if (!filter_var($techEmail, FILTER_VALIDATE_EMAIL)) {
                            $result['errors'][] = sprintf(__('E-mail de técnico atendente inválido: %s. Ignorado.', 'glpinewentity'), htmlspecialchars($techEmail, ENT_QUOTES));
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
                            $result['errors'][] = sprintf(__('Técnico atendente \'%s\' não encontrado no GLPI. Ignorado.', 'glpinewentity'), htmlspecialchars($techEmail, ENT_QUOTES));
                        }
                    }
                }
                
                // Onde alocar os técnicos?
                // Define o parentGroupId para este subgrupo baseado na escolha do usuário
                $currentParentGroupId = $parentGroupId;
                if (isset($createdGroupsByIndex[$sgParentIndex])) {
                    $currentParentGroupId = $createdGroupsByIndex[$sgParentIndex];
                }
                
                $targetGroupId = $currentParentGroupId; // Padrão se não houver nome de subgrupo
                
                if (!empty($sgName)) {
                    // Cria o subgrupo e muda o targetGroupId
                    $subg = new Group();
                    $targetGroupId = $subg->add([
                        'name'        => $sgName,
                        'entities_id' => $entityId,
                        'groups_id'   => $currentParentGroupId,
                    ]);
                    
                    if (!$targetGroupId) {
                        $safeSgName = htmlspecialchars($sgName, ENT_QUOTES);
                        $result['errors'][] = sprintf(__('Falha ao criar subgrupo \'%s\'.', 'glpinewentity'), $safeSgName);
                        continue;
                    }
                    
                    $result['groups'][] = [
                        'id'   => $targetGroupId,
                        'name' => $sgName,
                    ];
                    
                    // Armazena o ID deste subgrupo criado
                    $createdGroupsByIndex[(string)$index] = $targetGroupId;
                } else {
                    // Se não tiver nome, significa que é o bloco 0 que envia técnicos pro pai
                    $createdGroupsByIndex[(string)$index] = $currentParentGroupId;
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
                $safeCleanName = htmlspecialchars($cleanName, ENT_QUOTES);
                $result['errors'][] = sprintf(__('Falha ao criar categoria \'%s\'.', 'glpinewentity'), $safeCleanName);
            }
        }

        return $result;
    }

    // =====================================================================
    // Métodos auxiliares
    // =====================================================================

    public static function processUpdate(array $input, array $existingFields): array {
        $result = json_decode($existingFields['metadata'] ?? '{}', true) ?: [];
        // Limpa erros antigos carregados do metadata
        $result['errors'] = [];
        
        $sectorName   = trim($input['sector_name'] ?? '');
        $sectorAbbr   = trim($input['sector_abbr'] ?? '');
        $parentEntity = (int)($input['parent_entity'] ?? 0);
        $categoryNames = trim($input['category_names'] ?? '');
        
        if (empty($sectorName) || empty($sectorAbbr)) {
            $result['errors'][] = __('O nome do setor e a sigla são obrigatórios.', 'glpinewentity');
            return $result;
        }

        if (empty($categoryNames)) {
            $result['errors'][] = __('Informe pelo menos uma Categoria de Serviço.', 'glpinewentity');
            return $result;
        }
        
        if (!\Session::haveAccessToEntity($parentEntity)) {
            $result['errors'][] = __('Acesso negado à entidade pai escolhida.', 'glpinewentity');
            return $result;
        }
        
        $entityId = $result['entity_id'] ?? 0;

        // =================================================================
        // Atualizar Entidade
        // =================================================================
        if ($entityId <= 0) {
            $result['errors'][] = __('A infraestrutura não possui uma entidade gerenciada vinculada.', 'glpinewentity');
            return $result;
        }

        if (!\Session::haveAccessToEntity($entityId)) {
            $result['errors'][] = __('Acesso negado à entidade gerenciada por este setor.', 'glpinewentity');
            return $result;
        }

        $entity = new Entity();
        if (!$entity->getFromDB($entityId)) {
            $result['errors'][] = __('A entidade gerenciada não foi encontrada. A edição foi cancelada para evitar vínculos inconsistentes.', 'glpinewentity');
            return $result;
        }

        if ($entityId > 0) {
            global $DB;
            $entityName = strtoupper($sectorAbbr);
            $duplicateEntity = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_entities',
                'WHERE'  => [
                    'name'        => $entityName,
                    'entities_id' => $parentEntity,
                    'id'          => ['<>', $entityId],
                ],
                'LIMIT' => 1,
            ]);

            if (count($duplicateEntity) > 0) {
                $result['errors'][] = sprintf(
                    __('Não foi possível alterar a entidade pai: já existe uma entidade chamada "%s" nesse nível.', 'glpinewentity'),
                    $entityName
                );
                return $result;
            }

            $entity->update([
                'id' => $entityId,
                'name' => $entityName,
                'entities_id' => $parentEntity
            ]);
        }
        
        // =================================================================
        // Atualizar Grupo Pai
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
        // Sincronizar Perfis
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

            // Checagem de segurança (Impede escalar privilégios via edição)
            if (!\Profile::currentUserHaveMoreRightThan([$assignment['source_profile_id']])) {
                $result['errors'][] = sprintf(__('Sem permissão para clonar o perfil #%d.', 'glpinewentity'), $assignment['source_profile_id']);
                continue;
            }
            
            // Regra de Negócio: Proíbe explicitamente a clonagem ou atribuição do Super-Admin (ID 4)
            if (self::isSuperAdminProfile($assignment['source_profile_id'])) {
                $result['errors'][] = __('Por motivos de segurança, não é permitido clonar ou atribuir o perfil Super-Admin através deste assistente.', 'glpinewentity');
                continue;
            }
            
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
                
                // Checagem de segurança: O usuário não pode se apropriar de um perfil existente 
                // mais alto que ele (ex: submeter o nome 'Super-Admin' maliciosamente)
                if (!\Profile::currentUserHaveMoreRightThan([$profileId])) {
                    $result['errors'][] = sprintf(__('Sem permissão para reutilizar e atribuir o perfil \'%s\'.', 'glpinewentity'), htmlspecialchars($newName, ENT_QUOTES));
                    continue;
                }
                
                // Regra de Negócio extra: Garante que o perfil reutilizado também não seja Super-Admin
                if (self::isSuperAdminProfile($profileId)) {
                    $result['errors'][] = sprintf(__('O perfil \'%s\' é Super-Admin e não pode ser atribuído por este assistente.', 'glpinewentity'), htmlspecialchars($newName, ENT_QUOTES));
                    continue;
                }
            } else {
                // Criar perfil novo (clonar do fonte)
                $profileId = self::cloneProfile(
                    $assignment['source_profile_id'],
                    $newName
                );
                if (!$profileId) {
                    $result['errors'][] = sprintf(__('Falha ao criar o perfil \'%s\'.', 'glpinewentity'), htmlspecialchars($newName, ENT_QUOTES));
                    continue;
                }
            }

            $result['profiles'][] = [
                'id'   => $profileId,
                'name' => $newName,
            ];

            // Separa e-mails válidos para não descartar os demais por um erro isolado.
            $usersToAssign = [];
            if (!empty($assignment['users'])) {
                $usersList = array_filter(array_map('trim', preg_split('/[\n,]+/', $assignment['users'])));
                $usersList = array_slice($usersList, 0, 100); // Previne exaustão
                foreach ($usersList as $userEmail) {
                    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                        $result['errors'][] = sprintf(__('E-mail de usuário inválido para perfil %s: %s.', 'glpinewentity'), htmlspecialchars($assignment['label'], ENT_QUOTES), htmlspecialchars($userEmail, ENT_QUOTES));
                        continue;
                    }

                    $userId = self::findUserByEmail($userEmail);
                    if (!$userId || !self::canAssignProfile($userId, $profileId, $entityId)) {
                        $result['errors'][] = sprintf(__('Não foi possível atribuir o perfil \'%1$s\' ao usuário \'%2$s\': referência inválida.', 'glpinewentity'), htmlspecialchars($assignment['new_name'], ENT_QUOTES), htmlspecialchars($userEmail, ENT_QUOTES));
                        continue;
                    }

                    $usersToAssign[] = [
                        'id' => $userId,
                        'email' => $userEmail,
                    ];
                }
            }

            // Sincronizar usuários: remover os antigos da entidade e adicionar os novos.
            global $DB;
            $iterator = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_profiles_users',
                'WHERE'  => [
                    'profiles_id' => $profileId,
                    'entities_id' => $entityId
                ]
            ]);
            $profileUser = new Profile_User();
            foreach ($iterator as $row) {
                $profileUser->delete(['id' => $row['id']]);
            }

            foreach ($usersToAssign as $userToAssign) {
                $profileUser = new Profile_User();
                $puId = $profileUser->add([
                    'users_id'     => $userToAssign['id'],
                    'profiles_id'  => $profileId,
                    'entities_id'  => $entityId,
                    'is_recursive' => 1,
                ]);
                if (!$puId) {
                    $result['errors'][] = sprintf(__('Falha ao atribuir perfil \'%1$s\' ao usuário \'%2$s\'.', 'glpinewentity'), htmlspecialchars($assignment['new_name'], ENT_QUOTES), htmlspecialchars($userToAssign['email'], ENT_QUOTES));
                } else {
                    // Define a nova entidade e o perfil atrelado como os padrões do usuário nas preferências.
                    // Se o usuário passar por várias atribuições em diferentes blocos (Admin, Atendimento, etc.), o último será o padrão definitivo.
                    $userObj = new User();
                    $userObj->update([
                        'id'          => $userToAssign['id'],
                        'entities_id' => $entityId,
                        'profiles_id' => $profileId
                    ]);
                }
            }
        }

        // =================================================================
        // Sincronizar Subgrupos e Técnicos
        // =================================================================
        $subgroupsData = is_array($input['subgroups'] ?? null) ? $input['subgroups'] : [];
        
        if ($parentGroupId > 0) {
            // Buscar todos os subgrupos atuais na entidade
            $currentSubgroups = [];
            $sgIter = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_groups',
                'WHERE'  => ['entities_id' => $entityId]
            ]);
            foreach ($sgIter as $row) {
                if ($row['id'] != $parentGroupId) {
                    $currentSubgroups[$row['name']][] = $row['id'];
                }
            }

            $sectorAbbr = $input['sector_abbr'] ?? '';
            $result['groups'] = [['id' => $parentGroupId, 'name' => "({$sectorAbbr})"]];
            $result['technicians'] = [];

            // Mapeia o índice do subgrupo para o ID do grupo criado no GLPI
            $createdGroupsByIndex = ['-1' => $parentGroupId];

            foreach ($subgroupsData as $index => $sg) {
                $sgName  = trim($sg['name'] ?? '');
                $sgTechs = trim($sg['techs'] ?? '');
                $sgParentIndex = isset($sg['parent']) ? trim($sg['parent']) : '-1';

                if (empty($sgName) && empty($sgTechs)) continue;

                // Definir o pai correto a partir do mapeamento
                $mappedParentId = $parentGroupId;
                if (isset($createdGroupsByIndex[$sgParentIndex])) {
                    $mappedParentId = $createdGroupsByIndex[$sgParentIndex];
                }

                $targetGroupId = $parentGroupId; // Padrão: Grupo Pai

                if (!empty($sgName)) {
                    if (!empty($currentSubgroups[$sgName])) {
                        // Consome um dos IDs disponíveis (para resolver duplicatas)
                        $targetGroupId = array_shift($currentSubgroups[$sgName]);
                        
                        // Atualiza o pai caso tenha mudado
                        $subg = new Group();
                        $subg->update([
                            'id'        => $targetGroupId,
                            'groups_id' => $mappedParentId
                        ]);
                    } else {
                        // Criar subgrupo novo
                        $subg = new Group();
                        $targetGroupId = $subg->add([
                            'name'        => $sgName,
                            'entities_id' => $entityId,
                            'groups_id'   => $mappedParentId,
                        ]);
                        if (!$targetGroupId) {
                            $result['errors'][] = sprintf(__('Falha ao criar subgrupo \'%s\'.', 'glpinewentity'), $sgName);
                            continue;
                        }
                    }
                    $createdGroupsByIndex[(string)$index] = $targetGroupId;
                    $result['groups'][] = ['id' => $targetGroupId, 'name' => $sgName];
                }

                // Sincronizar técnicos: remover os atuais do grupo e adicionar os novos
                $guIter = $DB->request([
                    'SELECT' => 'id',
                    'FROM'   => 'glpi_groups_users',
                    'WHERE'  => ['groups_id' => $targetGroupId]
                ]);
                $groupUser = new Group_User();
                foreach ($guIter as $row) {
                    $groupUser->delete(['id' => $row['id']]);
                }

                if (!empty($sgTechs)) {
                    $techList = array_filter(array_map('trim', preg_split('/[\n,]+/', $sgTechs)));
                    $techList = array_slice($techList, 0, 100); // Previne exaustão
                    foreach ($techList as $techEmail) {
                        if (!filter_var($techEmail, FILTER_VALIDATE_EMAIL)) {
                            $result['errors'][] = sprintf(__('E-mail de técnico inválido: %s. Ignorado.', 'glpinewentity'), htmlspecialchars($techEmail, ENT_QUOTES));
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
                                $result['errors'][] = sprintf(__('Falha ao associar técnico \'%s\' ao subgrupo.', 'glpinewentity'), htmlspecialchars($techEmail, ENT_QUOTES));
                            } else {
                                $result['technicians'][] = [
                                    'id'    => $techUserId,
                                    'email' => $techEmail . ($sgName ? " -> {$sgName}" : " -> Pai"),
                                ];
                            }
                        } else {
                            $result['errors'][] = sprintf(__('Técnico \'%s\' não encontrado no GLPI. Ignorado.', 'glpinewentity'), htmlspecialchars($techEmail, ENT_QUOTES));
                        }
                    }
                }
            }

            // Inativamos os subgrupos órfãos (para não aparecerem mais no plugin nem em novas atribuições)
            // sem deletá-los, preservando o histórico de chamados.
            $groupObj = new \Group();
            foreach ($currentSubgroups as $sgId) {
                $groupObj->update([
                    'id'           => $sgId,
                    'is_assign'    => 0,
                    'is_requester' => 0,
                    'is_watcher'   => 0
                ]);
            }
        }

        // =================================================================
        // Sincronizar Categorias ITIL
        // =================================================================
        $currentCategories = [];
        $categoryIterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_itilcategories',
            'WHERE'  => ['entities_id' => $entityId],
        ]);
        foreach ($categoryIterator as $categoryRow) {
            $currentCategories[$categoryRow['name']][] = $categoryRow['id'];
        }

        $result['categories'] = [];
        $lastIdAtDepth = [];
        $catList = array_filter(array_map('trim', preg_split('/[\n]+/', $categoryNames)));
        foreach ($catList as $line) {
            preg_match('/^-+/', $line, $matches);
            $hyphensCount = !empty($matches[0]) ? strlen($matches[0]) : 0;
            $cleanName = trim(substr($line, $hyphensCount));
            if (empty($cleanName)) {
                continue;
            }

            $parentId = 0;
            for ($depth = $hyphensCount - 1; $depth >= 0; $depth--) {
                if (isset($lastIdAtDepth[$depth])) {
                    $parentId = $lastIdAtDepth[$depth];
                    break;
                }
            }

            $categoryId = 0;
            if (!empty($currentCategories[$cleanName])) {
                // Reutiliza a categoria existente
                $categoryId = array_shift($currentCategories[$cleanName]);
                
                // Atualiza o pai caso tenha mudado
                $category = new ITILCategory();
                if (!$category->update([
                    'id'                => $categoryId,
                    'itilcategories_id' => $parentId
                ])) {
                    $result['errors'][] = sprintf(__('Falha ao atualizar categoria \'%s\'.', 'glpinewentity'), htmlspecialchars($cleanName, ENT_QUOTES));
                    continue;
                }
            } else {
                $category = new ITILCategory();
                $categoryId = $category->add([
                    'name'              => $cleanName,
                    'entities_id'       => $entityId,
                    'itilcategories_id' => $parentId,
                    'is_recursive'      => 1,
                    'is_incident'       => 1,
                    'is_request'        => 1,
                ]);
                if (!$categoryId) {
                    $result['errors'][] = sprintf(__('Falha ao criar categoria \'%s\'.', 'glpinewentity'), htmlspecialchars($cleanName, ENT_QUOTES));
                    continue;
                }
            }

            $lastIdAtDepth[$hyphensCount] = $categoryId;
            foreach (array_keys($lastIdAtDepth) as $depth) {
                if ($depth > $hyphensCount) {
                    unset($lastIdAtDepth[$depth]);
                }
            }
            $result['categories'][] = [
                'id'   => $categoryId,
                'name' => $cleanName,
            ];
        }

        // Inativar as categorias órfãs (não deletamos para manter histórico)
        $catObj = new \ITILCategory();
        foreach ($currentCategories as $name => $ids) {
            foreach ($ids as $cId) {
                $catObj->update([
                    'id'                 => $cId,
                    'is_helpdeskvisible' => 0,
                    'is_incident'        => 0,
                    'is_request'         => 0
                ]);
            }
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
     * Confirma as referências usadas pelo GLPI antes de criar uma autorização.
     */
    private static function canAssignProfile(int $userId, int $profileId, int $entityId): bool {
        return User::getById($userId) instanceof User
            && Profile::getById($profileId) instanceof Profile
            && Entity::getById($entityId) instanceof Entity;
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

        // Remove false-positive validation errors from GLPI 11 when cloning profiles
        if (defined('ERROR') && isset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR])) {
            foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR] as $k => $msg) {
                if (stripos($msg, 'Perfis') !== false || stripos($msg, 'Profile') !== false || stripos($msg, 'valor incorreto') !== false) {
                    unset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR][$k]);
                }
            }
            if (empty($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR])) {
                unset($_SESSION['MESSAGE_AFTER_REDIRECT'][ERROR]);
            }
        }

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
