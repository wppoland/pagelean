<?php

declare(strict_types=1);

namespace PloginsAssets\Frontend;

use PloginsAssets\Contract\HasHooks;
use PloginsAssets\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Applies the configured rules on the front end: for every rule whose condition
 * matches the current request, dequeue its handle so it is not printed. A rule
 * only deregisters the handle too when the admin ticked "Also deregister" on
 * it, because a deregistered handle silently drops every script that depends
 * on it, while a dequeued one is still printed when something else needs it.
 *
 * Runs on wp_enqueue_scripts at a late priority (after themes and plugins have
 * enqueued) for both scripts and styles. Never runs in the admin.
 */
final class AssetManager implements HasHooks
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function registerHooks(): void
    {
        if (! $this->settings->isEnabled()) {
            return;
        }

        // Priority 100: run after the vast majority of enqueue callbacks so the
        // handles we want to remove are already registered.
        add_action('wp_enqueue_scripts', [$this, 'apply'], 100);
    }

    public function apply(): void
    {
        if (is_admin()) {
            return;
        }

        // Test mode: only administrators see the lean page.
        if ($this->settings->isTestMode() && ! current_user_can('manage_options')) {
            return;
        }

        foreach ($this->settings->rules() as $rule) {
            if (! $this->matches($rule)) {
                continue;
            }

            if ('style' === $rule['type']) {
                wp_dequeue_style($rule['handle']);
                if ($rule['deregister']) {
                    wp_deregister_style($rule['handle']);
                }
            } else {
                wp_dequeue_script($rule['handle']);
                if ($rule['deregister']) {
                    wp_deregister_script($rule['handle']);
                }
            }
        }
    }

    /**
     * @param array{handle: string, type: string, condition: string, ids: array<int, int>, post_type: string, deregister: bool} $rule
     */
    private function matches(array $rule): bool
    {
        switch ($rule['condition']) {
            case 'everywhere':
                return true;
            case 'front_page':
                return is_front_page();
            case 'blog_home':
                return is_home();
            case 'mobile':
                return wp_is_mobile();
            case 'desktop':
                return ! wp_is_mobile();
            case 'is_singular':
                return is_singular() && $this->idMatches($rule['ids']);
            case 'not_singular':
                // Everything except the targeted singles. With no ids that is
                // simply "not a single post/page".
                if ([] === $rule['ids']) {
                    return ! is_singular();
                }

                return ! (is_singular() && $this->idMatches($rule['ids']));
            case 'single_post_type':
                return '' !== $rule['post_type'] && is_singular($rule['post_type']);
            case 'wc_shop':
                return function_exists('is_shop') && is_shop();
            case 'wc_product':
                return function_exists('is_product') && is_product();
            case 'wc_cart':
                return function_exists('is_cart') && is_cart();
            case 'wc_checkout':
                return function_exists('is_checkout') && is_checkout();
        }

        return false;
    }

    /**
     * True when no IDs are configured (match any single) or the current queried
     * object is in the list.
     *
     * @param array<int, int> $ids
     */
    private function idMatches(array $ids): bool
    {
        if ([] === $ids) {
            return true;
        }

        return in_array(get_queried_object_id(), $ids, true);
    }
}
