<?php
/**
 * -----------------------------------------------------------------------
 * GLPI New Entity — front/sector.form.php
 * Formulário para criação e edição de infraestrutura de novo setor.
 * -----------------------------------------------------------------------
 */

include("../../../inc/includes.php");

// Permissão: somente Super-Admin (ou quem possa gerenciar entidades)
Session::checkRight("entity", CREATE);

// -----------------------------------------------------------------------
// POST: Processar criação
// -----------------------------------------------------------------------
$showResult = false;
$result     = [];
$isEdit     = false;
$sectorId   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sectorObj  = new PluginGlpinewentitySector();

if ($sectorId > 0) {
    if ($sectorObj->getFromDB($sectorId)) {
        $isEdit = true;
    } else {
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
    }
}

if (isset($_POST['process_wizard'])) {
    // No GLPI 11+, o CheckCsrfListener já valida e consome o token globalmente.
    // Chamar checkCSRF novamente falha porque o token já foi consumido.
    // Em versões antigas (ex: 9.5), precisamos checar manualmente.
    if (!class_exists('Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener')) {
        Session::checkCSRF($_POST);
    }

    if ($isEdit) {
        $result = PluginGlpinewentityWizard::processUpdate($_POST, $sectorObj->fields);
        // Atualiza metadata independentemente de ter erro, pois os dados no banco já foram alterados
        $sectorObj->update([
            'id' => $sectorId,
            'sector_name' => $_POST['sector_name'],
            'sector_abbr' => $_POST['sector_abbr'],
            'metadata' => json_encode($result)
        ]);
        
        if (empty($result['errors'])) {
            Session::addMessageAfterRedirect('Infraestrutura atualizada com sucesso!', true, INFO);
        } else {
            foreach ($result['errors'] as $err) {
                Session::addMessageAfterRedirect($err, false, ERROR);
            }
        }
        global $CFG_GLPI;
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php?id=' . $sectorId);
    } else {
        $result = PluginGlpinewentityWizard::processCreation($_POST);
        
        if (empty($result['errors']) && !empty($result['entity_id'])) {
            $sectorObj->add([
                'entities_id' => (int)$_POST['parent_entity'],
                'sector_name' => $_POST['sector_name'],
                'sector_abbr' => $_POST['sector_abbr'],
                'metadata' => json_encode($result)
            ]);
            Session::addMessageAfterRedirect('Infraestrutura criada com sucesso!', true, INFO);
            global $CFG_GLPI;
            Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.php');
        } else {
            // Se houve erro durante a criação, mas a entidade foi criada, salvamos o que deu e redirecionamos para EDIÇÃO
            if (!empty($result['entity_id'])) {
                $newSectorId = $sectorObj->add([
                    'entities_id' => (int)$_POST['parent_entity'],
                    'sector_name' => $_POST['sector_name'],
                    'sector_abbr' => $_POST['sector_abbr'],
                    'metadata' => json_encode($result)
                ]);
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                Session::addMessageAfterRedirect('Infraestrutura criada parcialmente. Verifique os erros.', false, WARNING);
                global $CFG_GLPI;
                Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php?id=' . $newSectorId);
            } else {
                // Erro fatal logo no inicio. Volta para adicionar
                foreach ($result['errors'] as $err) {
                    Session::addMessageAfterRedirect($err, false, ERROR);
                }
                global $CFG_GLPI;
                Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php');
            }
        }
    }
}

// -----------------------------------------------------------------------
// Carrega dados para edição
// -----------------------------------------------------------------------
$def_sector_name = '';
$def_sector_abbr = '';
$def_parent_entity = 0;
$def_category_names = '';
$def_subgroups = [];

