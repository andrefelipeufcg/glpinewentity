<?php

namespace GlpiPlugin\Glpinewentity;

use GlpiPlugin\Transferticketentity\Entity as TransferEntity;
use GlpiPlugin\Transferticketentity\Profile as TransferProfile;
use GlpiPlugin\Transferticketentity\Settings;

/** Optional integration with the settings maintained by Transfer Ticket Entity. */
final class TransferBridge
{
    public static function active(): bool
    {
        return class_exists(\Plugin::class) && method_exists(\Plugin::class, 'isActivated') && (new \Plugin())->isActivated('transferticketentity');
    }

    public static function enabled($value): bool
    {
        return (string) $value === '1';
    }

    public static function entityEnabled(int $id): bool
    {
        if (in_array($id, array_map('intval', (array)(Settings::getLastApplied('entities')['entities'] ?? [])), true)) return true;
        $row = TransferEntity::checkRights($id);
        return self::enabled($row['allow_transfer'] ?? 0);
    }

    public static function profileEnabled(int $id): bool
    {
        if (in_array($id, array_map('intval', (array)(Settings::getLastApplied('profiles')['profiles'] ?? [])), true)) return true;
        $fields = array_column(TransferProfile::getAllRights(), 'field');
        foreach (\ProfileRight::getProfileRights($id, $fields) as $value) {
            if ((int) $value > 0) return true;
        }
        return false;
    }

    public static function selectedGroups(): array
    {
        return array_map('intval', (array) (Settings::getLastApplied('groups')['groups'] ?? []));
    }

