<?php
namespace ClassFlowPro\Calendar;

use ClassFlowPro\Admin\Settings;

class Google
{
    private static function oauth_base(): string { return 'https://accounts.google.com/o/oauth2/v2'; }
    private static function token_url(): string { return 'https://oauth2.googleapis.com/token'; }
    private static function api_base(): string { return 'https://www.googleapis.com/calendar/v3'; }

    private static function get_tokens(): array { return get_option('cfp_google_tokens', []); }
    private static function save_tokens(array $t): void { update_option('cfp_google_tokens', $t, false); }

    public static function connect(\WP_REST_Request $req)
    {
        $client_id = Settings::get('google_client_id');
        $redirect = Settings::get('google_redirect_uri');
        $scope = rawurlencode('https://www.googleapis.com/auth/calendar');
        $state = wp_create_nonce('cfp_google_oauth');
        $url = self::oauth_base() . '/auth?response_type=code&client_id=' . rawurlencode($client_id) . '&redirect_uri=' . rawurlencode($redirect) . '&scope=' . $scope . '&access_type=offline&prompt=consent&state=' . rawurlencode($state);
        return new \WP_REST_Response(['url' => $url]);
    }

    public static function callback(\WP_REST_Request $req)
    {
        $code = sanitize_text_field($req->get_param('code'));
        $state = sanitize_text_field($req->get_param('state'));
        if (!wp_verify_nonce($state, 'cfp_google_oauth')) {
            return new \WP_Error('cfp_google_state', __('Invalid OAuth state', 'classflow-pro'), ['status' => 400]);
        }
        $client_id = Settings::get('google_client_id');
        $client_secret = Settings::get('google_client_secret');
        $redirect = Settings::get('google_redirect_uri');
        $res = wp_remote_post(self::token_url(), [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect,
            ],
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return $res;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($json['access_token'])) return new \WP_Error('cfp_google_token', __('Failed to obtain Google tokens', 'classflow-pro'));
        $json['expires_at'] = time() + (int)$json['expires_in'];
        self::save_tokens($json);
        return wp_redirect(admin_url('admin.php?page=classflow-pro-settings'));
    }

    private static function refresh_if_needed(): ?array
    {
        $t = self::get_tokens();
        if (!$t) return null;
        if (!empty($t['expires_at']) && $t['expires_at'] > (time() + 60)) return $t;
        $res = wp_remote_post(self::token_url(), [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $t['refresh_token'] ?? '',
                'client_id' => Settings::get('google_client_id'),
                'client_secret' => Settings::get('google_client_secret'),
            ],
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return null;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (empty($json['access_token'])) return null;
        $json['refresh_token'] = $json['refresh_token'] ?? ($t['refresh_token'] ?? null);
        $json['expires_at'] = time() + (int)$json['expires_in'];
        self::save_tokens($json);
        return $json;
    }

    public static function upsert_event(int $schedule_id): void
    {
        $calendar_id = Settings::get('google_calendar_id');
        if (!$calendar_id) return;
        $tokens = self::refresh_if_needed();
        if (!$tokens) return;
        global $wpdb;
        $s = $wpdb->prefix . 'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id = %d", $schedule_id), ARRAY_A);
        if (!$row) return;
        $title = get_the_title((int)$row['class_id']);
        $location = !empty($row['location_id']) ? get_the_title((int)$row['location_id']) : '';
        $event = [
            'summary' => $title,
            'location' => $location,
            'start' => ['dateTime' => gmdate('c', strtotime($row['start_time'])), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => gmdate('c', strtotime($row['end_time'])), 'timeZone' => 'UTC'],
        ];
        $method = 'POST';
        $path = '/calendars/' . rawurlencode($calendar_id) . '/events';
        if (!empty($row['google_event_id'])) {
            $method = 'PATCH';
            $path .= '/' . rawurlencode($row['google_event_id']);
        }
        $res = wp_remote_request(self::api_base() . $path, [
            'method' => $method,
            'headers' => [ 'Authorization' => 'Bearer ' . $tokens['access_token'], 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode($event),
            'timeout' => 45,
        ]);
        if (is_wp_error($res)) return;
        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if ($code < 400 && !empty($body['id'])) {
            $wpdb->update($s, ['google_event_id' => $body['id']], ['id' => $schedule_id], ['%s'], ['%d']);
        }
    }
}

