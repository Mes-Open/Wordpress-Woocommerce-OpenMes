# OpenMES Connector for WooCommerce

A WordPress/WooCommerce plugin that automatically creates **work orders** in [OpenMES](https://github.com/Mes-Open/OpenMes) whenever a customer places an order containing a product marked as "manufactured".

> **Part of the OpenMES ecosystem** — see the main project at [github.com/Mes-Open/OpenMes](https://github.com/Mes-Open/OpenMes)

---

## Features

- Flag any product as *"Manufacture in OpenMES"* directly from the WooCommerce product edit page
- Assign products to specific production lines (or use a global default)
- Once the order's payment is confirmed (status moves to `processing` or `completed`), a work order is automatically created in OpenMES via API
- Auto-restock work orders when stock drops to exactly 0 for backorder-enabled manufactured products
- Full order metadata (order number, product name, customer ID) stored in `extra_data`
- All API calls logged via WooCommerce's logger (**WooCommerce → Status → Logs**, source `openmes-connector`)
- HPOS (High-Performance Order Storage) compatible

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 7.0+ |
| PHP | 7.4+ |
| OpenMES | any version with REST API |

---

## Installation

1. Copy the `openmes-connector/` directory into `wp-content/plugins/`
2. Go to **Plugins** in WordPress admin and activate **OpenMES Connector for WooCommerce**
3. Go to **Settings → OpenMES** to configure

Or zip the `openmes-connector/` directory and upload via **Plugins → Add New → Upload Plugin**.

---

## Configuration

1. Go to **Settings → OpenMES**
2. Fill in:
   - **OpenMES API URL** — e.g. `https://demo.getopenmes.com`
   - **API Token** — generate one in OpenMES: **Settings → API Tokens**
   - **Default production line** — where orders go when no specific line is assigned
3. Tick **Enable integration** and save

---

## Marking products as manufactured

1. Open any product in **Products**
2. In the **Product data** box, scroll to the **OpenMES — Manufacturing** section
3. Tick **Manufacture this product**
4. Optionally select a production line for this product (or leave as "default")
5. Update

---

## How it works

```
Customer places order
        │
        ▼
Payment is confirmed — order status becomes `processing` (or `completed`)
        │
        ▼
For each line item → is "manufacture" flag set?
  No  → skip
  Yes → POST /api/v1/work-orders to OpenMES
        {
          "order_no":    "WC-1234-42",
          "planned_qty": 2,
          "line_id":     3,
          "description": "WooCommerce order #1234 — Widget XL",
          "extra_data":  { "source": "woocommerce", ... }
        }
```

Work orders are created at most once per order (idempotency flag is stored on the order as `_openmes_work_orders_created`), so transitions like `processing → completed` won't duplicate them.

When stock for a manufactured product drops to exactly 0 (and backorders are allowed), a separate `WC-RESTOCK-…` work order is created. Subsequent decrements into negative stock do not re-trigger.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Work orders not created | Check **Enable integration** is on and the token is valid |
| `HTTP error` in logs | Verify the OpenMES URL is reachable from the WP server |
| `401 Unauthorized` | Token expired — generate a new one in OpenMES |
| `422 Unprocessable` | Duplicate `order_no` — order already exists in OpenMES |

Logs: **WooCommerce → Status → Logs** (filter by source `openmes-connector`).

---

## Related

- [OpenMES — main repository](https://github.com/Mes-Open/OpenMes)
- [OpenMES Connector for PrestaShop](https://github.com/Mes-Open/prestashop-openmmes)

---

## License

MIT
