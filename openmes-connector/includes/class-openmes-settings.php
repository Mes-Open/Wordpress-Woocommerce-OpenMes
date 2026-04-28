<?php
/**
 * Settings page (Settings → OpenMES).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_Settings
{
    public const OPTION_NAME = 'openmes_connector_settings';
    public const PAGE_SLUG   = 'openmes-connector';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'options-general.php',
            __('OpenMES Connector', 'openmes-connector'),
            __('OpenMES', 'openmes-connector'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'openmes_connector_group',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default'           => [
                    'enabled'         => 0,
                    'api_url'         => '',
                    'api_token'       => '',
                    'default_line_id' => 0,
                ],
            ]
        );
    }

    /**
     * @param mixed $input
     */
    public function sanitize($input): array
    {
        $existing = get_option(self::OPTION_NAME, []);
        $input    = is_array($input) ? $input : [];

        $api_url = isset($input['api_url']) ? rtrim(esc_url_raw((string) $input['api_url']), '/') : '';

        // Keep existing token when the field is left blank
        $token = isset($input['api_token']) ? trim((string) $input['api_token']) : '';
        if ($token === '' && isset($existing['api_token'])) {
            $token = (string) $existing['api_token'];
        }

        return [
            'enabled'         => !empty($input['enabled']) ? 1 : 0,
            'api_url'         => $api_url,
            'api_token'       => $token,
            'default_line_id' => isset($input['default_line_id']) ? (int) $input['default_line_id'] : 0,
        ];
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $opts = wp_parse_args(get_option(self::OPTION_NAME, []), [
            'enabled'         => 0,
            'api_url'         => '',
            'api_token'       => '',
            'default_line_id' => 0,
        ]);

        $lines        = self::fetch_lines();
        $current_line = (int) $opts['default_line_id'];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('OpenMES Connector', 'openmes-connector'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('openmes_connector_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="openmes_enabled">
                                <?php esc_html_e('Enable integration', 'openmes-connector'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="openmes_enabled"
                                       name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]"
                                       value="1"
                                       <?php checked(1, (int) $opts['enabled']); ?>>
                                <?php esc_html_e('Send work orders to OpenMES', 'openmes-connector'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="openmes_api_url">
                                <?php esc_html_e('OpenMES API URL', 'openmes-connector'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url"
                                   id="openmes_api_url"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_url]"
                                   value="<?php echo esc_attr($opts['api_url']); ?>"
                                   class="regular-text"
                                   placeholder="https://demo.getopenmes.com">
                            <p class="description">
                                <?php esc_html_e('Base URL of your OpenMES instance.', 'openmes-connector'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="openmes_api_token">
                                <?php esc_html_e('API Token', 'openmes-connector'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="password"
                                   id="openmes_api_token"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[api_token]"
                                   value=""
                                   class="regular-text"
                                   autocomplete="new-password">
                            <p class="description">
                                <?php
                                if ($opts['api_token'] !== '') {
                                    esc_html_e('A token is currently saved. Leave blank to keep it.', 'openmes-connector');
                                } else {
                                    esc_html_e('Bearer token from OpenMES → Settings → API Tokens.', 'openmes-connector');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="openmes_default_line_id">
                                <?php esc_html_e('Default production line', 'openmes-connector'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="openmes_default_line_id"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[default_line_id]">
                                <option value="0">
                                    <?php esc_html_e('— No default line —', 'openmes-connector'); ?>
                                </option>
                                <?php foreach ($lines as $line) : ?>
                                    <option value="<?php echo esc_attr((string) $line['id']); ?>"
                                        <?php selected($current_line, (int) $line['id']); ?>>
                                        <?php echo esc_html($line['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Used when a product has no specific line assigned.', 'openmes-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Fetch and cache production lines for select dropdowns.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public static function fetch_lines(): array
    {
        $client = OpenMES_API_Client::from_settings();
        if ($client === null) {
            return [];
        }
        return $client->fetch_lines();
    }
}
