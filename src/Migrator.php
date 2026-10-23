<?php

declare(strict_types=1);

namespace PloginsAssets;

defined('ABSPATH') || exit;

/**
 * Idempotent schema/version migrations, run on every boot. Compares a stored
 * option against VERSION and applies forward steps as needed.
 */
final class Migrator
{
    private const OPTION = 'plogins_assets_db_version';

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, VERSION, '>=')) {
            return;
        }

        // No custom tables. Rules live in the plogins_assets_settings option,
        // merged over packaged defaults at read time.

        if (version_compare($current, '1.2.0', '<')) {
            $this->keepDeregisterOnExistingRules();
        }

        update_option(self::OPTION, VERSION, false);
    }

    /**
     * Before 1.2.0 every rule dequeued AND deregistered its handle. From 1.2.0
     * a rule only dequeues unless "Also deregister" is ticked. Rules saved
     * before the upgrade get the box ticked so they keep doing exactly what
     * they did; new rules start on the safer dequeue-only path.
     */
    private function keepDeregisterOnExistingRules(): void
    {
        $stored = get_option(Settings\SettingsRepository::OPTION, []);
        if (! is_array($stored) || empty($stored['rules']) || ! is_array($stored['rules'])) {
            return;
        }

        foreach ($stored['rules'] as $i => $rule) {
            if (is_array($rule) && ! array_key_exists('deregister', $rule)) {
                $stored['rules'][$i]['deregister'] = true;
            }
        }

        update_option(Settings\SettingsRepository::OPTION, $stored);
    }
}
