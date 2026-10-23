<?php
/**
 * ponytail: one runnable self-check for the money path (rule sanitisation).
 * Run:  php tests/test-normalize.php
 * No framework: shims the two WP functions the repo touches, then asserts.
 *
 * @package PloginsAssets
 */

declare(strict_types=1);

namespace PloginsAssets {
    const VERSION = '1.2.0';
}

namespace {
define('ABSPATH', __DIR__);

$GLOBALS['test_options'] = [];
function get_option(string $k, mixed $d = false): mixed
{
    return $GLOBALS['test_options'][$k] ?? $d;
}
function update_option(string $k, mixed $v, mixed $autoload = null): bool
{
    $GLOBALS['test_options'][$k] = $v;

    return true;
}
define('PLOGINS_ASSETS_DIR', dirname(__DIR__) . '/');

// Minimal shims for the WP functions SettingsRepository::normalizeRules() calls.
function sanitize_text_field(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', wp_strip_all_tags($s)) ?? '');
}
function wp_strip_all_tags(string $s): string
{
    return trim(strip_tags($s));
}
function absint(mixed $n): int
{
    return abs((int) $n);
}
function sanitize_key(string $s): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)) ?? '';
}
/** @return array<string, object> */
function get_post_types(array $args, string $output): array
{
    $o = static fn (string $n): object => (object) ['labels' => (object) ['singular_name' => $n]];

    return ['post' => $o('Post'), 'page' => $o('Page'), 'product' => $o('Product')];
}

require dirname(__DIR__) . '/src/Settings/SettingsRepository.php';
require dirname(__DIR__) . '/src/Contract/HasHooks.php';
require dirname(__DIR__) . '/src/Frontend/HandleDiscovery.php';
require dirname(__DIR__) . '/src/Migrator.php';

use PloginsAssets\Frontend\HandleDiscovery;
use PloginsAssets\Migrator;
use PloginsAssets\Settings\SettingsRepository;

$repo = new SettingsRepository();

// 1. Rows without a handle are dropped; handle is trimmed + tag-stripped.
$out = $repo->normalizeRules([
    ['handle' => '  contact-form-7  ', 'type' => 'script', 'condition' => 'not_singular', 'ids' => ''],
    ['handle' => '', 'type' => 'style', 'condition' => 'everywhere'],
    ['handle' => "<b>slider</b>", 'type' => 'style', 'condition' => 'is_singular', 'ids' => '12, 34, 34, -5, abc'],
]);
assert(count($out) === 2, 'empty-handle row must be dropped');
assert($out[0]['handle'] === 'contact-form-7', 'handle must be trimmed');
assert($out[0]['type'] === 'script');
assert($out[0]['ids'] === []);

// 2. Type defaults to script; unknown condition falls back to everywhere.
$out2 = $repo->normalizeRules([['handle' => 'x', 'type' => 'bogus', 'condition' => 'nope']]);
assert($out2[0]['type'] === 'script', 'bad type -> script');
assert($out2[0]['condition'] === 'everywhere', 'bad condition -> everywhere');

// 3. IDs: tags stripped from handle, ids parsed, deduped, positives only.
assert($out[1]['handle'] === 'slider', 'tags stripped from handle');
assert($out[1]['ids'] === [12, 34], 'ids parsed/deduped/positive-only, got ' . implode(',', $out[1]['ids']));

// 4. IDs are dropped for the conditions that never read them, so nothing comes
// back to the admin screen looking like it narrows the rule.
$out3 = $repo->normalizeRules([
    ['handle' => 'a', 'condition' => 'everywhere', 'ids' => '12, 34'],
    ['handle' => 'b', 'condition' => 'mobile', 'ids' => '12'],
    ['handle' => 'c', 'condition' => 'not_singular', 'ids' => '12'],
]);
assert($out3[0]['ids'] === [], 'everywhere must not keep ids');
assert($out3[1]['ids'] === [], 'mobile must not keep ids');
assert($out3[2]['ids'] === [12], 'not_singular must keep ids');

// 5. Non-array input is safe.
assert($repo->normalizeRules('garbage') === []);
assert($repo->normalizeRules(['not-a-row', 42]) === []);

// 6. single_post_type: post type allowlisted against public types, empty otherwise,
// and dropped for every other condition. WooCommerce conditions are accepted.
$out4 = $repo->normalizeRules([
    ['handle' => 'a', 'condition' => 'single_post_type', 'post_type' => 'product'],
    ['handle' => 'b', 'condition' => 'single_post_type', 'post_type' => 'shop_order'],
    ['handle' => 'c', 'condition' => 'everywhere', 'post_type' => 'product'],
    ['handle' => 'd', 'condition' => 'wc_checkout'],
]);
assert($out4[0]['post_type'] === 'product', 'public post type kept');
assert($out4[1]['post_type'] === '', 'non-public post type rejected');
assert($out4[2]['post_type'] === '', 'post type only kept for single_post_type');
assert($out4[3]['condition'] === 'wc_checkout', 'WooCommerce condition accepted');

// 7. deregister is opt-in per rule.
$out5 = $repo->normalizeRules([['handle' => 'a'], ['handle' => 'b', 'deregister' => '1'], ['handle' => 'c', 'deregister' => true]]);
assert($out5[0]['deregister'] === false, 'deregister defaults to off');
assert($out5[1]['deregister'] === true && $out5[2]['deregister'] === true, 'deregister honoured');

// 8. Discovered handles: known entries keep their place, the cap drops the oldest.
$seen = ['script|a' => 'a.js', 'style|b' => 'b.css'];
assert(HandleDiscovery::merge($seen, ['script|a' => 'a.js']) === $seen, 'revisit must not change the option');
$many = [];
for ($i = 0; $i < HandleDiscovery::MAX; $i++) {
    $many['script|h' . $i] = '';
}
$capped = HandleDiscovery::merge($many, ['style|new' => 'n.css']);
assert(count($capped) === HandleDiscovery::MAX, 'cap holds');
assert(isset($capped['style|new']) && ! isset($capped['script|h0']), 'oldest dropped, newest kept');

// 9. Upgrade from 1.1.x: rules saved before 1.2.0 keep deregistering; a rule
// already carrying the key is left alone; a second boot changes nothing.
$GLOBALS['test_options'] = [
    'plogins_assets_db_version' => '1.1.2',
    SettingsRepository::OPTION  => ['enabled' => true, 'rules' => [
        ['handle' => 'old', 'type' => 'script', 'condition' => 'everywhere', 'ids' => []],
        ['handle' => 'new', 'type' => 'script', 'condition' => 'everywhere', 'ids' => [], 'deregister' => false],
    ]],
];
(new Migrator())->maybeMigrate();
$migrated = $GLOBALS['test_options'][SettingsRepository::OPTION]['rules'];
assert(($migrated[0]['deregister'] ?? null) === true, 'pre-1.2.0 rule must keep deregistering');
assert($migrated[1]['deregister'] === false, 'explicit deregister=false must stay');
assert($GLOBALS['test_options']['plogins_assets_db_version'] === '1.2.0', 'db version stored');

echo "OK - all normalizeRules and migration assertions passed\n";
}
