<?php
/**
 * Main plugin class.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_Connector
{
    private static ?self $instance = null;

    private OpenMES_Settings $settings;
    private OpenMES_Product_Fields $product_fields;
    private OpenMES_Order_Handler $order_handler;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->settings       = new OpenMES_Settings();
        $this->product_fields = new OpenMES_Product_Fields();
        $this->order_handler  = new OpenMES_Order_Handler();
    }

    public function boot(): void
    {
        $this->settings->register();
        $this->product_fields->register();
        $this->order_handler->register();
    }

    public static function activate(): void
    {
        // Default options
        if (get_option(OpenMES_Settings::OPTION_NAME) === false) {
            update_option(OpenMES_Settings::OPTION_NAME, [
                'enabled'         => 0,
                'api_url'         => '',
                'api_token'       => '',
                'default_line_id' => 0,
            ]);
        }
    }

    public static function deactivate(): void
    {
        // Intentionally keep options on deactivate; only remove on uninstall.
    }
}
