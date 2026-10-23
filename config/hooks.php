<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * PloginsAssets\Contract\HasHooks.
 *
 * @package PloginsAssets
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use PloginsAssets\Admin\SettingsPage;
use PloginsAssets\Frontend\AssetManager;
use PloginsAssets\Frontend\HandleDiscovery;

defined('ABSPATH') || exit;

return [
    AssetManager::class,
    HandleDiscovery::class,
    SettingsPage::class,
];