if ($isEdit) {
    $meta = json_decode($sectorObj->fields['metadata'], true) ?: [];
    $def_sector_name = $sectorObj->fields['sector_name'];
    $def_sector_abbr = $sectorObj->fields['sector_abbr'];
    $def_parent_entity = $sectorObj->fields['entities_id'];
    
    // Reconstruir subgrupos e técnicos a partir do banco (live)
    $def_subgroups = [];
    $live_success = false;
    if (!empty($meta['entity_id'])) {
        global $DB;
        $parentGroupIter = $DB->request([
            'SELECT' => ['id', 'groups_id'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => [
                'entities_id' => $meta['entity_id']
            ]
        ]);
        
        $parentGroupId = 0;
        foreach ($parentGroupIter as $row) {
            if (empty($row['groups_id'])) {
                $parentGroupId = $row['id'];
                break;
            }
        }
        
        if ($parentGroupId > 0) {
            $def_subgroups[] = ['name' => '', 'techs' => []]; // Bloco Pai
            
            $subgroupsIter = $DB->request([
                'SELECT' => ['id', 'name', 'groups_id'],
                'FROM'   => 'glpi_groups',
                'WHERE'  => [
                    'entities_id' => $meta['entity_id'],
                    'id' => ['<>', $parentGroupId]
                ],
                'ORDER'  => 'id ASC'
            ]);
            
            $sgMap = [$parentGroupId => 0]; // group_id => index in $def_subgroups
            $idx = 1;
            
            $rows = [];
            foreach ($subgroupsIter as $row) {
                $rows[] = $row;
            }
            
            foreach ($rows as $row) {
                // Se a view nativa mostra nomes certos mas tem um prefixo ou algo assim, pegamos o nome real
                $def_subgroups[] = ['name' => $row['name'], 'techs' => [], 'parent' => '-1'];
                $sgMap[$row['id']] = $idx++;
            }
            
            foreach ($rows as $row) {
                $myIdx = $sgMap[$row['id']];
                $parentGlpiId = $row['groups_id'];
                if (isset($sgMap[$parentGlpiId])) {
                    if ($parentGlpiId == $parentGroupId) {
                        $def_subgroups[$myIdx]['parent'] = '-1';
                    } else {
                        $def_subgroups[$myIdx]['parent'] = (string)$sgMap[$parentGlpiId];
                    }
                }
            }
            
            // Buscar emails dos técnicos
            $allGroupIds = array_keys($sgMap);
            $techsIter = $DB->request([
                'SELECT' => ['glpi_groups_users.groups_id', 'glpi_useremails.email'],
                'FROM'   => 'glpi_groups_users',
                'INNER JOIN' => [
                    'glpi_useremails' => [
                        'ON' => [
                            'glpi_groups_users' => 'users_id',
                            'glpi_useremails'   => 'users_id'
                        ]
                    ]
                ],
                'WHERE'  => [
                    'glpi_groups_users.groups_id' => $allGroupIds
                ]
            ]);
            
            foreach ($techsIter as $row) {
                $gId = $row['groups_id'];
                if (isset($sgMap[$gId])) {
                    $def_subgroups[$sgMap[$gId]]['techs'][] = $row['email'];
                }
            }
            $live_success = true;
        }
    }
    
    // Fallback para metadata antiga se a query live falhar ou não houver entidade criada
    if (!$live_success && !empty($meta['groups'])) {
        foreach ($meta['groups'] as $g) {
            $gName = $g['name'];
            $isParent = (strpos($gName, '(' . $sectorObj->fields['sector_abbr'] . ')') !== false);
            $sgName = $isParent ? '' : $gName;
            $def_subgroups[] = [
                'name' => $sgName,
                'techs' => [],
                'parent' => '-1'
            ];
        }
        
        if (!empty($meta['technicians'])) {
            foreach ($meta['technicians'] as $tech) {
                $parts = explode(' -> ', $tech['email']);
                $email = trim($parts[0]);
                $groupTarget = trim($parts[1] ?? '');
                
                foreach ($def_subgroups as &$sg) {
                    if (($groupTarget === 'Pai' && $sg['name'] === '') || ($groupTarget !== 'Pai' && $sg['name'] === $groupTarget)) {
                        $sg['techs'][] = $email;
                        break;
                    }
                }
                unset($sg);
            }
        }
    }

    foreach ($def_subgroups as &$sg) {
        $sg['techs'] = implode("\n", array_unique($sg['techs']));
    }
    unset($sg); // IMPORTANTE: quebra a referência para evitar corrupção no próximo foreach
    
    // Se por acaso vier vazio, garante pelo menos um bloco
    if (empty($def_subgroups)) {
        $def_subgroups[0] = ['name' => '', 'techs' => ''];
    }


    // Reconstruir categorias a partir do banco para manter a hierarquia com hífens
    $catList = [];
    if (!empty($meta['entity_id'])) {
        global $DB;
        $cat_iterator = $DB->request([
            'SELECT' => ['id', 'name', 'itilcategories_id'],
            'FROM'   => 'glpi_itilcategories',
            'WHERE'  => ['entities_id' => $meta['entity_id']]
        ]);
        
        $cats = [];
        $children = [];
        foreach ($cat_iterator as $row) {
            $cats[$row['id']] = $row;
            $children[$row['itilcategories_id']][] = $row['id'];
        }
        
        $buildTree = function($parentId, $depth) use (&$buildTree, &$catList, &$cats, &$children) {
            if (isset($children[$parentId])) {
                foreach ($children[$parentId] as $childId) {
                    $prefix = str_repeat('-', $depth);
                    $catList[] = $prefix . $cats[$childId]['name'];
                    $buildTree($childId, $depth + 1);
                }
            }
        };
        
        $buildTree(0, 0);
    }
    
    // Fallback caso a entidade não tenha sido criada ou não tenha categorias no DB
    if (empty($catList) && !empty($meta['categories'])) {
        foreach ($meta['categories'] as $c) {
            $catList[] = $c['name'];
        }
    }
    $def_category_names = implode("\n", $catList);

    // Reconstruir Perfis (Buscando diretamente do banco para a entidade criada)
    $def_profiles = [
        'admin' => ['id' => 0, 'emails' => []],
        'support' => ['id' => 0, 'emails' => []],
        'transfer' => ['id' => 0, 'emails' => []],
        'custom' => []
    ];
    if (!empty($meta['entity_id'])) {
        global $DB;
        $pu_iterator = $DB->request([
            'SELECT' => [
                'glpi_profiles_users.profiles_id',
                'glpi_useremails.email',
                'glpi_profiles.name AS profile_name'
            ],
            'FROM'   => 'glpi_profiles_users',
            'INNER JOIN' => [
                'glpi_useremails' => [
                    'ON' => [
                        'glpi_profiles_users' => 'users_id',
                        'glpi_useremails' => 'users_id'
                    ]
                ],
                'glpi_profiles' => [
                    'ON' => [
                        'glpi_profiles_users' => 'profiles_id',
                        'glpi_profiles' => 'id'
                    ]
                ]
            ],
            'WHERE'  => [
                'glpi_profiles_users.entities_id' => $meta['entity_id']
            ]
        ]);
        
        $profile_map = [];
        foreach ($pu_iterator as $row) {
            $pid = $row['profiles_id'];
            $pname = $row['profile_name'];
            $email = $row['email'];
            
            if (!isset($profile_map[$pid])) {
                if (str_ends_with($pname, ' - Admin')) {
                    $profile_map[$pid] = 'admin';
                } elseif (str_ends_with($pname, ' - Atendimento')) {
                    $profile_map[$pid] = 'support';
                } elseif (str_ends_with($pname, ' - Transferência de Chamados')) {
                    $profile_map[$pid] = 'transfer';
                } else {
                    $def_profiles['custom'][] = [
                        'id' => $pid,
                        'emails' => []
                    ];
                    $profile_map[$pid] = 'custom_' . (count($def_profiles['custom']) - 1);
                }
            }
            
            $map_key = $profile_map[$pid];
            if (str_starts_with($map_key, 'custom_')) {
                $idx = (int)str_replace('custom_', '', $map_key);
                $def_profiles['custom'][$idx]['emails'][] = $email;
            } else {
                $def_profiles[$map_key]['emails'][] = $email;
                $def_profiles[$map_key]['id'] = $pid;
            }
        }
    }
}

// -----------------------------------------------------------------------
// RENDERIZAÇÃO DA PÁGINA
// -----------------------------------------------------------------------
Html::header('GLPI New Entity — Form', $_SERVER['PHP_SELF'], 'config', 'plugins');

global $CFG_GLPI;
$form_url = $CFG_GLPI['root_doc'] . '/plugins/glpinewentity/front/sector.form.php';

echo "<div class='center' style='margin-top: 20px;'>";
echo "<style>
        .tab_cadre_fixe td {
            vertical-align: top !important;
        }
        .tab_cadre_fixe td:not([style*=\"padding: 0\"]) {
            padding-top: 15px !important;
            padding-bottom: 15px !important;
        }
    </style>";

// =====================================================================
// FORMULÁRIO WIZARD
// =====================================================================

    echo "<form method='post' action='" . $form_url . "' id='form_wizard'>";
    echo "<input type='hidden' name='process_wizard' value='1'>";
    if ($isEdit) {
        echo "<input type='hidden' name='id' value='{$sectorId}'>";
    }

    // ── Título Principal ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2' style='font-size: 1.2em;'>";
    echo "Nova Entidade para Central de Serviços";
    echo "</th></tr>";
    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 1: Dados da Entidade ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-building' style='margin-right: 5px;'></i> Dados da Entidade</th></tr>";

    // Entidade-Pai (dropdown nativo do GLPI)
    echo "<tr class='tab_bg_1'>";
    echo "      <td style='width: 35%;'>Entidade-Pai <span style='color:red;'>*</span></td>";
    echo "      <td>";
    Entity::dropdown([
        'name'  => 'parent_entity',
        'value' => $def_parent_entity,
    ]);
    echo "          <br><small class='text-muted'>Selecione sob qual entidade o novo setor será criado.</small>";
    echo "      </td>";
    echo "</tr>";

    // Nome do Setor
    echo "<tr class='tab_bg_1'>";
    echo "<td>Nome do Setor <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='text' name='sector_name' class='form-control' style='width: 100%;' placeholder='Ex: Departamento de Computação' value='" . Html::cleanInputText($def_sector_name) . "' required>";
    echo "</td>";
    echo "</tr>";

    // Sigla
    echo "<tr class='tab_bg_1'>";
    echo "<td>Sigla <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<input type='text' name='sector_abbr' class='form-control' style='width: 100%;' placeholder='Ex: DC' value='" . Html::cleanInputText($def_sector_abbr) . "' maxlength='20' required>";
    echo "<br><small class='text-muted'>A entidade será criada com o mesmo nome da sigla.</small>";
    echo "</td>";
    echo "</tr>";



    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 2: Perfis ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-id-card' style='margin-right: 5px;'></i> Perfis</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 15px;'>";
    
    // Carregar lista de perfis do banco
    $profiles = [];
    $profile_obj = new Profile();
    foreach ($profile_obj->find([], ['name']) as $p) {
        $profiles[$p['id']] = $p['name'];
    }

    echo "<div id='perfis-padrao-section'>";



    // Admin
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    $adminVal = $def_sector_abbr ? Html::cleanInputText($def_sector_abbr) . ' - Admin' : '';
    echo "      <input type='text' id='profile_admin' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly value='{$adminVal}'>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_admin' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    $adminId = $def_profiles['admin']['id'] ?? 0;
    foreach ($profiles as $pid => $pname) {
        if ($adminId > 0) {
            $selected = ($pid == $adminId) ? 'selected' : '';
        } else {
            $selected = (strpos($pname, '[Padrão] Admin') !== false) ? 'selected' : '';
        }
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    $adminEmails = Html::cleanInputText(implode("\n", $def_profiles['admin']['emails'] ?? []));
    echo "    <textarea name='users_profile_admin' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$adminEmails}</textarea>";
    echo "  </div>";
    echo "</div>";

    // Atendimento
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    $supportVal = $def_sector_abbr ? Html::cleanInputText($def_sector_abbr) . ' - Atendimento' : '';
    echo "      <input type='text' id='profile_support' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly value='{$supportVal}'>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_support' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    $supportId = $def_profiles['support']['id'] ?? 0;
    foreach ($profiles as $pid => $pname) {
        if ($supportId > 0) {
            $selected = ($pid == $supportId) ? 'selected' : '';
        } else {
            $selected = (strpos($pname, '[Padrão] Atendimento') !== false) ? 'selected' : '';
        }
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    $supportEmails = Html::cleanInputText(implode("\n", $def_profiles['support']['emails'] ?? []));
    echo "    <textarea name='users_profile_support' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$supportEmails}</textarea>";
    echo "  </div>";
    echo "</div>";

    // Transferência de Chamados
    echo "<div style='display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;'>";
    echo "  <div style='display: flex; gap: 10px;'>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Perfil Padrão</label>";
    $transferVal = $def_sector_abbr ? Html::cleanInputText($def_sector_abbr) . ' - Transferência de Chamados' : '';
    echo "      <input type='text' id='profile_transfer' name='profiles_default[]' class='form-control' style='width: 100%; border: none; background: #e9ecef;' readonly value='{$transferVal}'>";
    echo "    </div>";
    echo "    <div style='flex: 1;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "      <select name='copy_profile_transfer' class='form-select profile-select2' style='width: 100%;'>";
    echo "        <option value='0'>-----</option>";
    $transferId = $def_profiles['transfer']['id'] ?? 0;
    foreach ($profiles as $pid => $pname) {
        if ($transferId > 0) {
            $selected = ($pid == $transferId) ? 'selected' : '';
        } else {
            $selected = (strpos($pname, '[Padrão] Transferência de Chamados') !== false) ? 'selected' : '';
        }
        echo "        <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "      </select>";
    echo "    </div>";
    echo "  </div>";
    echo "  <div>";
    echo "    <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    $transferEmails = Html::cleanInputText(implode("\n", $def_profiles['transfer']['emails'] ?? []));
    echo "    <textarea name='users_profile_transfer' class='form-control' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$transferEmails}</textarea>";
    echo "  </div>";
    echo "</div>";

    echo "</div>"; // fecha #perfis-padrao-section
    
    echo "<br><small class='text-muted'>Os 'Perfis Padrão' são criados automaticamente com base na SIGLA e não podem ser apagados, mas você pode adicionar um novo em 'Adicionar Perfil'.</small>";
    echo "</td>";
    echo "</tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 0;'>";
    echo "<div id='profiles-container'>";
    
    // Template oculto para adicionar perfis customizados
    echo "<div class='profile-block template' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa; display: none;'>";
    echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
    echo "      <strong>Perfil Adicional</strong>";
    echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-profile'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
    echo "  </div>";
    echo "  <div style='display: flex; gap: 10px; align-items: flex-start;'>";
    echo "      <div style='flex: 1;'>";
    echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Nome do Perfil</label>";
    echo "          <input type='text' class='form-control profile-input' style='width: 100%;' placeholder='Ex: SIGLA - Coordenador' name='name_profile_custom[]'>";
    echo "      </div>";
    echo "      <div style='flex: 1;'>";
    echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
    echo "          <select name='copy_profile_custom[]' class='form-select profile-select2' style='width: 100%;'>";
    echo "            <option value='0'>-----</option>";
    foreach ($profiles as $pid => $pname) {
        echo "            <option value='{$pid}'>" . Html::cleanInputText($pname) . "</option>";
    }
    echo "          </select>";
    echo "      </div>";
    echo "  </div>";
    echo "  <div style='margin-top: 10px;'>";
    echo "      <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
    echo "      <textarea name='users_profile_custom[]' class='form-control profile-users-input' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'></textarea>";
    echo "  </div>";
    echo "</div>";

    // Renderiza perfis customizados existentes na edição
    if (!empty($def_profiles['custom'])) {
        foreach ($def_profiles['custom'] as $cProf) {
            $cEmails = Html::cleanInputText(implode("\n", $cProf['emails'] ?? []));
            $cId = $cProf['id'];
            
            echo "<div class='profile-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
            echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
            echo "      <strong>Perfil Adicional</strong>";
            echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-profile'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
            echo "  </div>";
            echo "  <div style='display: flex; gap: 10px; align-items: flex-start;'>";
            echo "      <div style='flex: 1;'>";
            echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Nome do Perfil</label>";
            echo "          <input type='text' class='form-control profile-input' name='name_profile_custom[]' style='width: 100%;' placeholder='Ex: SIGLA - Coordenador' value='" . Html::cleanInputText($cProf['name']) . "'>";
            echo "          <small class='text-muted'>O nome original não é carregado na edição, preencha novamente se desejar salvar outro.</small>";
            echo "      </div>";
            echo "      <div style='flex: 1;'>";
            echo "          <label style='display: block; margin-bottom: 5px; color: #444;'>Copiar de...</label>";
            echo "          <select name='copy_profile_custom[]' class='form-select profile-select2' style='width: 100%;'>";
            echo "            <option value='0'>-----</option>";
            foreach ($profiles as $pid => $pname) {
                $selected = ($pid == $cId) ? 'selected' : '';
                echo "            <option value='{$pid}' {$selected}>" . Html::cleanInputText($pname) . "</option>";
            }
            echo "          </select>";
            echo "      </div>";
            echo "  </div>";
            echo "  <div style='margin-top: 10px;'>";
            echo "      <label style='display: block; margin-bottom: 5px; color: #444; font-size: 0.9em;'>Usuários a serem vinculados neste perfil (E-mails)</label>";
            echo "      <textarea name='users_profile_custom[]' class='form-control profile-users-input' style='width: 100%; height: 50px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este perfil. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$cEmails}</textarea>";
            echo "  </div>";
            echo "</div>";
        }
    }

    echo "</div>";
    echo "<div style='padding: 0 10px 10px 10px;'>";
    echo "<button type='button' class='btn btn-success btn-sm' id='btn-add-profile'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar Perfil</button>";
    echo "</div>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";


    // ── Bloco 3: Grupos e Técnicos Atendentes ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-users-cog' style='margin-right: 5px;'></i> Grupos e Técnicos Atendentes</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td colspan='2' style='padding: 0;'>";
    echo "<div id='subgroups-container'>";
    
    if (empty($def_subgroups) || count($def_subgroups) <= 1) {
        // Se não tem subgrupos, ou só tem o pai (índice 0), renderiza 1 bloco vazio
        $sg0Name = Html::cleanInputText($def_subgroups[0]['name'] ?? '');
        $sg0Techs = Html::cleanInputText($def_subgroups[0]['techs'] ?? '');
        
        echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
        echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
        echo "      <strong>Subgrupo <span class='sg-index'>1</span></strong>";
        echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='display:none;'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
        echo "  </div>";
        echo "  <div style='margin-bottom: 10px;'>";
        echo "      <label>Nome do Subgrupo</label>";
        echo "      <input type='text' name='subgroups[0][name]' class='form-control sg-name-input' style='width: 100%;' value='" . $sg0Name . "' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
        echo "  </div>";
        echo "  <div style='margin-bottom: 10px;'>";
        echo "      <label>Grupo Pai</label>";
        echo "      <div class='parent-wrapper'>";
        echo "          <input type='text' class='form-control' value='(SIGLA)' readonly>";
        echo "          <input type='hidden' name='subgroups[0][parent]' value='-1'>";
        echo "      </div>";
        echo "  </div>";
        echo "  <div>";
        echo "      <label>E-mails dos Técnicos Atendentes</label>";
        echo "      <textarea name='subgroups[0][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>" . $sg0Techs . "</textarea>";
        echo "      <small class='text-muted'>Devem estar cadastrados no GLPI. Se informar um subgrupo, os técnicos irão EXCLUSIVAMENTE para ele. Senão, irão para o Grupo Pai <strong>({SIGLA})</strong>.</small>";
        echo "  </div>";
        echo "</div>";
    } else {
        // Tem subgrupos (além do pai). O índice 0 no $def_subgroups é o pai.
        // Se a pessoa preencheu subgrupos, o metadata os salvou a partir do índice 1.
        $i = 0;
        foreach ($def_subgroups as $idx => $sg) {
            if ($idx === 0) continue; // Pula o grupo pai que só foi salvo no metadata, mas não no form
            
            $sgName = Html::cleanInputText($sg['name']);
            $sgTechs = Html::cleanInputText($sg['techs'] ?? '');
            
            echo "<div class='subgroup-block' style='border: 1px solid #ccc; padding: 10px; margin: 10px; background: #fafafa;'>";
            echo "  <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
            echo "      <strong>Subgrupo <span class='sg-index'>".($i+1)."</span></strong>";
            echo "      <button type='button' class='btn btn-sm btn-danger btn-remove-subgroup' style='".($i==0 ? 'display:none;' : '')."'><i class='fas fa-trash' style='margin-right: 5px;'></i> Remover</button>";
            echo "  </div>";
            echo "  <div style='margin-bottom: 10px;'>";
            echo "      <label>Nome do Subgrupo</label>";
            echo "      <input type='text' name='subgroups[{$i}][name]' class='form-control sg-name-input' style='width: 100%;' value='{$sgName}' placeholder='Ex: Suporte Nível 1 (Deixe em branco para alocar no Grupo Pai)'>";
            echo "  </div>";
            echo "  <div style='margin-bottom: 10px;'>";
            echo "      <label>Grupo Pai</label>";
            echo "      <div class='parent-wrapper'>";
            echo "          <select name='subgroups[{$i}][parent]' class='form-select sg-parent-select' style='width: 100%;'>";
            echo "              <option value='-1' " . (($sg['parent'] ?? '-1') == '-1' ? 'selected' : '') . ">(SIGLA)</option>";
            for ($prev = 0; $prev < $i; $prev++) {
                $prevName = Html::cleanInputText($def_subgroups[$prev]['name'] ?? '');
                if (!empty($prevName)) {
                    $selected = (($sg['parent'] ?? '') == (string)$prev) ? 'selected' : '';
                    echo "              <option value='{$prev}' {$selected}>{$prevName}</option>";
                }
            }
            echo "          </select>";
            echo "      </div>";
            echo "  </div>";
            echo "  <div>";
            echo "      <label>E-mails dos Técnicos Atendentes</label>";
            echo "      <textarea name='subgroups[{$i}][techs]' class='form-control' style='width: 100%; height: 80px;' placeholder='Insira pelo menos um e-mail para ser adicionado a este subgrupo. Se precisar adicionar mais de um, separe os e-mails com vírgula ou quebra de linha (enter). (ex: nome1@dominio.com, nome2@dominio.com)'>{$sgTechs}</textarea>";
            echo "      <small class='text-muted'>Na edição, carregamos os e-mails salvos na base da entidade. Se quiser sincronizar, modifique e salve.</small>";
            echo "  </div>";
            echo "</div>";
            $i++;
        }
    }
    
    echo "</div>"; // Fim subgroups-container
    
    echo "<div style='margin: 10px;'>";
    echo "  <button type='button' id='btn-add-subgroup' class='btn btn-sm btn-primary'><i class='fas fa-plus' style='margin-right: 5px;'></i> Adicionar outro Subgrupo</button>";
    echo "</div>";
    
    echo "</td>";
    echo "</tr>";

    echo "</table>";

    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Bloco 4: Catálogo de Serviços ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr><th colspan='2'><i class='fas fa-clipboard-list' style='margin-right: 5px;'></i> Catálogo de Serviços (Categorias ITIL)</th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td style='width: 35%;'>Categorias de Serviço <span style='color:red;'>*</span></td>";
    echo "<td>";
    echo "<textarea name='category_names' class='form-control' style='width: 100%; height: 160px; overflow-y: scroll;' placeholder='Uma categoria por linha. Use hífen (-) para subcategorias.&#10;Ex:&#10;Hardware&#10;- Manutenção de Hardware&#10;-- Troca de Peças&#10;Software&#10;- Instalação de Software' required>" . Html::cleanInputText($def_category_names) . "</textarea>";
    echo "<br><small class='text-muted'>Cada categoria será vinculada exclusivamente à nova entidade, habilitada para Incidentes e Requisições.<br><strong>Importante:</strong> O sistema só identificará a hierarquia (Categorias Pai e Filha) se você usar o hífen (-) no início da linha correspondente.</small>";
    echo "</td>";
    echo "</tr>";

    echo "</table>";



    echo "<hr style='width: 750px; border-top: 3px solid black; margin: 20px auto 10px auto;'>";

    // ── Botão Submeter ──
    echo "<table class='tab_cadre_fixe' style='width: 750px;'>";
    echo "<tr class='tab_bg_2'>";
    echo "<td class='center' style='padding: 15px;'>";
    $btnTitle = $isEdit ? 'Salvar Modificações' : 'Criar Infraestrutura da Entidade';
    
    echo "<button type='submit' class='btn btn-primary' style='font-size: 1.05em; padding: 8px 30px;'>";
    echo $btnTitle;
    echo "</button>";
    echo "</td>";
    echo "</tr>";
    echo "</table>";

    Html::closeForm();

    echo "<script>
    $(function() {
        function validateEmailsStr(str) {
            let cleanStr = str.trim();
            if (cleanStr === '') return false;
            let emails = cleanStr.split(/[\\n,]+/);
            let emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
            for (let i = 0; i < emails.length; i++) {
                let e = emails[i].trim();
                if (e !== '' && !emailRegex.test(e)) {
                    return false;
                }
            }
            return true;
        }

        let index = $('.subgroup-block').length;

        function getSigla() {
            let val = $('input[name=\"sector_abbr\"]').val().trim();
            return val !== '' ? '(' + val + ')' : '';
        }

        function updateAllParentCombos(removedIndex = null) {
            const container = $('#subgroups-container');
            let siglaText = getSigla();
            
            let firstInput = container.find('.subgroup-block').first().find('.parent-wrapper input[type=\"text\"]');
            if (firstInput.length) {
                firstInput.val(siglaText);
            }
            
            let availableParents = [];
            container.find('.subgroup-block').each(function() {
                let nameInput = $(this).find('input.sg-name-input');
                if (!nameInput.length) return;
                
                let prevName = nameInput.val().trim();
                let match = nameInput.attr('name').match(/\d+/);
                if (match && prevName !== '') {
                    availableParents.push({ index: match[0], name: prevName });
                }
            });
            
            container.find('.subgroup-block').each(function() {
                let select = $(this).find('select.sg-parent-select');
                if (select.length === 0) return;
                
                let currentValue = select.val();
                if (removedIndex !== null && currentValue === removedIndex) {
                    currentValue = '-1';
                }
                
                let match = $(this).find('input.sg-name-input').attr('name').match(/\d+/);
                if (!match) return;
                let currentBlockIndex = match[0];
                
                let selectHtml = '<option value=\"-1\">' + siglaText + '</option>';
                let hasCurrentValue = false;
                if (currentValue === '-1') hasCurrentValue = true;
                
                for (let i = 0; i < availableParents.length; i++) {
                    let parentInfo = availableParents[i];
                    if (parentInfo.index === currentBlockIndex) break;
                    
                    selectHtml += '<option value=\"' + parentInfo.index + '\">' + parentInfo.name + '</option>';
                    if (currentValue === parentInfo.index) hasCurrentValue = true;
                }
                
                select.html(selectHtml);
                
                if (hasCurrentValue) {
                    select.val(currentValue);
                } else {
                    select.val('-1');
                }
            });
        }

        $('#subgroups-container').on('click', '.btn-remove-subgroup', function() {
            let block = $(this).closest('.subgroup-block');
            let match = block.find('input.sg-name-input').attr('name').match(/\d+/);
            let removedIndex = match ? match[0] : null;
            block.remove();
            updateAllParentCombos(removedIndex);
        });

        $('#subgroups-container').on('input', 'input.sg-name-input', function() {
            updateAllParentCombos();
        });

        $('#btn-add-subgroup').on('click', function() {
            const container = $('#subgroups-container');
            
            // Validação: verifica se os blocos atuais estão preenchidos
            let allFilled = true;
            let isSiglaEmpty = getSigla() === '';
            
            container.find('.subgroup-block').each(function() {
                const nameVal = $(this).find('input.sg-name-input').val().trim();
                const techsVal = $(this).find('textarea').val().trim();
                const isFirst = $(this).index() === 0;
                
                if (isFirst) {
                    if (isSiglaEmpty || techsVal === '') {
                        allFilled = false;
                    }
                } else {
                    const parentVal = $(this).find('select.sg-parent-select, input[name$=\"[parent]\"]').val();
                    if (nameVal === '' || parentVal === null || parentVal === '' || isSiglaEmpty || techsVal === '') {
                        allFilled = false;
                    }
                }
            });
            
            if (!allFilled) {
                alert('Por favor, preencha o Nome do Subgrupo, Grupo Pai e os E-mails dos técnicos em todos os blocos atuais antes de adicionar um novo.');
                return;
            }

            const firstBlock = container.find('.subgroup-block').first();
            const newBlock = firstBlock.clone();
            
            newBlock.find('input.sg-name-input').val('');
            newBlock.find('textarea').val('');
            
            newBlock.find('input.sg-name-input').attr('name', 'subgroups[' + index + '][name]');
            newBlock.find('textarea').attr('name', 'subgroups[' + index + '][techs]');
            newBlock.find('.sg-index').text(index + 1);
            
            let parentWrapper = newBlock.find('.parent-wrapper');
            parentWrapper.empty();
            let selectHtml = '<select name=\"subgroups[' + index + '][parent]\" class=\"form-select sg-parent-select\" style=\"width: 100%;\"></select>';
            parentWrapper.append(selectHtml);
            
            const btnRemove = newBlock.find('.btn-remove-subgroup');
            btnRemove.show();
            // Evento de remoção agora é delegado globalmente
            
            container.append(newBlock);
            index++;
            updateAllParentCombos();
        });

        // ── Lógica para Perfis Padrão ──
        $('input[name=\'sector_abbr\']').on('input', function() {
            let abbr = $(this).val().trim();
            if (abbr === '') {
                $('#profile_admin').val('');
                $('#profile_support').val('');
                $('#profile_transfer').val('');
            } else {
                $('#profile_admin').val(abbr + ' - Admin');
                $('#profile_support').val(abbr + ' - Atendimento');
                $('#profile_transfer').val(abbr + ' - Transferência de Chamados');
            }
            updateAllParentCombos();
        });
        // Inicializa ao carregar (se houver valor padrão)
        $('input[name=\'sector_abbr\']').trigger('input');

        // Inicializa o select2 explicitamente com 100% de largura
        $('.profile-select2').select2({ width: '100%' });

        // ── Lógica para Adicionar Perfis Customizados ──
        $('#btn-add-profile').on('click', function() {
            const container = $('#profiles-container');
            const template = container.find('.profile-block.template');
            
            // Valida os visíveis atuais
            let allFilled = true;
            let emailsValid = true;
            container.find('.profile-block:visible').each(function() {
                const nameVal = $(this).find('.profile-input').val().trim();
                const copyVal = $(this).find('select').val();
                const usersVal = $(this).find('.profile-users-input').val().trim();
                if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                    allFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    emailsValid = false;
                }
            });
            
            if (!allFilled || !emailsValid) {
                alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) antes de adicionar um novo.');
                return;
            }
            
            const newBlock = template.clone();
            newBlock.removeClass('template');
            newBlock.css('display', 'block');
            newBlock.find('.profile-input').attr('name', 'name_profile_custom[]');
            
            // Limpa o textarea de usuários
            newBlock.find('.profile-users-input').val('');
            
            // Remove o lixo do select2 clonado
            newBlock.find('.select2-container').remove();
            
            // Restaura o select original para inicializar o select2 novamente
            let selectEl = newBlock.find('select');
            selectEl.removeClass('select2-hidden-accessible')
                    .removeAttr('data-select2-id')
                    .removeAttr('tabindex')
                    .removeAttr('aria-hidden')
                    .show();
            
            // Gera um ID novo e limpa o valor selecionado
            let newId = 'dropdown_copy_profile_' + Date.now();
            selectEl.attr('id', newId).val('0');
            selectEl.find('option').removeAttr('data-select2-id');

            newBlock.find('.btn-remove-profile').on('click', function() {
                newBlock.remove();
            });
            
            container.append(newBlock);

            // Inicializa select2 no novo dropdown com largura total
            $('#' + newId).select2({ width: '100%' });
        });

        // Validação no Submit do Formulário
        $('#form_wizard').on('submit', function(e) {
            let standardFilled = true;
            let standardEmailsValid = true;
            $('#perfis-padrao-section select').each(function() {
                if ($(this).val() === '0' || $(this).val() === null) {
                    standardFilled = false;
                }
            });
            $('#perfis-padrao-section textarea').each(function() {
                let usersVal = $(this).val().trim();
                if (usersVal === '') {
                    standardFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    standardEmailsValid = false;
                }
            });
            
            if (!standardFilled || !standardEmailsValid) {
                e.preventDefault();
                alert('Por favor, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Padrão.');
                return false;
            }

            let customFilled = true;
            let customEmailsValid = true;
            $('#profiles-container .profile-block:visible').each(function() {
                const nameVal = $(this).find('.profile-input').val().trim();
                const copyVal = $(this).find('select').val();
                const usersVal = $(this).find('.profile-users-input').val().trim();
                
                if (nameVal === '' || copyVal === '' || copyVal === null || copyVal === '0' || usersVal === '') {
                    customFilled = false;
                } else if (!validateEmailsStr(usersVal)) {
                    customEmailsValid = false;
                }
            });
            
            if (!customFilled || !customEmailsValid) {
                e.preventDefault();
                alert('Por favor, preencha o nome do perfil, selecione de qual perfil copiar e certifique-se de que todos os e-mails informados são válidos (ex: nome@dominio.com) para todos os Perfis Adicionais.');
                return false;
            }
        });

    });
    </script>";

echo "</div>";

Html::footer();
