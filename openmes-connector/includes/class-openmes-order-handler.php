<?php
/**
 * Hooks into WooCommerce orders and stock changes to create work orders.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_Order_Handler
{
    private const ORDER_WO_SENT_META = '_openmes_work_orders_created';

    /** @var int[] Product IDs that already had a WO created via order hook in this request */
    private array $order_wo_created_for = [];

    public function register(): void
    {
        // Fire only after payment is confirmed — `processing` for paid online orders,
        // `completed` to cover digital products that skip straight past `processing`.
        add_action('woocommerce_order_status_processing', [$this, 'on_order_paid'], 20, 2);
        add_action('woocommerce_order_status_completed', [$this, 'on_order_paid'], 20, 2);
        add_action('woocommerce_product_set_stock', [$this, 'on_product_stock_changed'], 20, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'on_product_stock_changed'], 20, 1);
    }

    /**
     * @param int           $order_id
     * @param WC_Order|null $order
     */
    public function on_order_paid($order_id, $order = null): void
    {
        try {
            if (!$this->is_enabled()) {
                return;
            }

            $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                return;
            }

            // Idempotency: an order can transition processing → completed (and back),
            // firing this hook multiple times. Only send work orders on the first pass.
            if ($order->get_meta(self::ORDER_WO_SENT_META) === 'yes') {
                return;
            }

            $client = OpenMES_API_Client::from_settings();
            if ($client === null) {
                OpenMES_Logger::warning(
                    sprintf('Integration not configured — skipping order #%d', $order->get_id())
                );
                return;
            }

            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }

                $product_id = $item->get_variation_id() ?: $item->get_product_id();
                $base_id    = $item->get_product_id();

                if (!OpenMES_Product_Fields::is_manufactured($base_id)) {
                    continue;
                }

                $this->create_order_work_order($client, $order, $item, $product_id, $base_id);
                $this->order_wo_created_for[] = $base_id;
                if ($product_id !== $base_id) {
                    $this->order_wo_created_for[] = $product_id;
                }
            }

            $order->update_meta_data(self::ORDER_WO_SENT_META, 'yes');
            $order->save();
        } catch (\Throwable $e) {
            OpenMES_Logger::error('Error in on_order_paid: ' . $e->getMessage());
        }
    }

    /**
     * @param WC_Product $product
     */
    public function on_product_stock_changed($product): void
    {
        try {
            if (!$this->is_enabled()) {
                return;
            }

            if (!$product instanceof WC_Product) {
                return;
            }

            // Trigger only on the transition to exactly zero. Already-negative stock
            // would otherwise fire a fresh restock WO on every subsequent decrement.
            $stock = $product->get_stock_quantity();
            if ($stock === null || (int) $stock !== 0) {
                return;
            }

            $product_id = $product->get_id();
            $base_id    = $product->is_type('variation')
                ? (int) $product->get_parent_id()
                : $product_id;

            if (in_array($product_id, $this->order_wo_created_for, true)
                || in_array($base_id, $this->order_wo_created_for, true)) {
                return;
            }

            if (!OpenMES_Product_Fields::is_manufactured($base_id)) {
                return;
            }

            if (!$product->backorders_allowed()) {
                return;
            }

            $client = OpenMES_API_Client::from_settings();
            if ($client === null) {
                OpenMES_Logger::warning(
                    sprintf('Integration not configured — skipping restock for product #%d', $product_id)
                );
                return;
            }

            $line_id   = OpenMES_Product_Fields::resolve_line_id($base_id);
            $variation = $product->is_type('variation') ? '-' . $product_id : '';
            $order_no  = 'WC-RESTOCK-' . $base_id . $variation
                . '-' . bin2hex(random_bytes(4));

            $payload = [
                'order_no'    => $order_no,
                'planned_qty' => 1.0,
                'description' => sprintf(
                    /* translators: %s: product name */
                    __('Auto restock — product out of stock: %s', 'openmes-connector'),
                    $product->get_name()
                ),
                'extra_data'  => [
                    'source'            => 'woocommerce',
                    'trigger'           => 'out_of_stock',
                    'wc_product_id'     => $product_id,
                    'wc_parent_id'      => $base_id,
                    'wc_product_name'   => $product->get_name(),
                    'wc_product_sku'    => $product->get_sku(),
                    'wc_stock_quantity' => 0,
                ],
            ];

            if ($line_id !== null) {
                $payload['line_id'] = $line_id;
            }

            $this->send_work_order($client, $payload);
        } catch (\Throwable $e) {
            OpenMES_Logger::error('Error in on_product_stock_changed: ' . $e->getMessage());
        }
    }

    private function create_order_work_order(
        OpenMES_API_Client $client,
        WC_Order $order,
        WC_Order_Item_Product $item,
        int $product_id,
        int $base_id
    ): void {
        $order_no = 'WC-' . $order->get_order_number() . '-' . $product_id;
        $product  = $item->get_product();
        $name     = $item->get_name();
        $sku      = $product instanceof WC_Product ? $product->get_sku() : '';

        $payload = [
            'order_no'    => $order_no,
            'planned_qty' => (float) $item->get_quantity(),
            'description' => sprintf(
                /* translators: 1: order number, 2: product name */
                __('WooCommerce order #%1$s — %2$s', 'openmes-connector'),
                $order->get_order_number(),
                $name
            ),
            'extra_data'  => [
                'source'           => 'woocommerce',
                'trigger'          => 'order',
                'wc_order_id'      => $order->get_id(),
                'wc_order_number'  => $order->get_order_number(),
                'wc_product_id'    => $product_id,
                'wc_parent_id'     => $base_id,
                'wc_product_name'  => $name,
                'wc_product_sku'   => $sku,
                'wc_customer_id'   => $order->get_customer_id(),
            ],
        ];

        $line_id = OpenMES_Product_Fields::resolve_line_id($base_id);
        if ($line_id !== null) {
            $payload['line_id'] = $line_id;
        }

        $this->send_work_order($client, $payload);
    }

    private function send_work_order(OpenMES_API_Client $client, array $payload): void
    {
        $response = $client->create_work_order($payload);
        $order_no = $payload['order_no'];

        if ($response === null || isset($response['error'])) {
            $msg = $response['message'] ?? 'Unknown error';
            OpenMES_Logger::error(
                sprintf('Failed to create work order %s: %s', $order_no, $msg)
            );
            return;
        }

        $wo_id = $response['data']['id'] ?? '?';
        OpenMES_Logger::info(
            sprintf('Work order created: %s (ID: %s)', $order_no, (string) $wo_id)
        );
    }

    private function is_enabled(): bool
    {
        $opts = get_option(OpenMES_Settings::OPTION_NAME, []);
        return !empty($opts['enabled']);
    }
}
