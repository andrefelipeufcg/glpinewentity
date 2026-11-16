<?php

namespace GlpiPlugin\Glpinewentity;


/** Integração opcional com as configurações do plugin de transferência de chamados. */
final class TransferBridge
{
    public static function pluginName(): string
    {
        return (string) \Config::getConfigurationValue('glpinewentity', 'transfer_plugin');
    }

    public static function validPluginName(string $name): bool
    {
        return strlen($name) <= 50 && preg_match('/\A[a-zA-Z0-9]+\z/D', $name) === 1;
    }

    public static function pluginExists(string $name): bool
    {
        if (!self::validPluginName($name)) return false;
        $directories = defined('GLPI_PLUGINS_DIRECTORIES') ? GLPI_PLUGINS_DIRECTORIES : (defined('GLPI_ROOT') ? [GLPI_ROOT . '/plugins', GLPI_ROOT . '/marketplace'] : []);
        foreach ($directories as $root) {
            if (is_dir($root . DIRECTORY_SEPARATOR . $name)
                && in_array($name, scandir($root) ?: [], true)
                && is_file($root . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'setup.php')) return true;
        }
        return false;
    }

    private static function pluginClass(string $class): string
    {
        return 'GlpiPlugin\\' . ucfirst(self::pluginName()) . '\\' . $class;
    }

    public static function active(): bool
    {
        if (!self::enabled(\Config::getConfigurationValue('glpinewentity', 'transfer_enabled'))) return false;
        $name = self::pluginName();
        if (!self::validPluginName($name) || !class_exists(\Plugin::class) || !method_exists(\Plugin::class, 'isActivated') || !(new \Plugin())->isActivated($name)) return false;
        foreach (['Settings', 'Entity', 'Profile'] as $class) {
            if (!class_exists(self::pluginClass($class))) return false;
        }
        foreach (['getLastApplied', 'rememberApplied', 'getAssignedGroupId'] as $method) {
            if (!is_callable([self::pluginClass('Settings'), $method])) return false;
        }
        foreach (['checkRights', 'getEntityGroups', 'availableCategories'] as $method) {
            if (!is_callable([self::pluginClass('Entity'), $method])) return false;
        }
        return is_callable([self::pluginClass('Profile'), 'getAllRights']);
    }

    public static function enabled($value): bool
    {
        return (string) $value === '1';
    }

    public static function entityEnabled(int $id): bool
    {
        if (!self::active()) return false;
        if (in_array($id, array_map('intval', (array)((self::pluginClass('Settings'))::getLastApplied('entities')['entities'] ?? [])), true)) return true;
        $row = (self::pluginClass('Entity'))::checkRights($id);
        return self::enabled($row['allow_transfer'] ?? 0);
    }

    public static function profileEnabled(int $id): bool
    {
        if (!self::active()) return false;
        if (in_array($id, array_map('intval', (array)((self::pluginClass('Settings'))::getLastApplied('profiles')['profiles'] ?? [])), true)) return true;
        $fields = array_column((self::pluginClass('Profile'))::getAllRights(), 'field');
        foreach (\ProfileRight::getProfileRights($id, $fields) as $value) {
            if ((int) $value > 0) return true;
        }
        return false;
    }

    public static function selectedGroups(): array
    {
        if (!self::active()) return [];
        return array_map('intval', (array) ((self::pluginClass('Settings'))::getLastApplied('groups')['groups'] ?? []));
    }

