<?php
namespace ClassFlowPro\Accounting;

use ClassFlowPro\Admin\Settings;
use WP_Error;

class QuickBooks
{
    private static function base_url(): string
    {
        $env = Settings::get('quickbooks_environment', 'production');
        return $env === 'sandbox' ? 'https://sandbox-quickbooks.api.intuit.com' : 'https://quickbooks.api.intuit.com';
    }

    private static function oauth_base(): string
    {
        return 'https://oauth.platform.intuit.com';
    }

    private static function get_tokens(): array
    {
        return get_option('cfp_quickbooks_tokens', []);
    }

    private static function save_tokens(array $tokens): void
    {
        update_option('cfp_quickbooks_tokens', $tokens, false);
    }

    public static function connect(\WP_REST_Request $req)
    {
        $client_id = Settings::get('quickbooks_client_id');
        $redirect_uri = Settings::get('quickbooks_redirect_uri');
        $scope = 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid profile email phone address';
        $state = wp_create_nonce('cfp_qb_oauth');
        $auth_url = self::oauth_base() . '/oauth2/v1/authorize?response_type=code&client_id=' . rawurlencode($client_id) . '&redirect_uri=' . rawurlencode($redirect_uri) . '&scope=' . rawurlencode($scope) . '&state=' . rawurlencode($state);
        return new \WP_REST_Response(['url' => $auth_url]);
    }

    public static function callback(\WP_REST_Request $req)
    {
        $code = sanitize_text_field($req->get_param('code'));
        $realm_id = sanitize_text_field($req->get_param('realmId'));
        $state = sanitize_text_field($req->get_param('state'));
        if (!wp_verify_nonce($state, 'cfp_qb_oauth')) {
            return new WP_Error('cfp_qb_invalid_state', __('Invalid OAuth state', 'classflow-pro'), ['status' => 400]);
        }
        $tokens = self::exchange_code_for_tokens($code);
        if (is_wp_error($tokens)) return $tokens;
        self::save_tokens($tokens);
        if ($realm_id) {
            $settings = get_option('cfp_settings', []);
            $settings['quickbooks_realm_id'] = $realm_id;
            update_option('cfp_settings', $settings, false);
        }
        return wp_redirect(admin_url('admin.php?page=classflow-pro'));
    }

    private static function exchange_code_for_tokens(string $code)
    {
        $client_id = Settings::get('quickbooks_client_id');
        $client_secret = Settings::get('quickbooks_client_secret');
        $redirect_uri = Settings::get('quickbooks_redirect_uri');
        $auth = base64_encode($client_id . ':' . $client_secret);
        $res = wp_remote_post(self::oauth_base() . '/oauth2/v1/tokens/bearer', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Accept' => 'application/json',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ],
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return $res;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($json['access_token'])) {
            return new WP_Error('cfp_qb_token_error', __('Failed to obtain QuickBooks tokens', 'classflow-pro'));
        }
        $json['expires_at'] = time() + (int)$json['expires_in'];
        $json['refresh_expires_at'] = time() + (int)($json['x_refresh_token_expires_in'] ?? 0);
        return $json;
    }

    private static function refresh_tokens_if_needed(): ?array
    {
        $tokens = self::get_tokens();
        if (empty($tokens)) return null;
        if (!empty($tokens['expires_at']) && $tokens['expires_at'] > (time() + 60)) {
            return $tokens;
        }
        $client_id = Settings::get('quickbooks_client_id');
        $client_secret = Settings::get('quickbooks_client_secret');
        $auth = base64_encode($client_id . ':' . $client_secret);
        $res = wp_remote_post(self::oauth_base() . '/oauth2/v1/tokens/bearer', [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Accept' => 'application/json',
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $tokens['refresh_token'],
            ],
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return null;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($json['access_token'])) return null;
        $json['expires_at'] = time() + (int)$json['expires_in'];
        $json['refresh_token'] = $json['refresh_token'] ?? $tokens['refresh_token'];
        $json['refresh_expires_at'] = time() + (int)($json['x_refresh_token_expires_in'] ?? 0);
        self::save_tokens($json);
        return $json;
    }

