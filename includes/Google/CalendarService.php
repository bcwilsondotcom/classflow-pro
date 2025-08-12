<?php
namespace ClassFlowPro\Google;

use ClassFlowPro\Admin\Settings;

/**
 * Enhanced Google Calendar integration with Meet support
 */
class CalendarService extends GoogleService
{
    private static $api_base = 'https://www.googleapis.com/calendar/v3';
    
    /**
     * Create or update a calendar event for a schedule
     */
    public static function sync_schedule(int $schedule_id): ?string
    {
        if (!Settings::get('google_calendar_enabled')) {
            return null;
        }
        
        global $wpdb;
        $stable = $wpdb->prefix . 'cfp_schedules';
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $schedule_id), ARRAY_A);
        
        if (!$schedule) {
            return null;
        }
        
        // Get class and location details
        $class_name = \ClassFlowPro\Utils\Entities::class_name((int)$schedule['class_id']);
        $location_name = \ClassFlowPro\Utils\Entities::location_name((int)$schedule['location_id']);
        $instructor_name = \ClassFlowPro\Utils\Entities::instructor_name((int)$schedule['instructor_id']);
        
        // Build event data
        $event = self::build_event_data($schedule, $class_name, $location_name, $instructor_name);
        
        // Check if event already exists
        $existing_event_id = get_post_meta($schedule_id, '_cfp_google_event_id', true);
        
        if ($existing_event_id) {
            // Update existing event
            return self::update_event($existing_event_id, $event);
        } else {
            // Create new event
            $event_id = self::create_event($event);
            if ($event_id) {
                update_post_meta($schedule_id, '_cfp_google_event_id', $event_id);
            }
            return $event_id;
        }
    }
    
    /**
     * Build event data for Google Calendar
     */
    private static function build_event_data(array $schedule, string $class_name, string $location_name, string $instructor_name): array
    {
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($schedule['location_id']) ? (int)$schedule['location_id'] : null);
        
        // Parse datetime and duration
        $start_dt = new \DateTime($schedule['start_time'] . 'Z');
        $start_dt->setTimezone(new \DateTimeZone($tz));
        
        $duration_minutes = (int)$schedule['duration_minutes'] ?: 60;
        $end_dt = clone $start_dt;
        $end_dt->add(new \DateInterval('PT' . $duration_minutes . 'M'));
        
        // Build description
        $description = "Class: $class_name\n";
        if ($instructor_name) {
            $description .= "Instructor: $instructor_name\n";
        }
        if ($location_name) {
            $description .= "Location: $location_name\n";
        }
        
        // Get capacity and current bookings
        $capacity = (int)$schedule['capacity'];
        if ($capacity > 0) {
            $booked = self::get_booking_count($schedule['id']);
            $description .= "Capacity: $booked / $capacity\n";
        }
        
        // Add booking link
        $booking_url = site_url('/booking?schedule=' . $schedule['id']);
        $description .= "\nBooking Link: $booking_url";
        
        $event = [
            'summary' => $class_name,
            'description' => $description,
            'start' => [
                'dateTime' => $start_dt->format('c'),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $end_dt->format('c'),
                'timeZone' => $tz,
            ],
        ];
        
        // Add location
        if ($location_name && strtolower($location_name) !== 'virtual' && strtolower($location_name) !== 'online') {
            $event['location'] = $location_name;
        }
        
        // Add Google Meet if enabled and location is virtual
        if (Settings::get('google_meet_enabled')) {
            $is_virtual = (strtolower($location_name) === 'virtual' || 
                          strtolower($location_name) === 'online' || 
                          empty($location_name));
            
            if ($is_virtual || Settings::get('google_meet_auto_create')) {
                $event['conferenceData'] = [
                    'createRequest' => [
                        'requestId' => 'cfp-' . $schedule['id'] . '-' . time(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ],
                    ],
                ];
            }
        }
        
        // Set event color
        $color_id = Settings::get('google_calendar_color');
        if ($color_id) {
            $event['colorId'] = $color_id;
        }
        
        // Add reminders (optional)
        $event['reminders'] = [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'email', 'minutes' => 1440], // 24 hours
                ['method' => 'popup', 'minutes' => 60],   // 1 hour
            ],
        ];
        
        return $event;
    }
    
    /**
     * Create a new calendar event
     */
    private static function create_event(array $event): ?string
    {
        $calendar_id = Settings::get('google_calendar_id', 'primary');
        $url = self::$api_base . '/calendars/' . urlencode($calendar_id) . '/events';
        
        // Add conference data support if Meet is enabled
        if (isset($event['conferenceData'])) {
            $url .= '?conferenceDataVersion=1';
        }
        
        $response = self::api_request($url, [
            'method' => 'POST',
            'body' => json_encode($event),
        ]);
        
        if (isset($response['error'])) {
            error_log('ClassFlow Pro: Failed to create Google Calendar event - ' . print_r($response['error'], true));
            return null;
        }
        
        // Store Meet link if created
        if (isset($response['hangoutLink'])) {
            // We could store this in schedule meta for easy access
            global $wpdb;
            $stable = $wpdb->prefix . 'cfp_schedules';
            $wpdb->update($stable, 
                ['notes' => $wpdb->get_var($wpdb->prepare("SELECT notes FROM $stable WHERE id = %d", $response['id'])) . "\nGoogle Meet: " . $response['hangoutLink']],
                ['id' => $response['id']]
            );
        }
        
        return $response['id'] ?? null;
    }
    
    /**
     * Update an existing calendar event
     */
    private static function update_event(string $event_id, array $event): ?string
    {
        $calendar_id = Settings::get('google_calendar_id', 'primary');
        $url = self::$api_base . '/calendars/' . urlencode($calendar_id) . '/events/' . urlencode($event_id);
        
        // Add conference data support if Meet is enabled
        if (isset($event['conferenceData'])) {
            $url .= '?conferenceDataVersion=1';
        }
        
        $response = self::api_request($url, [
            'method' => 'PATCH',
            'body' => json_encode($event),
        ]);
        
        if (isset($response['error'])) {
            error_log('ClassFlow Pro: Failed to update Google Calendar event - ' . print_r($response['error'], true));
            return null;
        }
        
        return $response['id'] ?? null;
    }
    
    /**
     * Delete a calendar event
     */
    public static function delete_event(int $schedule_id): bool
    {
        $event_id = get_post_meta($schedule_id, '_cfp_google_event_id', true);
        
        if (!$event_id) {
            return true; // No event to delete
        }
        
        $calendar_id = Settings::get('google_calendar_id', 'primary');
        $url = self::$api_base . '/calendars/' . urlencode($calendar_id) . '/events/' . urlencode($event_id);
        
        $response = self::api_request($url, [
            'method' => 'DELETE',
        ]);
        
        if (!isset($response['error'])) {
            delete_post_meta($schedule_id, '_cfp_google_event_id');
            return true;
        }
        
        // If error is 404, event doesn't exist, so we can clean up meta
        if (isset($response['error']['code']) && $response['error']['code'] == 404) {
            delete_post_meta($schedule_id, '_cfp_google_event_id');
            return true;
        }
        
        return false;
    }
    
    /**
     * Sync a booking to calendar (if enabled)
     */
    public static function sync_booking(int $booking_id): ?string
    {
        if (!Settings::get('google_calendar_sync_bookings')) {
            return null;
        }
        
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $btable WHERE id = %d", $booking_id), ARRAY_A);
        if (!$booking) {
            return null;
        }
        
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $booking['schedule_id']), ARRAY_A);
        if (!$schedule) {
            return null;
        }
        
        // Get customer info
        $customer_name = $booking['customer_name'];
        $customer_email = $booking['customer_email'];
        
        if (!$customer_name && $booking['user_id']) {
            $user = get_userdata($booking['user_id']);
            if ($user) {
                $customer_name = $user->display_name;
                $customer_email = $user->user_email;
            }
        }
        
        // Build event for individual booking
        $class_name = \ClassFlowPro\Utils\Entities::class_name((int)$schedule['class_id']);
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($schedule['location_id']) ? (int)$schedule['location_id'] : null);
        
        $start_dt = new \DateTime($schedule['start_time'] . 'Z');
        $start_dt->setTimezone(new \DateTimeZone($tz));
        
        $duration_minutes = (int)$schedule['duration_minutes'] ?: 60;
        $end_dt = clone $start_dt;
        $end_dt->add(new \DateInterval('PT' . $duration_minutes . 'M'));
        
        $event = [
            'summary' => "$class_name - $customer_name",
            'description' => "Booking #$booking_id\nCustomer: $customer_name\nEmail: $customer_email\nStatus: " . $booking['status'],
            'start' => [
                'dateTime' => $start_dt->format('c'),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $end_dt->format('c'),
                'timeZone' => $tz,
            ],
        ];
        
        // Add attendee
        if ($customer_email) {
            $event['attendees'] = [
                ['email' => $customer_email, 'displayName' => $customer_name],
            ];
        }
        
        // Create the event
        $calendar_id = Settings::get('google_calendar_id', 'primary');
        $url = self::$api_base . '/calendars/' . urlencode($calendar_id) . '/events?sendUpdates=all';
        
        $response = self::api_request($url, [
            'method' => 'POST',
            'body' => json_encode($event),
        ]);
        
        if (isset($response['id'])) {
            // Store event ID with booking
            update_post_meta($booking_id, '_cfp_google_booking_event_id', $response['id']);
            return $response['id'];
        }
        
        return null;
    }
    
    /**
     * Get booking count for a schedule
     */
    private static function get_booking_count(int $schedule_id): int
    {
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $btable WHERE schedule_id = %d AND status IN ('confirmed', 'pending')",
            $schedule_id
        ));
    }
    
    /**
     * Get Google Meet link for a schedule
     */
    public static function get_meet_link(int $schedule_id): ?string
    {
        $event_id = get_post_meta($schedule_id, '_cfp_google_event_id', true);
        
        if (!$event_id) {
            return null;
        }
        
        $calendar_id = Settings::get('google_calendar_id', 'primary');
        $url = self::$api_base . '/calendars/' . urlencode($calendar_id) . '/events/' . urlencode($event_id);
        
        $response = self::api_request($url, [
            'method' => 'GET',
        ]);
        
        return $response['hangoutLink'] ?? null;
    }
}