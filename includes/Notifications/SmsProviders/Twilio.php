<?php
namespace ClassFlowPro\Notifications\SmsProviders;

use WP_Error;

class Twilio
{
    public static function send(string $from, string $to, string $body)
    {
        $sid = \ClassFlowPro\Admin\Settings::get('twilio_account_sid', '');
        $token = \ClassFlowPro\Admin\Settings::get('twilio_auth_token', '');
        if (!$sid || !$token || !$from) {
            return new WP_Error('cfp_twilio_not_configured', 'Twilio is not configured');
        }
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $args = [
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token) ],
            'body' => [ 'From' => $from, 'To' => $to, 'Body' => $body ],
            'timeout' => 15,
        ];
        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) {
            $msg = wp_remote_retrieve_body($res) ?: 'Twilio error';
            return new WP_Error('cfp_twilio_error', $msg, ['status' => $code]);
        }
        return json_decode(wp_remote_retrieve_body($res), true);
    }
}