    public static function api_request(string $method, string $path, array $payload = [])
    {
        $tokens = self::refresh_tokens_if_needed();
        if (!$tokens) {
            return new WP_Error('cfp_qb_not_connected', __('QuickBooks not connected', 'classflow-pro'));
        }
        $realm_id = Settings::get('quickbooks_realm_id');
        if (!$realm_id) return new WP_Error('cfp_qb_no_realm', __('QuickBooks realmId is missing', 'classflow-pro'));
        $url = self::base_url() . '/v3/company/' . rawurlencode($realm_id) . $path;
        $headers = [
            'Authorization' => 'Bearer ' . $tokens['access_token'],
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 45,
        ];
        if (in_array($method, ['POST','PUT','PATCH'], true)) {
            $args['body'] = wp_json_encode($payload);
        }
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code >= 400) {
            \ClassFlowPro\Logging\Logger::log('error', 'quickbooks', 'API error', ['status' => $code, 'path' => $path, 'response' => $json]);
            return new WP_Error('cfp_qb_error', __('QuickBooks API error', 'classflow-pro'), ['status' => $code, 'body' => $json]);
        }
        \ClassFlowPro\Logging\Logger::log('info', 'quickbooks', 'API request', ['path' => $path, 'status' => $code]);
        return $json;
    }

    public static function ensure_customer(string $email, string $display_name)
    {
        $query = "select Id, DisplayName from Customer where PrimaryEmailAddr.Address = '$email'";
        $res = self::api_request('GET', '/query?query=' . rawurlencode($query));
        if (!is_wp_error($res) && !empty($res['QueryResponse']['Customer'][0]['Id'])) {
            return $res['QueryResponse']['Customer'][0]['Id'];
        }
        $payload = [
            'DisplayName' => $display_name ?: $email,
            'PrimaryEmailAddr' => ['Address' => $email],
        ];
        $created = self::api_request('POST', '/customer', $payload);
        if (is_wp_error($created)) return null;
        return $created['Customer']['Id'] ?? null;
    }

    public static function create_sales_receipt_for_booking(int $booking_id): void
    {
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) return;
        if ((int)$booking['amount_cents'] <= 0) return;
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $schedules WHERE id = %d", $booking['schedule_id']), ARRAY_A);
        if (!$schedule) return;

        $email = $booking['customer_email'];
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$schedule['class_id']);
        $desc = $title . ' — ' . gmdate('Y-m-d H:i', strtotime($schedule['start_time'])) . ' UTC';
        $amount = round(((int)$booking['amount_cents']) / 100, 2);

        $customer_id = $email ? self::ensure_customer($email, $email) : null;
        // Item mapping
        $itemRef = null;
        if (\ClassFlowPro\Admin\Settings::get('qb_item_per_class_enable', 0)) {
            $itemRef = self::ensure_item_for_class((int)$schedule['class_id']);
        } elseif ($name = \ClassFlowPro\Admin\Settings::get('qb_default_item_name', '')) {
            $itemRef = self::ensure_item($name);
        }
        $taxCodeRef = \ClassFlowPro\Admin\Settings::get('qb_tax_code_ref', '');

        $lineDetail = [ 'Qty' => 1, 'UnitPrice' => $amount ];
        if ($itemRef) $lineDetail['ItemRef'] = ['value' => $itemRef];
        if ($taxCodeRef) $lineDetail['TaxCodeRef'] = ['value' => $taxCodeRef];
        $line = [ 'DetailType' => 'SalesItemLineDetail', 'Amount' => $amount, 'Description' => $desc, 'SalesItemLineDetail' => $lineDetail ];
        $payload = [
            'TxnDate' => gmdate('Y-m-d'),
            'Line' => [$line],
            'PrivateNote' => 'ClassFlow Pro Booking #' . $booking_id,
        ];
        if ($customer_id) {
            $payload['CustomerRef'] = ['value' => $customer_id];
        }
        $created = self::api_request('POST', '/salesreceipt', $payload);
        if (!is_wp_error($created) && !empty($created['SalesReceipt']['Id'])) {
            $tx = $wpdb->prefix . 'cfp_transactions';
            $wpdb->insert($tx, [
                'user_id' => $booking['user_id'],
                'booking_id' => $booking['id'],
                'amount_cents' => $booking['amount_cents'],
                'currency' => $booking['currency'],
                'type' => 'sales_receipt',
                'processor' => 'quickbooks',
                'processor_id' => $created['SalesReceipt']['Id'],
                'status' => 'succeeded',
                'tax_amount_cents' => 0,
                'fee_amount_cents' => 0,
            ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d']);
        }
    }

    public static function download_sales_receipt_pdf(string $sales_receipt_id)
    {
        $tokens = self::refresh_tokens_if_needed();
        if (!$tokens) return new \WP_Error('cfp_qb_not_connected', __('QuickBooks not connected', 'classflow-pro'));
        $realm_id = Settings::get('quickbooks_realm_id');
        if (!$realm_id) return new \WP_Error('cfp_qb_no_realm', __('QuickBooks realmId is missing', 'classflow-pro'));
        $url = self::base_url() . '/v3/company/' . rawurlencode($realm_id) . '/salesreceipt/' . rawurlencode($sales_receipt_id) . '/pdf';
        $res = wp_remote_get($url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $tokens['access_token'], 'Accept' => 'application/pdf' ],
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return $res;
        if (wp_remote_retrieve_response_code($res) >= 400) return new \WP_Error('cfp_qb_pdf_error', __('Failed to download QuickBooks PDF', 'classflow-pro'));
        return $res;
    }

    public static function ensure_item_for_class(int $class_id): ?string
    {
        $prefix = \ClassFlowPro\Admin\Settings::get('qb_item_prefix', 'Class - ');
        $name = $prefix . \ClassFlowPro\Utils\Entities::class_name($class_id);
        return self::ensure_item($name);
    }

    public static function ensure_item(string $name): ?string
    {
        $query = "select Id, Name from Item where Name = '$name'";
        $res = self::api_request('GET', '/query?query=' . rawurlencode($query));
        if (!is_wp_error($res) && !empty($res['QueryResponse']['Item'][0]['Id'])) {
            return $res['QueryResponse']['Item'][0]['Id'];
        }
        $incomeRef = \ClassFlowPro\Admin\Settings::get('qb_income_account_ref', '');
        $payload = [
            'Name' => $name,
            'Type' => 'Service',
            'IncomeAccountRef' => $incomeRef ? ['value' => $incomeRef] : null,
        ];
        $payload = array_filter($payload, fn($v) => $v !== null);
        $created = self::api_request('POST', '/item', $payload);
        if (is_wp_error($created)) return null;
        return $created['Item']['Id'] ?? null;
    }
}