    /** Validate against the prospective parent before any structure is modified. */
    public static function validate(array $input, int $entityId, int $parentId): ?string
    {
        if (!self::active()) return null;
        global $DB;
        foreach ((array) ($input['subgroups'] ?? []) as $group) {
            if (self::enabled($group['transfer'] ?? 0)) {
                $target = (self::pluginClass('Settings'))::getAssignedGroupId();
                $item = new \Group();
                if ($target <= 0 || !$item->getFromDB($target)) {
                    return __('Configure um grupo de atribuição válido no plugin de transferência de chamados antes de permitir a visualização de chamados transferidos.', 'glpinewentity');
                }
                if (!\Session::haveRight(\Group::$rightname, UPDATE) || !\Session::haveAccessToEntity($item->getEntityID(), $item->isRecursive())) {
                    return __('Você não tem permissão para atualizar o grupo de atribuição do plugin de transferência de chamados.', 'glpinewentity');
                }
                break;
            }
        }
        if (!self::enabled($input['transfer_entity'] ?? 0)) return null;
        $last = (self::pluginClass('Settings'))::getLastApplied('entities');
        if (!self::enabled($last['allow_transfer'] ?? 0)) return null;
        $groupId = self::enabled($last['allow_entity_only_transfer'] ?? 0) ? (int) ($last['groups_id'] ?? (self::pluginClass('Settings'))::getAssignedGroupId()) : 0;
        if (self::enabled($last['allow_entity_only_transfer'] ?? 0) && $groupId <= 0) {
            return __('Selecione um grupo de atribuição nas configurações do plugin de transferência de chamados.', 'glpinewentity');
        }
        $categoryId = !self::enabled($last['keep_category'] ?? 0) ? (int) ($last['itilcategories_id'] ?? 0) : 0;
        foreach ([['glpi_groups', $groupId, 'group'], ['glpi_itilcategories', $categoryId, 'category']] as [$table, $id, $kind]) {
            if ($id <= 0) continue;
            $row = $DB->request(['FROM' => $table, 'WHERE' => ['id' => $id], 'LIMIT' => 1])->current();
            $available = false;
            if ($row && (int) $row['entities_id'] === $entityId && $entityId > 0) {
                $available = true;
            } elseif ($row && !empty($row['is_recursive'])) {
                if ($kind === 'group') {
                    $available = in_array($id, array_map('intval', array_column((self::pluginClass('Entity'))::getEntityGroups($parentId), 'id')), true);
                } else {
                    foreach ((self::pluginClass('Entity'))::availableCategories($parentId) as $categories) {
                        if (array_key_exists($id, $categories)) $available = true;
                    }
                }
            }
            if (!$available) {
                return $kind === 'group'
                    ? __('O grupo de atribuição configurado no plugin de transferência de chamados não está disponível na entidade escolhida.', 'glpinewentity')
                    : __('A categoria padrão configurada no plugin de transferência de chamados não está disponível na entidade escolhida.', 'glpinewentity');
            }
        }
        return null;
    }

    private static function mergeIds(array $existing, array $remove, array $add): array
    {
        return array_values(array_unique(array_merge(array_diff(array_map('intval', $existing), array_map('intval', $remove)), array_map('intval', $add))));
    }

