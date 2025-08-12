<?php
namespace ClassFlowPro\Zoom;

use ClassFlowPro\Admin\Settings;

/**
 * Zoom API integration using Server-to-Server OAuth
 */
class ZoomService
{
    private static $api_base = 'https://api.zoom.us/v2';
    private static $oauth_url = 'https://zoom.us/oauth/token';
    
    /**
     * Get access token using Server-to-Server OAuth
     */
    protected static function get_access_token(): ?string
    {
        $account_id = Settings::get('zoom_account_id');
        $client_id = Settings::get('zoom_client_id');
        $client_secret = Settings::get('zoom_client_secret');
        
        if (empty($account_id) || empty($client_id) || empty($client_secret)) {
            return null;
        }
        
        // Check if we have a cached token
        $cached_token = get_transient('cfp_zoom_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        // Request new token
        $response = wp_remote_post(self::$oauth_url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'account_credentials',
                'account_id' => $account_id,
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('Zoom OAuth error: ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            error_log('Zoom OAuth failed: No access token in response');
            return null;
        }
        
        // Cache token (expires in 1 hour, cache for 55 minutes)
        set_transient('cfp_zoom_access_token', $body['access_token'], 55 * MINUTE_IN_SECONDS);
        
        return $body['access_token'];
    }
    
    /**
     * Make an authenticated API request to Zoom
     */
    protected static function api_request(string $endpoint, array $args = []): array
    {
        $token = self::get_access_token();
        
        if (!$token) {
            return ['error' => 'Failed to authenticate with Zoom'];
        }
        
        $url = self::$api_base . $endpoint;
        
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
            return ['error' => 'Invalid JSON response from Zoom API'];
        }
        
        // Check for API errors
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            $error_message = $data['message'] ?? 'Zoom API error';
            return ['error' => $error_message, 'code' => $status_code];
        }
        
        return $data ?: [];
    }
    
    /**
     * Create a Zoom meeting
     */
    public static function create_meeting(array $params): ?array
    {
        if (!Settings::get('zoom_enabled')) {
            return null;
        }
        
        $meeting_data = [
            'topic' => $params['topic'] ?? 'ClassFlow Pro Session',
            'type' => 2, // Scheduled meeting
            'start_time' => $params['start_time'], // ISO 8601 format
            'duration' => $params['duration'] ?? 60, // in minutes
            'timezone' => $params['timezone'] ?? 'UTC',
            'agenda' => $params['agenda'] ?? '',
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => Settings::get('zoom_join_before_minutes', 5) > 0,
                'jbh_time' => Settings::get('zoom_join_before_minutes', 5),
                'mute_upon_entry' => (bool)Settings::get('zoom_mute_on_entry'),
                'waiting_room' => (bool)Settings::get('zoom_waiting_room'),
                'auto_recording' => Settings::get('zoom_auto_recording', 'none'),
                'registration_type' => 1, // Attendees register once
                'approval_type' => 2, // No registration required
                'audio' => 'both',
                'alternative_hosts' => '',
                'meeting_authentication' => false,
            ],
        ];
        
        // Add password if specified
        if (!empty($params['password'])) {
            $meeting_data['password'] = $params['password'];
        }
        
        $response = self::api_request('/users/me/meetings', [
            'method' => 'POST',
            'body' => json_encode($meeting_data),
        ]);
        
        if (isset($response['error'])) {
            error_log('Failed to create Zoom meeting: ' . $response['error']);
            return null;
        }
        
