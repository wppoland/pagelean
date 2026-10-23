<?php
/**
 * Default settings, merged under the option key `plogins_assets_settings`.
 *
 * @package PloginsAssets
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Master switch. When off, no dequeueing happens at all.
    'enabled'   => true,
    // Test mode: rules apply only to logged-in users with manage_options.
    'test_mode' => false,
    // Ordered list of rules. Each rule dequeues one handle when its condition
    // matches the current front-end request. Shape enforced by
    // PloginsAssets\Settings\SettingsRepository::normalize().
    //
    //   [
    //     'handle'    => 'contact-form-7',
    //     'type'      => 'script',        // 'script' | 'style'
    //     'condition' => 'not_singular',  // see SettingsRepository::CONDITIONS
    //     'ids'       => [12, 34],         // post/page IDs, only for *_singular
    //     'post_type' => 'product',        // only for single_post_type
    //     'deregister'=> false,            // also deregister (breaks dependents)
    //   ]
    'rules'     => [],
];