    /** Validate against the prospective parent before any structure is modified. */
    public static function validate(array $input, int $entityId, int $parentId): ?string
    {
        global $DB;
        foreach ((array) ($input['subgroups'] ?? []) as $group) {
            if (self::enabled($group['transfer'] ?? 0)) {
                $target = Settings::getAssignedGroupId();
                $item = new \Group();
                if ($target <= 0 || !$item->getFromDB($target)) {
                    return __('Configure um grupo de atribuição válido no plugin Transfer Ticket Entity antes de permitir a visualização de chamados transferidos.', 'glpinewentity');
                }
                if (!\Session::haveRight(\Group::$rightname, UPDATE) || !\Session::haveAccessToEntity($item->getEntityID(), $item->isRecursive())) {
                    return __('Você não tem permissão para atualizar o grupo de atribuição do Transfer Ticket Entity.', 'glpinewentity');
                }
                break;
            }
        }
        if (!self::enabled($input['transfer_entity'] ?? 0)) return null;
        $last = Settings::getLastApplied('entities');
        if (!self::enabled($last['allow_transfer'] ?? 0)) return null;
        $groupId = self::enabled($last['allow_entity_only_transfer'] ?? 0) ? (int) ($last['groups_id'] ?? Settings::getAssignedGroupId()) : 0;
        if (self::enabled($last['allow_entity_only_transfer'] ?? 0) && $groupId <= 0) {
            return __('Selecione um grupo de atribuição nas configurações do Transfer Ticket Entity.', 'glpinewentity');
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
                    $available = in_array($id, array_map('intval', array_column(TransferEntity::getEntityGroups($parentId), 'id')), true);
                } else {
                    foreach (TransferEntity::availableCategories($parentId) as $categories) {
                        if (array_key_exists($id, $categories)) $available = true;
                    }
                }
            }
            if (!$available) {
                return $kind === 'group'
                    ? __('O grupo de atribuição configurado no Transfer Ticket Entity não está disponível na entidade escolhida.', 'glpinewentity')
                    : __('A categoria padrão configurada no Transfer Ticket Entity não está disponível na entidade escolhida.', 'glpinewentity');
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
        global $DB;
        $entityLast = Settings::getLastApplied('entities');
        $allow = $entitySelected && self::enabled($entityLast['allow_transfer'] ?? 0);
        $required = $allow && self::enabled($entityLast['allow_entity_only_transfer'] ?? 0);
        $keepCategory = $allow && self::enabled($entityLast['keep_category'] ?? 0);
        if (!$DB->beginTransaction()) throw new \RuntimeException(__('Unable to start transfer configuration transaction.', 'glpinewentity'));
        try {
            if (!$DB->updateOrInsert('glpi_plugin_transferticketentity_entities_settings', [
                'allow_transfer' => (int) $allow,
                'allow_entity_only_transfer' => (int) $required,
                'groups_id' => $required ? (int) ($entityLast['groups_id'] ?? Settings::getAssignedGroupId()) : 0,
                'keep_sla' => (int) ($allow && self::enabled($entityLast['keep_sla'] ?? 1)),
                'justification_transfer' => (int) ($allow && self::enabled($entityLast['justification_transfer'] ?? 0)),
                'keep_category' => (int) $keepCategory,
                'itilcategories_id' => $allow && !$keepCategory && (int) ($entityLast['itilcategories_id'] ?? 0) > 0 ? (int) $entityLast['itilcategories_id'] : null,
            ], ['entities_id' => $entityId])) throw new \RuntimeException(__('Unable to save entity transfer settings.', 'glpinewentity'));
            $entityLast['entities'] = self::mergeIds((array) ($entityLast['entities'] ?? []), [$entityId], $entitySelected ? [$entityId] : []);
            Settings::rememberApplied('entities', $entityLast);

            $profileLast = Settings::getLastApplied('profiles');
            $selectedProfiles = [];
            foreach ($profiles as $id => $selected) {
                $rights = [];
                foreach (TransferProfile::getAllRights() as $right) {
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
            Settings::rememberApplied('profiles', $profileLast);

            $groupLast = Settings::getLastApplied('groups');
            $selectedGroups = [];
            foreach ($groups as $id => $selected) if ($selected) $selectedGroups[] = (int) $id;
            $groupLast['groups'] = self::mergeIds((array) ($groupLast['groups'] ?? []), array_merge($oldGroups, array_keys($groups)), $selectedGroups);
            self::syncMembers($groupLast['groups']);
            Settings::rememberApplied('groups', $groupLast);
            if (!$DB->commit()) throw new \RuntimeException(__('Unable to commit transfer settings.', 'glpinewentity'));
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    private static function syncMembers(array $sourceGroups): void
    {
        global $DB;
        $target = Settings::getAssignedGroupId();
        if (in_array($target, $sourceGroups, true) && $target > 0) {
            throw new \RuntimeException(__('O grupo de atribuição não pode ser selecionado como grupo de origem.', 'glpinewentity'));
        }
        $owned = json_decode((string) \Config::getConfigurationValue('glpinewentity', 'transfer_owned_members'), true);
        if (!is_array($owned)) $owned = [];
        $needed = [];
        if ($sourceGroups) {
            foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_groups_users', 'WHERE' => ['groups_id' => $sourceGroups]]) as $member) $needed[(int) $member['users_id']] = true;
        }
        foreach ($owned as $oldTarget => $users) {
            $gu = new \Group_User();
            foreach ((array) $users as $user) {
                if ((int) $oldTarget === $target && isset($needed[(int) $user])) continue;
                $criteria = ['groups_id' => (int) $oldTarget, 'users_id' => (int) $user];
                $exists = $DB->request(['FROM' => 'glpi_groups_users', 'WHERE' => $criteria, 'LIMIT' => 1])->current();
                if ($exists && !$gu->deleteByCriteria($criteria, true)) throw new \RuntimeException(__('Não foi possível remover um usuário do grupo de atribuição.', 'glpinewentity'));
                unset($owned[$oldTarget][array_search($user, $owned[$oldTarget], true)]);
            }
            $owned[$oldTarget] = array_values($owned[$oldTarget]);
            if (!$owned[$oldTarget]) unset($owned[$oldTarget]);
        }
        if ($target > 0 && $needed) {
            $existing = [];
            foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_groups_users', 'WHERE' => ['groups_id' => $target]]) as $member) $existing[(int) $member['users_id']] = true;
            foreach (array_keys($needed) as $user) {
                if (isset($existing[$user])) continue;
                if (!(new \Group_User())->add(['groups_id' => $target, 'users_id' => $user])) throw new \RuntimeException(__('Não foi possível adicionar um usuário ao grupo de atribuição.', 'glpinewentity'));
                $owned[$target][] = $user;
            }
        }
        \Config::setConfigurationValues('glpinewentity', ['transfer_owned_members' => json_encode($owned)]);
    }
}
