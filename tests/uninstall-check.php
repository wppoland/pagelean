<?php
/**
 * Deleting the plugin must remove its options from every site of a network.
 * The settings option is shared with an add-on, so it goes only when no add-on
 * is on disk, judged once for the whole network from a fresh plugin list (a
 * bulk delete may have removed the add-on earlier in the same request).
 *
 * Run: php tests/uninstall-check.php
 */

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'pagelean/pagelean.php');

$blog        = 1;
$log         = [];
$lists       = 0;
$addon       = false;
$cachedList  = null;

function is_multisite(): bool { return true; }
function get_sites(array $args): array { return [1, 2, 3]; }
function switch_to_blog(int $id): void { $GLOBALS['blog'] = $id; }
function restore_current_blog(): void { switch_to_blog(1); }
function delete_option(string $name): bool { $GLOBALS['log'][] = $GLOBALS['blog'] . ':' . $name; return true; }
// Like core: the list is cached for the request until wp_cache_delete('plugins', 'plugins').
function wp_cache_delete(string $key, string $group = ''): bool
{
    if ('plugins' === $key && 'plugins' === $group) {
        $GLOBALS['cachedList'] = null;
    }

    return true;
}
function get_plugins(): array
{
    ++$GLOBALS['lists'];
    $GLOBALS['cachedList'] ??= ['pagelean/pagelean.php' => ['TextDomain' => 'pagelean']]
        + ($GLOBALS['addon'] ? ['pagelean-pro/pagelean-pro.php' => ['TextDomain' => 'pagelean-pro']] : []);

    return $GLOBALS['cachedList'];
}

$file = __DIR__ . '/../uninstall.php';
if (! is_file($file)) {
    echo "FAIL: there is no uninstall.php, so deleting the plugin leaves its options behind\n";
    exit(1);
}

/** @return list<string> */
function run(bool $addon, bool $staleCache = false): array
{
    $GLOBALS['log']        = [];
    $GLOBALS['lists']      = 0;
    $GLOBALS['cachedList'] = null;
    if ($staleCache) {
        // A bulk delete listed the add-on, then uninstalled and deleted it first.
        $GLOBALS['addon'] = true;
        get_plugins();
        $GLOBALS['lists'] = 0;
    }
    $GLOBALS['addon'] = $addon;
    include $GLOBALS['file'];
    $log = $GLOBALS['log'];
    sort($log);

    return $log;
}

function expected(bool $withSettings): array
{
    $out = [];
    foreach ([1, 2, 3] as $site) {
        array_push($out, "{$site}:plogins_assets_db_version", "{$site}:plogins_assets_seen_handles");
        if ($withSettings) {
            $out[] = "{$site}:plogins_assets_settings";
        }
    }
    sort($out);

    return $out;
}

$fail = [];
foreach (
    [
        'no add-on'                         => [run(false), expected(true)],
        'add-on on disk'                    => [run(true), expected(false)],
        'add-on removed earlier in request' => [run(false, true), expected(true)],
    ] as $case => [$got, $want]
) {
    if ($got !== $want) {
        $fail[] = "{$case}: missing " . implode(', ', array_diff($want, $got)) . '; extra ' . implode(', ', array_diff($got, $want));
    }
}
run(false);
if (1 !== $lists) {
    $fail[] = "the plugin list was read {$lists} times, expected once for the whole network";
}

if ($fail !== []) {
    echo 'FAIL: ' . implode("\nFAIL: ", $fail) . "\n";
    exit(1);
}

echo "OK: uninstall cleans every site and leaves the shared settings to an installed add-on\n";
