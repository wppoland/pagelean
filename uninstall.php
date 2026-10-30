<?php
/**
 * Removes what the plugin stored when it is deleted: the rules and settings,
 * the schema version and the list of handles it has seen on the front end.
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

// An add-on saves rules into plogins_assets_settings too. While it is installed,
// active or not, that option is the add-on's to remove when it is uninstalled;
// with no add-on on disk it is ours. Matched on the text domain, so a renamed
// folder still counts. A bulk delete runs both uninstalls in one request and the
// plugin list is cached per request, so drop the cache first.
if (! function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
wp_cache_delete('plugins', 'plugins');
$plogins_assets_addon_installed = in_array('pagelean-pro', array_column(get_plugins(), 'TextDomain'), true);

// Clean the current site. A closure rather than a named function, so the file
// can be included more than once in a request.
$plogins_assets_uninstall_site = static function () use ($plogins_assets_addon_installed): void {
    if (! $plogins_assets_addon_installed) {
        delete_option('plogins_assets_settings');
    }
    delete_option('plogins_assets_db_version');
    delete_option('plogins_assets_seen_handles');
};

// Options are per site, so a network uninstall visits every site.
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $plogins_assets_site_id) {
        switch_to_blog((int) $plogins_assets_site_id);
        $plogins_assets_uninstall_site();
        restore_current_blog();
    }
    unset($plogins_assets_site_id);
} else {
    $plogins_assets_uninstall_site();
}
unset($plogins_assets_uninstall_site);