    public static function apply(int $entityId, bool $entitySelected, array $profiles, array $removedProfiles, array $groups, array $oldGroups): void
    {
        if (!self::active()) return;
        global $DB;
        $entityLast = (self::pluginClass('Settings'))::getLastApplied('entities');
        $allow = $entitySelected && self::enabled($entityLast['allow_transfer'] ?? 0);
        $required = $allow && self::enabled($entityLast['allow_entity_only_transfer'] ?? 0);
        $keepCategory = $allow && self::enabled($entityLast['keep_category'] ?? 0);
        $DB->beginTransaction();
        try {
            if (!$DB->updateOrInsert('glpi_plugin_' . self::pluginName() . '_entities_settings', [
                'allow_transfer' => (int) $allow,
                'allow_entity_only_transfer' => (int) $required,
                'groups_id' => $required ? (int) ($entityLast['groups_id'] ?? (self::pluginClass('Settings'))::getAssignedGroupId()) : 0,
                'keep_sla' => (int) ($allow && self::enabled($entityLast['keep_sla'] ?? 1)),
                'justification_transfer' => (int) ($allow && self::enabled($entityLast['justification_transfer'] ?? 0)),
                'keep_category' => (int) $keepCategory,
                'itilcategories_id' => $allow && !$keepCategory && (int) ($entityLast['itilcategories_id'] ?? 0) > 0 ? (int) $entityLast['itilcategories_id'] : null,
            ], ['entities_id' => $entityId])) throw new \RuntimeException(__('Unable to save entity transfer settings.', 'glpinewentity'));
            $entityLast['entities'] = self::mergeIds((array) ($entityLast['entities'] ?? []), [$entityId], $entitySelected ? [$entityId] : []);
            (self::pluginClass('Settings'))::rememberApplied('entities', $entityLast);

            $profileLast = (self::pluginClass('Settings'))::getLastApplied('profiles');
            $selectedProfiles = [];
            foreach ($profiles as $id => $selected) {
                $rights = [];
                foreach ((self::pluginClass('Profile'))::getAllRights() as $right) {
                    $rights[$right['field']] = $selected && (int) ($profileLast['rights'][$right['field']] ?? 0) > 0 ? key($right['rights']) : 0;
                }
                \ProfileRight::updateProfileRights((int) $id, $rights);
                $stored = \ProfileRight::getProfileRights((int) $id, array_keys($rights));
                foreach ($rights as $name => $value) {
                    if ((int) ($stored[$name] ?? -1) !== (int) $value) throw new \RuntimeException(__('Não foi possível salvar as permissões de transferência do perfil.', 'glpinewentity'));
                }
                if ($selected) $selectedProfiles[] = (int) $id;
            }
            $profileLast['profiles'] = self::mergeIds((array) ($profileLast['profiles'] ?? []), array_merge(array_keys($profiles), $removedProfiles), $selectedProfiles);
            (self::pluginClass('Settings'))::rememberApplied('profiles', $profileLast);

            $groupLast = (self::pluginClass('Settings'))::getLastApplied('groups');
            $selectedGroups = [];
            foreach ($groups as $id => $selected) if ($selected) $selectedGroups[] = (int) $id;
            $groupLast['groups'] = self::mergeIds((array) ($groupLast['groups'] ?? []), array_merge($oldGroups, array_keys($groups)), $selectedGroups);
            self::syncMembers($groupLast['groups']);
            (self::pluginClass('Settings'))::rememberApplied('groups', $groupLast);
            $DB->commit();
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    public static function syncCurrentMembers(int $changedGroupId): void
    {
        static $syncing = false;
        if ($syncing || !self::active()) return;
        $sourceGroups = self::selectedGroups();
        if (!in_array($changedGroupId, $sourceGroups, true)) return;
        $syncing = true;
        try {
            self::syncMembers($sourceGroups);
        } finally {
            $syncing = false;
        }
    }

    private static function syncMembers(array $sourceGroups): void
    {
        global $DB;
        $target = (int) (self::pluginClass('Settings'))::getAssignedGroupId();
        if ($target <= 0) {
            if ($sourceGroups) throw new \RuntimeException(__('Configure um grupo de atribuição válido no plugin de transferência de chamados.', 'glpinewentity'));
            return;
        }
        if (in_array($target, $sourceGroups, true)) {
            throw new \RuntimeException(__('O grupo de atribuição não pode ser selecionado como grupo de origem.', 'glpinewentity'));
        }
        $targetGroup = new \Group();
        if (!$targetGroup->getFromDB($target) || !\Session::haveRight(\Group::$rightname, UPDATE) || !\Session::haveAccessToEntity($targetGroup->getEntityID(), $targetGroup->isRecursive())) {
            throw new \RuntimeException(__('Grupo de atribuição inválido ou sem permissão de atualização.', 'glpinewentity'));
        }
        $needed = [];
        if ($sourceGroups) {
            foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_groups_users', 'WHERE' => ['groups_id' => $sourceGroups]]) as $member) $needed[(int) $member['users_id']] = true;
        }
        $existing = [];
        foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_groups_users', 'WHERE' => ['groups_id' => $target]]) as $member) $existing[(int) $member['users_id']] = true;
        $groupUser = new \Group_User();
        foreach (array_keys($existing) as $user) {
            if (!isset($needed[$user]) && !$groupUser->deleteByCriteria(['groups_id' => $target, 'users_id' => $user], true)) {
                throw new \RuntimeException(__('Não foi possível remover um usuário do grupo de atribuição.', 'glpinewentity'));
            }
        }
        foreach (array_keys($needed) as $user) {
            if (!isset($existing[$user]) && !$groupUser->add(['groups_id' => $target, 'users_id' => $user])) {
                throw new \RuntimeException(__('Não foi possível adicionar um usuário ao grupo de atribuição.', 'glpinewentity'));
            }
        }
    }
}
