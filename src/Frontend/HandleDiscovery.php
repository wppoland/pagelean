<?php

declare(strict_types=1);

namespace PloginsAssets\Frontend;

use PloginsAssets\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Records the script and style handles a front-end page actually loads while an
 * administrator browses it, so the settings screen can suggest the handles the
 * visitors get (the admin screen itself only knows the admin-side handles).
 *
 * Stored in a non-autoloaded option, capped at MAX entries (oldest dropped
 * first), and only written when a page shows a handle not seen before.
 */
final class HandleDiscovery implements HasHooks
{
    public const OPTION = 'plogins_assets_seen_handles';
    public const MAX    = 500;

    /** @var array<string, string> "type|handle" => src, collected before our own rules run */
    private array $early = [];

    public function registerHooks(): void
    {
        // Priority 99: one step before AssetManager::apply() (100) so handles a
        // rule removes are still recorded.
        add_action('wp_enqueue_scripts', [$this, 'collectEarly'], 99);
        // Late in the footer: everything printed on the page, including handles
        // enqueued from shortcodes and blocks in the body.
        add_action('wp_print_footer_scripts', [$this, 'record'], 1000);
    }

    public function collectEarly(): void
    {
        if ($this->shouldRecord()) {
            $this->early = $this->currentHandles();
        }
    }

    public function record(): void
    {
        if (! $this->shouldRecord()) {
            return;
        }

        $seen   = $this->seen();
        $merged = self::merge($seen, $this->early + $this->currentHandles());
        if ($merged !== $seen) {
            update_option(self::OPTION, $merged, false);
        }
    }

    /**
     * Recorded handles, "type|handle" => src.
     *
     * @return array<string, string>
     */
    public function seen(): array
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? array_map('strval', $stored) : [];
    }

    /**
     * Add new entries after the known ones, then keep only the newest MAX.
     * Known entries keep their position so a revisit does not rewrite the option.
     *
     * @param array<string, string> $seen
     * @param array<string, string> $found
     * @return array<string, string>
     */
    public static function merge(array $seen, array $found): array
    {
        $merged = $seen + $found;
        if (count($merged) > self::MAX) {
            $merged = array_slice($merged, -self::MAX, null, true);
        }

        return $merged;
    }

    private function shouldRecord(): bool
    {
        return ! is_admin() && current_user_can('manage_options');
    }

    /** @return array<string, string> */
    private function currentHandles(): array
    {
        $out = [];
        foreach (['script' => wp_scripts(), 'style' => wp_styles()] as $type => $deps) {
            $handles = array_merge($deps->queue, $deps->done);
            foreach (array_unique($handles) as $handle) {
                $dep = $deps->registered[$handle] ?? null;
                $src = null !== $dep && is_string($dep->src) ? $dep->src : '';
                $out[$type . '|' . $handle] = substr($src, 0, 255);
            }
        }

        return $out;
    }
}
