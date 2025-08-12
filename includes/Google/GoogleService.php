<?php
namespace ClassFlowPro\Google;

use ClassFlowPro\Admin\Settings;

/**
 * Base class for all Google Workspace services
 * Handles OAuth, token management, and common API functionality
 */
abstract class GoogleService
{
    protected static $oauth_base = 'https://accounts.google.com/o/oauth2/v2';
    protected static $token_url = 'https://oauth2.googleapis.com/token';
    
    /**
     * Get all required OAuth scopes for enabled services
     */
    public static function get_required_scopes(): array
    {
        $scopes = [];
        
        // Always include calendar scope (existing functionality)
        if (Settings::get('google_calendar_enabled')) {
            $scopes[] = 'https://www.googleapis.com/auth/calendar';
            $scopes[] = 'https://www.googleapis.com/auth/calendar.events';
        }
        
        // Gmail scopes
        if (Settings::get('gmail_enabled')) {
            $scopes[] = 'https://www.googleapis.com/auth/gmail.send';
            $scopes[] = 'https://www.googleapis.com/auth/gmail.compose';
        }
        
        // Google Meet (included with Calendar)
        if (Settings::get('google_meet_enabled')) {
            $scopes[] = 'https://www.googleapis.com/auth/calendar';
        }
        
        // Google Drive scopes
        if (Settings::get('google_drive_enabled')) {
            $scopes[] = 'https://www.googleapis.com/auth/drive.file';
            $scopes[] = 'https://www.googleapis.com/auth/drive.appdata';
        }
        
        // Google Contacts scopes
        if (Settings::get('google_contacts_enabled')) {
            $scopes[] = 'https://www.googleapis.com/auth/contacts';
        }
        
        // Remove duplicates
        return array_unique($scopes);
    }
    
    /**
     * Get stored OAuth tokens
     */
    protected static function get_tokens(): array
    {
        return get_option('cfp_google_token', []);
    }
    
    /**
     * Save OAuth tokens
     */
    protected static function save_tokens(array $tokens): void
    {
        update_option('cfp_google_token', $tokens, false);
    }
    
    /**
     * Check if tokens are valid and refresh if needed
     */
    protected static function ensure_valid_token(): ?string
    {
        $tokens = self::get_tokens();
        
        if (empty($tokens['access_token'])) {
            return null;
        }
        
        // Check if token is expired
        if (!empty($tokens['expires_at']) && $tokens['expires_at'] < time() + 60) {
            // Token is expired or about to expire, refresh it
            if (!empty($tokens['refresh_token'])) {
                $refreshed = self::refresh_token($tokens['refresh_token']);
                if ($refreshed) {
                    return $refreshed;
                }
            }
            return null;
        }
        
        return $tokens['access_token'];
    }
    
    /**
     * Refresh an expired access token
     */
    protected static function refresh_token(string $refresh_token): ?string
    {
        $client_id = Settings::get('google_client_id');
        $client_secret = Settings::get('google_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            return null;
        }
        
        $response = wp_remote_post(self::$token_url, [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('Google token refresh failed: ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            error_log('Google token refresh failed: No access token in response');
            return null;
        }
        
        // Update stored tokens
        $tokens = self::get_tokens();
        $tokens['access_token'] = $body['access_token'];
        $tokens['expires_at'] = time() + (int)($body['expires_in'] ?? 3600);
        
        // Preserve refresh token if not returned (Google doesn't always return it)
        if (!empty($body['refresh_token'])) {
            $tokens['refresh_token'] = $body['refresh_token'];
        }
        
        self::save_tokens($tokens);
        
        return $body['access_token'];
    }
    
    /**
     * Make an authenticated API request
     */
    protected static function api_request(string $url, array $args = []): array
    {
        $token = self::ensure_valid_token();
        
        if (!$token) {
            return ['error' => 'No valid Google authentication token'];
        }
        
        $defaults = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON response from Google API'];
        }
        
        return $data ?: [];
    }
    
    /**
     * Handle OAuth connection initiation
     */
    public static function connect(\WP_REST_Request $request)
    {
        $client_id = Settings::get('google_client_id');
        $redirect_uri = Settings::get('google_redirect_uri');
        
        if (empty($client_id) || empty($redirect_uri)) {
            return new \WP_Error('missing_config', 'Google OAuth not configured', ['status' => 400]);
        }
        
        $scopes = self::get_required_scopes();
        if (empty($scopes)) {
            // Default to calendar scope if nothing is enabled
            $scopes = ['https://www.googleapis.com/auth/calendar'];
        }
        
        $state = wp_create_nonce('cfp_google_oauth');
        
        $params = [
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];
        
        $auth_url = self::$oauth_base . '/auth?' . http_build_query($params);
        
        // Redirect directly to Google
        wp_redirect($auth_url);
        exit;
    }
    
    /**
     * Handle OAuth callback
     */
    public static function callback(\WP_REST_Request $request)
    {
        $code = sanitize_text_field($request->get_param('code'));
        $state = sanitize_text_field($request->get_param('state'));
        $error = sanitize_text_field($request->get_param('error'));
        
        // Handle user denial
        if ($error) {
            wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&auth_error=' . urlencode($error)));
            exit;
        }
        
        // Verify state
        if (!wp_verify_nonce($state, 'cfp_google_oauth')) {
            wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&auth_error=invalid_state'));
            exit;
        }
        
        $client_id = Settings::get('google_client_id');
        $client_secret = Settings::get('google_client_secret');
        $redirect_uri = Settings::get('google_redirect_uri');
        
        // Exchange code for tokens
        $response = wp_remote_post(self::$token_url, [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&auth_error=token_exchange_failed'));
            exit;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&auth_error=no_access_token'));
            exit;
        }
        
        // Store tokens
        $tokens = [
            'access_token' => $body['access_token'],
            'expires_at' => time() + (int)($body['expires_in'] ?? 3600),
            'scope' => $body['scope'] ?? '',
        ];
        
        if (!empty($body['refresh_token'])) {
            $tokens['refresh_token'] = $body['refresh_token'];
        }
        
        self::save_tokens($tokens);
        
        // Redirect to settings with success message
        wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&auth_success=1'));
        exit;
    }
    
    /**
     * Disconnect Google account
     */
    public static function disconnect(\WP_REST_Request $request)
    {
        // Clear stored tokens
        delete_option('cfp_google_token');
        
        // Redirect to settings
        wp_redirect(admin_url('admin.php?page=classflow-pro-settings&tab=google&disconnected=1'));
        exit;
    }
    
    /**
     * Check if service is connected
     */
    public static function is_connected(): bool
    {
        $tokens = self::get_tokens();
        return !empty($tokens['access_token']) || !empty($tokens['refresh_token']);
    }
}