<?php
/**
 * Adds OpenMES fields to the WooCommerce product edit page.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_Product_Fields
{
    public const META_MANUFACTURE = '_openmes_manufacture';
    public const META_LINE_ID     = '_openmes_line_id';

    public function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_fields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_fields']);
    }

    public function render_fields(): void
    {
        global $post;

        $product_id  = isset($post->ID) ? (int) $post->ID : 0;
        $manufacture = $product_id ? get_post_meta($product_id, self::META_MANUFACTURE, true) : '';
        $line_id     = $product_id ? (int) get_post_meta($product_id, self::META_LINE_ID, true) : 0;
        $lines       = OpenMES_Settings::fetch_lines();

        echo '<div class="options_group openmes-options-group">';
        echo '<p class="form-field"><strong>' . esc_html__('OpenMES — Manufacturing', 'openmes-connector') . '</strong></p>';

        woocommerce_wp_checkbox([
            'id'            => self::META_MANUFACTURE,
            'value'         => $manufacture === 'yes' ? 'yes' : 'no',
            'cbvalue'       => 'yes',
            'label'         => __('Manufacture this product', 'openmes-connector'),
            'description'   => __('When enabled, a work order is created in OpenMES every time this product is ordered.', 'openmes-connector'),
            'desc_tip'      => false,
        ]);

        $options = ['0' => __('— Use default line —', 'openmes-connector')];
        foreach ($lines as $line) {
            $options[(string) $line['id']] = $line['name'];
        }

        woocommerce_wp_select([
            'id'          => self::META_LINE_ID,
            'value'       => (string) $line_id,
            'label'       => __('Production line', 'openmes-connector'),
            'options'     => $options,
            'description' => __('Override the default line for this product.', 'openmes-connector'),
            'desc_tip'    => true,
        ]);

        echo '</div>';
    }

    /**
     * @param WC_Product $product
     */
    public function save_fields($product): void
    {
        if (!$product instanceof WC_Product) {
            return;
        }

        $manufacture = isset($_POST[self::META_MANUFACTURE]) && $_POST[self::META_MANUFACTURE] === 'yes'
            ? 'yes'
            : 'no';

        $line_id = isset($_POST[self::META_LINE_ID]) ? (int) $_POST[self::META_LINE_ID] : 0;

        $product->update_meta_data(self::META_MANUFACTURE, $manufacture);
        $product->update_meta_data(self::META_LINE_ID, $line_id);
    }

    public static function is_manufactured(int $product_id): bool
    {
        return get_post_meta($product_id, self::META_MANUFACTURE, true) === 'yes';
    }

    public static function get_line_id(int $product_id): int
    {
        return (int) get_post_meta($product_id, self::META_LINE_ID, true);
    }

    public static function resolve_line_id(int $product_id): ?int
    {
        $line_id = self::get_line_id($product_id);
        if ($line_id <= 0) {
            $opts    = get_option(OpenMES_Settings::OPTION_NAME, []);
            $line_id = isset($opts['default_line_id']) ? (int) $opts['default_line_id'] : 0;
        }
        return $line_id > 0 ? $line_id : null;
    }
}
