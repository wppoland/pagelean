<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container.
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

use PloginsAssets\Admin\SettingsPage;
use PloginsAssets\Container;
use PloginsAssets\Frontend\AssetManager;
use PloginsAssets\Frontend\HandleDiscovery;
use PloginsAssets\Migrator;
use PloginsAssets\Settings\SettingsRepository;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    $c->singleton(SettingsRepository::class, static fn (): SettingsRepository => new SettingsRepository());

    $c->singleton(AssetManager::class, static fn (Container $c): AssetManager => new AssetManager(
        $c->get(SettingsRepository::class),
    ));

    $c->singleton(HandleDiscovery::class, static fn (): HandleDiscovery => new HandleDiscovery());

    $c->singleton(SettingsPage::class, static fn (Container $c): SettingsPage => new SettingsPage(
        $c->get(SettingsRepository::class),
        $c->get(HandleDiscovery::class),
    ));
};