        return $response;
    }
    
    /**
     * Update a Zoom meeting
     */
    public static function update_meeting(string $meeting_id, array $params): bool
    {
        $meeting_data = [
            'topic' => $params['topic'] ?? null,
            'start_time' => $params['start_time'] ?? null,
            'duration' => $params['duration'] ?? null,
            'timezone' => $params['timezone'] ?? null,
            'agenda' => $params['agenda'] ?? null,
        ];
        
        // Remove null values
        $meeting_data = array_filter($meeting_data, function($value) {
            return $value !== null;
        });
        
        if (empty($meeting_data)) {
            return true; // Nothing to update
        }
        
        $response = self::api_request('/meetings/' . $meeting_id, [
            'method' => 'PATCH',
            'body' => json_encode($meeting_data),
        ]);
        
        return !isset($response['error']);
    }
    
    /**
     * Delete a Zoom meeting
     */
    public static function delete_meeting(string $meeting_id): bool
    {
        $response = self::api_request('/meetings/' . $meeting_id, [
            'method' => 'DELETE',
        ]);
        
        return !isset($response['error']);
    }
    
    /**
     * Get meeting details
     */
    public static function get_meeting(string $meeting_id): ?array
    {
        $response = self::api_request('/meetings/' . $meeting_id, [
            'method' => 'GET',
        ]);
        
        if (isset($response['error'])) {
            return null;
        }
        
        return $response;
    }
    
    /**
     * Add registrant to a meeting
     */
    public static function add_registrant(string $meeting_id, string $email, string $first_name, string $last_name = ''): ?array
    {
        $data = [
            'email' => $email,
            'first_name' => $first_name,
        ];
        
        if ($last_name) {
            $data['last_name'] = $last_name;
        }
        
        $response = self::api_request('/meetings/' . $meeting_id . '/registrants', [
            'method' => 'POST',
            'body' => json_encode($data),
        ]);
        
        if (isset($response['error'])) {
            return null;
        }
        
        return $response;
    }
    
    /**
     * Test Zoom connection
     */
    public static function test_connection(): array
    {
        // Try to get user info
        $response = self::api_request('/users/me', [
            'method' => 'GET',
        ]);
        
        if (isset($response['error'])) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response['error'],
            ];
        }
        
        if (isset($response['email'])) {
            return [
                'success' => true,
                'message' => sprintf('Connected as: %s', $response['email']),
                'user' => $response,
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Unable to verify Zoom connection',
        ];
    }
    
    /**
     * Create meeting for a class schedule
     */
    public static function create_meeting_for_schedule(int $schedule_id): ?string
    {
        global $wpdb;
        $stable = $wpdb->prefix . 'cfp_schedules';
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $schedule_id), ARRAY_A);
        
        if (!$schedule) {
            return null;
        }
        
        // Check if meeting already exists
        $existing_meeting_id = get_post_meta($schedule_id, '_cfp_zoom_meeting_id', true);
        if ($existing_meeting_id) {
            // Update existing meeting instead
            $meeting = self::get_meeting($existing_meeting_id);
            if ($meeting && !isset($meeting['error'])) {
                return $existing_meeting_id;
            }
        }
        
        // Get class and instructor details
        $class_name = \ClassFlowPro\Utils\Entities::class_name((int)$schedule['class_id']);
        $instructor_name = \ClassFlowPro\Utils\Entities::instructor_name((int)$schedule['instructor_id']);
        $location_name = \ClassFlowPro\Utils\Entities::location_name((int)$schedule['location_id']);
        
        // Check if this is a virtual class
        $is_virtual = (strtolower($location_name) === 'virtual' || 
                      strtolower($location_name) === 'online' || 
                      empty($location_name));
        
        if (!$is_virtual && !Settings::get('zoom_auto_create')) {
            return null; // Only create for virtual classes unless auto-create is enabled
        }
        
        // Get timezone for location
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($schedule['location_id']) ? (int)$schedule['location_id'] : null);
        
        // Parse start time
        $start_dt = new \DateTime($schedule['start_time'] . 'Z');
        $start_dt->setTimezone(new \DateTimeZone($tz));
        
        // Build meeting parameters
        $params = [
            'topic' => $class_name,
            'start_time' => $start_dt->format('Y-m-d\TH:i:s'),
            'duration' => (int)$schedule['duration_minutes'] ?: 60,
            'timezone' => $tz,
            'agenda' => "Class: $class_name\n" . 
                       ($instructor_name ? "Instructor: $instructor_name\n" : "") .
                       "Scheduled for: " . $start_dt->format('l, F j, Y g:i A'),
        ];
        
        // Create the meeting
        $meeting = self::create_meeting($params);
        
        if (!$meeting || isset($meeting['error'])) {
            error_log('Failed to create Zoom meeting for schedule ' . $schedule_id);
            return null;
        }
        
        // Store meeting details
        update_post_meta($schedule_id, '_cfp_zoom_meeting_id', $meeting['id']);
        update_post_meta($schedule_id, '_cfp_zoom_join_url', $meeting['join_url']);
        update_post_meta($schedule_id, '_cfp_zoom_host_url', $meeting['start_url']);
        
        // Store meeting password if exists
        if (!empty($meeting['password'])) {
            update_post_meta($schedule_id, '_cfp_zoom_password', $meeting['password']);
        }
        
        return $meeting['id'];
    }
    
    /**
     * Get Zoom meeting link for a schedule
     */
    public static function get_meeting_link(int $schedule_id, string $type = 'join'): ?string
    {
        $meeting_id = get_post_meta($schedule_id, '_cfp_zoom_meeting_id', true);
        
        if (!$meeting_id) {
            // Try to create meeting if enabled
            $meeting_id = self::create_meeting_for_schedule($schedule_id);
            if (!$meeting_id) {
                return null;
            }
        }
        
        if ($type === 'host') {
            return get_post_meta($schedule_id, '_cfp_zoom_host_url', true) ?: null;
        }
        
        return get_post_meta($schedule_id, '_cfp_zoom_join_url', true) ?: null;
    }
}