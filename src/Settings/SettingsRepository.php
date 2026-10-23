<?php

declare(strict_types=1);

namespace PloginsAssets\Settings;

defined('ABSPATH') || exit;

/**
 * Reads, normalises and persists the plugin settings stored in the
 * `plogins_assets_settings` option. Single source of truth for the rule shape,
 * so the admin UI and the front-end dequeue engine agree on structure.
 */
final class SettingsRepository
{
    public const OPTION = 'plogins_assets_settings';

    /**
     * Supported rule conditions. `ids` is only consulted for the *_singular
     * conditions; every other condition ignores it.
     */
    public const CONDITIONS = [
        'everywhere',    // every front-end page
        'front_page',    // the site front page
        'blog_home',     // the posts index
        'is_singular',   // any single post/page (optionally limited to ids)
        'not_singular',  // everything except single posts/pages (or except ids)
        'mobile',        // wp_is_mobile() is true
        'desktop',       // wp_is_mobile() is false
        'single_post_type', // a single entry of one public post type
        'wc_shop',       // WooCommerce shop page
        'wc_product',    // WooCommerce single product
        'wc_cart',       // WooCommerce cart
        'wc_checkout',   // WooCommerce checkout
    ];

    /** Conditions that only make sense while WooCommerce is active. */
    public const WC_CONDITIONS = ['wc_shop', 'wc_product', 'wc_cart', 'wc_checkout'];

    /**
     * The only conditions AssetManager::matches() reads the ids list for. Every
     * other condition decides on the request alone.
     */
    private const ID_CONDITIONS = ['is_singular', 'not_singular'];

    /** @return array<int, string> */
    public function conditions(): array
    {
        return self::CONDITIONS;
    }

    /**
     * Whether a condition can actually act on the post/page IDs of a rule.
     */
    public function conditionUsesIds(string $condition): bool
    {
        return in_array($condition, self::ID_CONDITIONS, true);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    /**
     * Test mode: rules apply only to logged-in administrators, every other
     * visitor gets the untouched page.
     */
    public function isTestMode(): bool
    {
        return (bool) ($this->settings()['test_mode'] ?? false);
    }

    /**
     * Public post types a "single of post type" rule may target.
     *
     * @return array<string, string> slug => label
     */
    public function publicPostTypes(): array
    {
        $types = [];
        foreach (get_post_types(['public' => true], 'objects') as $slug => $obj) {
            $types[(string) $slug] = (string) $obj->labels->singular_name;
        }

        return $types;
    }

    /**
     * The configured rules, already normalised to canonical shape.
     *
     * @return array<int, array{handle: string, type: string, condition: string, ids: array<int, int>, post_type: string, deregister: bool}>
     */
    public function rules(): array
    {
        $rules = $this->settings()['rules'] ?? [];

        return is_array($rules) ? array_values($rules) : [];
    }

    /**
     * Settings array merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require PLOGINS_ASSETS_DIR . 'config/defaults.php';

        $merged          = array_merge($defaults, $stored);
        $merged['rules'] = $this->normalizeRules($merged['rules'] ?? []);

        return $merged;
    }

    /**
     * @param bool|null $testMode Null keeps the stored value (callers that only
     *                            touch the rules, such as the PRO add-on).
     */
    public function save(bool $enabled, mixed $rawRules, ?bool $testMode = null): void
    {
        update_option(self::OPTION, [
            'enabled'   => $enabled,
            'test_mode' => $testMode ?? $this->isTestMode(),
            'rules'     => $this->normalizeRules($rawRules),
        ]);
    }

    /**
     * Coerce a raw submitted rules array into the canonical shape, dropping any
     * row without a handle.
     *
     * @param mixed $raw
     * @return array<int, array{handle: string, type: string, condition: string, ids: array<int, int>, post_type: string, deregister: bool}>
     */
    public function normalizeRules(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $postTypes = null;

        $rules = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $handle = sanitize_text_field((string) ($row['handle'] ?? ''));
            $handle = trim($handle);
            if ('' === $handle) {
                continue;
            }

            $type = ('style' === ($row['type'] ?? '')) ? 'style' : 'script';

            $condition = (string) ($row['condition'] ?? 'everywhere');
            if (! in_array($condition, self::CONDITIONS, true)) {
                $condition = 'everywhere';
            }

            // Only the two singular conditions reach the ids downstream. Keeping
            // them on the other five was a quiet lie: the merchant typed "12, 34"
            // next to "Everywhere", saw it come back on reload as if it were in
            // force, and every shopper on every page still lost the handle. Store
            // nothing we cannot apply.
            $ids = $this->conditionUsesIds($condition)
                ? $this->normalizeIds($row['ids'] ?? '')
                : [];

            // Allowlisted against the public post types; anything else is
            // stored empty so the rule matches nothing and the select shows
            // "Choose a post type" instead of pretending to be in force.
            $postType = '';
            if ('single_post_type' === $condition) {
                $postTypes ??= $this->publicPostTypes();
                $candidate = sanitize_key((string) ($row['post_type'] ?? ''));
                $postType  = isset($postTypes[$candidate]) ? $candidate : '';
            }

            $rules[] = [
                'handle'     => $handle,
                'type'       => $type,
                'condition'  => $condition,
                'ids'        => $ids,
                'post_type'  => $postType,
                'deregister' => ! empty($row['deregister']),
            ];
        }

        return $rules;
    }

    /**
     * Accept either an array of IDs or a comma-separated string and return a
     * clean, unique, positive-int list.
     *
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeIds(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        // Only clean positive integers: a stray "-5" or "abc" is dropped, not
        // coerced (absint would turn -5 into 5 and target the wrong page).
        $ids = array_map(static fn (mixed $v): int => (int) $v, $raw);
        $ids = array_filter($ids, static fn (int $n): bool => $n > 0);

        return array_values(array_unique($ids));
    }
}
