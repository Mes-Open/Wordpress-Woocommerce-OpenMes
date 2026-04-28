<?php
/**
 * Plugin Name:       OpenMES Connector for WooCommerce
 * Plugin URI:        https://github.com/Mes-Open/OpenMes
 * Description:       Automatically creates work orders in OpenMES when WooCommerce orders contain manufactured products. Also creates restock work orders when stock drops to 0.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            OpenMES Team
 * License:           MIT
 * Text Domain:       openmes-connector
 * Domain Path:       /languages
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.5
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('OPENMES_CONNECTOR_VERSION', '1.0.0');
define('OPENMES_CONNECTOR_FILE', __FILE__);
define('OPENMES_CONNECTOR_DIR', plugin_dir_path(__FILE__));
define('OPENMES_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-logger.php';
require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-api-client.php';
require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-settings.php';
require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-product-fields.php';
require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-order-handler.php';
require_once OPENMES_CONNECTOR_DIR . 'includes/class-openmes-connector.php';

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>'
                . esc_html__(
                    'OpenMES Connector requires WooCommerce to be installed and active.',
                    'openmes-connector'
                )
                . '</p></div>';
        });
        return;
    }

    load_plugin_textdomain(
        'openmes-connector',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    OpenMES_Connector::instance()->boot();
});

register_activation_hook(__FILE__, [OpenMES_Connector::class, 'activate']);
register_deactivation_hook(__FILE__, [OpenMES_Connector::class, 'deactivate']);
