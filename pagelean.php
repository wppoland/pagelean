<?php
/**
 * Plugin Name:       Pagelean - Conditional Script and Style Loading
 * Plugin URI:        https://plogins.com/pagelean/
 * Description:        Dequeue individual scripts and styles on the pages that do not need them. Fewer requests, lighter pages, faster loads - using WordPress core, no page builder lock-in.
 * Version:           1.2.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pagelean
 * Domain Path:       /languages
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

namespace PloginsAssets;

defined('ABSPATH') || exit;

const VERSION     = '1.2.1';
const PLUGIN_FILE = __FILE__;

define('PLOGINS_ASSETS_DIR', plugin_dir_path(__FILE__));
define('PLOGINS_ASSETS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', static function (): void {
    Plugin::instance()->boot();
}, 0);
