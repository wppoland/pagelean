<?php

declare(strict_types=1);

namespace PloginsAssets\Admin;

use PloginsAssets\Contract\HasHooks;
use PloginsAssets\Frontend\HandleDiscovery;
use PloginsAssets\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Settings screen under Settings -> Pagelean. Renders the master switch
 * and the repeatable rule table, and handles the nonce-guarded save.
 */
final class SettingsPage implements HasHooks
{
    private const SLUG   = 'pagelean';
    private const ACTION = 'plogins_assets_save';

    private const EMPTY_RULE = ['handle' => '', 'type' => 'script', 'condition' => 'everywhere', 'ids' => [], 'post_type' => '', 'deregister' => false];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly HandleDiscovery $discovery,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_' . self::ACTION, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_notices', [$this, 'testModeNotice']);
    }

    /**
     * Shown on every admin screen while test mode is on, so nobody forgets the
     * rules are not reaching visitors yet.
     */
    public function testModeNotice(): void
    {
        if (! current_user_can('manage_options') || ! $this->settings->isEnabled() || ! $this->settings->isTestMode()) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
            esc_html__('Pagelean test mode is on.', 'pagelean'),
            esc_html__('Rules apply only to logged-in administrators. Visitors still get every script and style.', 'pagelean'),
            esc_url(admin_url('admin.php?page=' . self::SLUG)),
            esc_html__('Turn it off when the pages look right.', 'pagelean')
        );
    }

    public function addMenu(): void
    {
        add_menu_page(
            __('Pagelean', 'pagelean'),
            __('Pagelean', 'pagelean'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-performance',
            81
        );
    }

    public function assets(string $hook): void
    {
        if ('toplevel_page_' . self::SLUG !== $hook) {
            return;
        }

        wp_enqueue_style(
            'plogins-assets-admin',
            PLOGINS_ASSETS_URL . 'assets/css/admin.css',
            [],
            \PloginsAssets\VERSION
        );
        wp_enqueue_script(
            'plogins-assets-admin',
            PLOGINS_ASSETS_URL . 'assets/js/admin.js',
            [],
            \PloginsAssets\VERSION,
            true
        );
    }

    public function save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'pagelean'));
        }

        check_admin_referer(self::ACTION);

        $enabled  = ! empty($_POST['enabled']);
        $testMode = ! empty($_POST['test_mode']);

        // Every field is single-line text, sanitised here on read; the
        // repository's normalizeRules() then validates each one.
        $rawRules = isset($_POST['rules']) && is_array($_POST['rules'])
            ? map_deep(wp_unslash($_POST['rules']), 'sanitize_text_field')
            : [];

        $this->settings->save($enabled, $rawRules, $testMode);

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'updated' => '1'],
            admin_url('admin.php')
        ));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $enabled  = $this->settings->isEnabled();
        $testMode = $this->settings->isTestMode();
        $rules    = $this->settings->rules();
        if ([] === $rules) {
            // One empty starter row so the table is never blank.
            $rules = [self::EMPTY_RULE];
        }

        ?>
        <div class="wrap plogins-assets">
            <h1><?php esc_html_e('Pagelean', 'pagelean'); ?></h1>
            <p class="description">
                <?php esc_html_e('Remove specific scripts and styles from the pages that do not need them. Lighter pages load faster.', 'pagelean'); ?>
            </p>

            <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameter, only the literal '1' shows the notice.
            if (isset($_GET['updated']) && '1' === sanitize_key(wp_unslash($_GET['updated']))) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'pagelean'); ?></p></div>
            <?php endif; ?>

            <div class="notice notice-warning inline">
                <p><?php esc_html_e('Removing a handle that another script depends on can break page functionality. Change one rule at a time and check the front end.', 'pagelean'); ?></p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <?php wp_nonce_field(self::ACTION); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>>
                        <strong><?php esc_html_e('Enable conditional asset loading', 'pagelean'); ?></strong>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="test_mode" value="1" aria-describedby="plogins-assets-test-mode-desc" <?php checked($testMode); ?>>
                        <strong><?php esc_html_e('Test mode', 'pagelean'); ?></strong>
                    </label>
                    <span id="plogins-assets-test-mode-desc" class="description">
                        <?php esc_html_e('Apply the rules only for logged-in administrators. Everyone else gets the untouched page, so you can check the front end before visitors see the change.', 'pagelean'); ?>
                    </span>
                </p>

                <table class="widefat plogins-assets-rules">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Handle', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Type', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Remove when', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Post/Page IDs', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Post type', 'pagelean'); ?></th>
                            <th><?php esc_html_e('Also deregister', 'pagelean'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="plogins-assets-rows">
                        <?php foreach ($rules as $i => $rule) : ?>
                            <?php $this->renderRow((int) $i, $rule); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="plogins-assets-add">
                        <?php esc_html_e('+ Add rule', 'pagelean'); ?>
                    </button>
                </p>

                <?php $this->renderHandleHints(); ?>

                <?php submit_button(__('Save rules', 'pagelean')); ?>
            </form>

            <template id="plogins-assets-template">
                <?php $this->renderRow(0, self::EMPTY_RULE, true); ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param array{handle: string, type: string, condition: string, ids: array<int, int>, post_type: string, deregister: bool} $rule
     */
    private function renderRow(int $index, array $rule, bool $template = false): void
    {
        $name = $template ? 'rules[__INDEX__]' : 'rules[' . $index . ']';
        $hasWc = function_exists('is_shop');
        ?>
        <tr class="plogins-assets-row">
            <td>
                <input type="text" class="regular-text" list="plogins-assets-handles"
                    name="<?php echo esc_attr($name); ?>[handle]"
                    value="<?php echo esc_attr($rule['handle']); ?>"
                    placeholder="<?php esc_attr_e('e.g. contact-form-7', 'pagelean'); ?>">
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[type]">
                    <option value="script" <?php selected($rule['type'], 'script'); ?>><?php esc_html_e('Script (JS)', 'pagelean'); ?></option>
                    <option value="style" <?php selected($rule['type'], 'style'); ?>><?php esc_html_e('Style (CSS)', 'pagelean'); ?></option>
                </select>
            </td>
            <td>
                <select name="<?php echo esc_attr($name); ?>[condition]">
                    <?php foreach ($this->settings->conditions() as $cond) : ?>
                        <?php
                        // WooCommerce conditions only while WooCommerce runs, but a
                        // saved rule keeps its own condition visible.
                        if (! $hasWc && $cond !== $rule['condition'] && in_array($cond, SettingsRepository::WC_CONDITIONS, true)) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo esc_attr($cond); ?>" <?php selected($rule['condition'], $cond); ?>>
                            <?php echo esc_html($this->conditionLabel($cond)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <?php
            // The dequeue engine reads these ids for the two single post/page
            // conditions only. The field used to stay open on every row, so an
            // admin could pick "Everywhere", type "12, 34", save, and see it come
            // back looking active while visitors lost the handle on the whole
            // site. Closed unless the chosen condition can honour it.
            $usesIds = $this->settings->conditionUsesIds($rule['condition']);
            ?>
            <td class="plogins-assets-ids">
                <input type="text" class="regular-text" name="<?php echo esc_attr($name); ?>[ids]"
                    value="<?php echo esc_attr(implode(', ', $rule['ids'])); ?>"
                    placeholder="<?php esc_attr_e('optional, e.g. 12, 34', 'pagelean'); ?>"
                    <?php disabled(! $usesIds); ?>>
                <span class="description plogins-assets-ids-note"<?php echo $usesIds ? ' hidden' : ''; ?>>
                    <?php esc_html_e('Used only by the two single posts/pages conditions.', 'pagelean'); ?>
                </span>
            </td>
            <?php $usesPostType = 'single_post_type' === $rule['condition']; ?>
            <td>
                <select name="<?php echo esc_attr($name); ?>[post_type]" aria-label="<?php esc_attr_e('Post type', 'pagelean'); ?>" <?php disabled(! $usesPostType); ?>>
                    <option value=""><?php esc_html_e('Choose a post type', 'pagelean'); ?></option>
                    <?php foreach ($this->settings->publicPostTypes() as $slug => $label) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($rule['post_type'], $slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[deregister]" value="1" <?php checked($rule['deregister']); ?>>
                    <span class="screen-reader-text"><?php esc_html_e('Also deregister the handle', 'pagelean'); ?></span>
                </label>
            </td>
            <td>
                <button type="button" class="button-link plogins-assets-remove" aria-label="<?php esc_attr_e('Remove rule', 'pagelean'); ?>">&times;</button>
            </td>
        </tr>
        <?php
    }

    private function conditionLabel(string $cond): string
    {
        $labels = [
            'everywhere'   => __('Everywhere', 'pagelean'),
            'front_page'   => __('On the front page', 'pagelean'),
            'blog_home'    => __('On the blog posts index', 'pagelean'),
            'is_singular'  => __('On single posts/pages (matching IDs)', 'pagelean'),
            'not_singular' => __('Except single posts/pages (matching IDs)', 'pagelean'),
            'mobile'       => __('On mobile devices', 'pagelean'),
            'desktop'      => __('On desktop devices', 'pagelean'),
            'single_post_type' => __('On single entries of a post type', 'pagelean'),
            'wc_shop'      => __('On the WooCommerce shop page', 'pagelean'),
            'wc_product'   => __('On WooCommerce product pages', 'pagelean'),
            'wc_cart'      => __('On the WooCommerce cart', 'pagelean'),
            'wc_checkout'  => __('On the WooCommerce checkout', 'pagelean'),
        ];

        return $labels[$cond] ?? $cond;
    }

    /**
     * A datalist of handle hints so admins get autocomplete instead of guessing.
     * Front-end handles recorded by HandleDiscovery come first (with their
     * source file as the label); handles registered on this admin request fill
     * the rest. It is only a hint; any handle can be typed.
     */
    private function renderHandleHints(): void
    {
        $hints = [];
        foreach ($this->discovery->seen() as $key => $src) {
            [$type, $handle] = array_pad(explode('|', (string) $key, 2), 2, '');
            if ('' === $handle || isset($hints[$handle])) {
                continue;
            }
            $kind           = 'style' === $type ? __('Style', 'pagelean') : __('Script', 'pagelean');
            $hints[$handle] = '' === $src ? $kind : $kind . ': ' . $src;
        }
        $frontCount = count($hints);

        $registered = array_merge(array_keys(wp_scripts()->registered), array_keys(wp_styles()->registered));
        foreach ($registered as $handle) {
            $hints[(string) $handle] ??= '';
        }
        ksort($hints);

        echo '<datalist id="plogins-assets-handles">';
        foreach ($hints as $handle => $label) {
            echo '<option value="' . esc_attr((string) $handle) . '"' . ('' === $label ? '' : ' label="' . esc_attr($label) . '"') . '></option>';
        }
        echo '</datalist>';

        echo '<p class="description">';
        if (0 === $frontCount) {
            esc_html_e('Tip: visit a few pages of your site while logged in as an administrator. Pagelean records the scripts and styles they load and suggests them here.', 'pagelean');
        } else {
            echo esc_html(sprintf(
                /* translators: %d: number of handles recorded on the front end */
                _n('%d handle recorded from your front-end pages is suggested as you type. Visit more pages to add more.', '%d handles recorded from your front-end pages are suggested as you type. Visit more pages to add more.', $frontCount, 'pagelean'),
                $frontCount
            ));
        }
        echo '</p>';
    }
}
