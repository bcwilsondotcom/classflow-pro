<?php
namespace ClassFlowPro\Google;

use ClassFlowPro\Admin\Settings;

/**
 * Gmail API integration for sending emails
 */
class GmailService extends GoogleService
{
    private static $api_base = 'https://gmail.googleapis.com/gmail/v1';
    
    /**
     * Send an email using Gmail API
     */
    public static function send_email(array $args): bool
    {
        // Check if Gmail is enabled
        if (!Settings::get('gmail_enabled')) {
            return false;
        }
        
        // Ensure we have valid authentication
        $token = self::ensure_valid_token();
        if (!$token) {
            error_log('ClassFlow Pro: Gmail authentication failed');
            return false;
        }
        
        // Prepare email data
        $to = $args['to'] ?? '';
        $subject = $args['subject'] ?? '';
        $body = $args['body'] ?? '';
        $headers = $args['headers'] ?? [];
        $attachments = $args['attachments'] ?? [];
        
        if (empty($to) || empty($subject)) {
            return false;
        }
        
        // Get sender info from settings
        $sender_email = Settings::get('gmail_sender_email', get_option('admin_email'));
        $sender_name = Settings::get('gmail_sender_name', get_bloginfo('name'));
        
        // Build RFC 2822 formatted message
        $message = self::build_message($to, $subject, $body, $sender_email, $sender_name, $headers, $attachments);
        
        // Encode message for Gmail API
        $encoded_message = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
        
        // Send via Gmail API
        $url = self::$api_base . '/users/me/messages/send';
        
        $response = self::api_request($url, [
            'method' => 'POST',
            'body' => json_encode([
                'raw' => $encoded_message,
            ]),
        ]);
        
        if (isset($response['error'])) {
            error_log('ClassFlow Pro Gmail Error: ' . print_r($response['error'], true));
            return false;
        }
        
        // Track email if enabled
        if (Settings::get('gmail_track_opens') && isset($response['id'])) {
            self::add_tracking_pixel($response['id'], $to);
        }
        
        return isset($response['id']);
    }
    
    /**
     * Build RFC 2822 formatted email message
     */
    private static function build_message(
        string $to,
        string $subject,
        string $body,
        string $from_email,
        string $from_name,
        array $headers = [],
        array $attachments = []
    ): string {
        $boundary = uniqid('boundary_');
        
        // Build headers
        $message = "From: \"$from_name\" <$from_email>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        
        // Add custom headers
        foreach ($headers as $key => $value) {
            if (stripos($key, 'Content-Type') === false && stripos($key, 'MIME-Version') === false) {
                $message .= "$key: $value\r\n";
            }
        }
        
        // Check if HTML content
        $is_html = (strpos($body, '<html') !== false || strpos($body, '<body') !== false);
        
        if (!empty($attachments)) {
            // Multipart message with attachments
            $message .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
            
            // Body part
            $message .= "--$boundary\r\n";
            if ($is_html) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($body)) . "\r\n";
            
            // Attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $filename = basename($attachment);
                    $file_content = file_get_contents($attachment);
                    $mime_type = mime_content_type($attachment) ?: 'application/octet-stream';
                    
                    $message .= "--$boundary\r\n";
                    $message .= "Content-Type: $mime_type; name=\"$filename\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
                    $message .= chunk_split(base64_encode($file_content)) . "\r\n";
                }
            }
            
            $message .= "--$boundary--";
        } else {
            // Simple message without attachments
            if ($is_html) {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                
                // Add tracking pixel if enabled
                if (Settings::get('gmail_track_opens')) {
                    $tracking_pixel = self::get_tracking_pixel_html($to);
                    // Insert before closing body tag
                    if (stripos($body, '</body>') !== false) {
                        $body = str_ireplace('</body>', $tracking_pixel . '</body>', $body);
                    } else {
                        $body .= $tracking_pixel;
                    }
                }
            } else {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($body));
        }
        
        return $message;
    }
    
    /**
     * Get tracking pixel HTML
     */
    private static function get_tracking_pixel_html(string $email): string
    {
        $tracking_id = wp_hash($email . time());
        $tracking_url = site_url('/wp-json/classflow/v1/email/track/' . $tracking_id);
        
        // Store tracking info
        set_transient('cfp_email_track_' . $tracking_id, [
            'email' => $email,
            'sent_at' => current_time('mysql'),
        ], DAY_IN_SECONDS * 30);
        
        return '<img src="' . esc_url($tracking_url) . '" width="1" height="1" style="display:none;" alt="" />';
    }
    
    /**
     * Add tracking data for sent email
     */
    private static function add_tracking_pixel(string $message_id, string $recipient): void
    {
        $tracking_data = get_option('cfp_gmail_tracking', []);
        
        $tracking_data[$message_id] = [
            'recipient' => $recipient,
            'sent_at' => current_time('mysql'),
            'opened' => false,
            'opened_at' => null,
        ];
        
        // Keep only last 1000 entries
        if (count($tracking_data) > 1000) {
            $tracking_data = array_slice($tracking_data, -1000, null, true);
        }
        
        update_option('cfp_gmail_tracking', $tracking_data, false);
    }
    
    /**
     * Send bulk emails (for notifications to multiple recipients)
     */
    public static function send_bulk(array $recipients, string $subject, string $body, array $headers = []): array
    {
        $results = [
            'sent' => [],
            'failed' => [],
        ];
        
        foreach ($recipients as $recipient) {
            $sent = self::send_email([
                'to' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'headers' => $headers,
            ]);
            
            if ($sent) {
                $results['sent'][] = $recipient;
            } else {
                $results['failed'][] = $recipient;
            }
            
            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }
        
        return $results;
    }
    
    /**
     * Test Gmail connection and configuration
     */
    public static function test_connection(): array
    {
        $token = self::ensure_valid_token();
        
        if (!$token) {
            return [
                'success' => false,
                'message' => __('Gmail is not authenticated. Please connect to Google first.', 'classflow-pro'),
            ];
        }
        
        // Test by getting user profile
        $url = self::$api_base . '/users/me/profile';
        $response = self::api_request($url, ['method' => 'GET']);
        
        if (isset($response['error'])) {
            return [
                'success' => false,
                'message' => __('Gmail API error: ', 'classflow-pro') . $response['error']['message'] ?? 'Unknown error',
            ];
        }
        
        if (isset($response['emailAddress'])) {
            // Update sender email if not set
            $current_sender = Settings::get('gmail_sender_email');
            if (empty($current_sender)) {
                $settings = get_option('cfp_settings', []);
                $settings['gmail_sender_email'] = $response['emailAddress'];
                update_option('cfp_settings', $settings);
            }
            
            return [
                'success' => true,
                'message' => sprintf(__('Connected to Gmail as: %s', 'classflow-pro'), $response['emailAddress']),
                'email' => $response['emailAddress'],
            ];
        }
        
        return [
            'success' => false,
            'message' => __('Unable to verify Gmail connection', 'classflow-pro'),
        ];
    }
}