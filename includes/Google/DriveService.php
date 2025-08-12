<?php
namespace ClassFlowPro\Google;

use ClassFlowPro\Admin\Settings;

/**
 * Google Drive integration for backups and exports
 */
class DriveService extends GoogleService
{
    private static $api_base = 'https://www.googleapis.com/drive/v3';
    private static $upload_base = 'https://www.googleapis.com/upload/drive/v3';
    
    /**
     * Export bookings data to Google Drive
     */
    public static function export_bookings(string $date_from = null, string $date_to = null): ?string
    {
        if (!Settings::get('google_drive_enabled')) {
            return null;
        }
        
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        
        // Build query
        $query = "SELECT b.*, s.start_time, s.class_id, s.location_id, s.instructor_id 
                  FROM $btable b 
                  JOIN $stable s ON b.schedule_id = s.id 
                  WHERE 1=1";
        
        $params = [];
        
        if ($date_from) {
            $query .= " AND s.start_time >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $query .= " AND s.start_time <= %s";
            $params[] = $date_to;
        }
        
        $query .= " ORDER BY s.start_time ASC";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $bookings = $wpdb->get_results($query, ARRAY_A);
        
        // Generate CSV content
        $csv_content = self::generate_bookings_csv($bookings);
        
        // Create filename
        $filename = 'classflow_bookings_' . date('Y-m-d_His') . '.csv';
        
        // Upload to Drive
        return self::upload_file($filename, $csv_content, 'text/csv');
    }
    
    /**
     * Generate CSV content from bookings data
     */
    private static function generate_bookings_csv(array $bookings): string
    {
        $csv = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($csv, [
            'Booking ID',
            'Customer Name',
            'Customer Email',
            'Class',
            'Start Time',
            'Location',
            'Instructor',
            'Status',
            'Amount',
            'Currency',
            'Payment Method',
            'Created At',
        ]);
        
        // Data rows
        foreach ($bookings as $booking) {
            $class_name = \ClassFlowPro\Utils\Entities::class_name((int)$booking['class_id']);
            $location_name = \ClassFlowPro\Utils\Entities::location_name((int)$booking['location_id']);
            $instructor_name = \ClassFlowPro\Utils\Entities::instructor_name((int)$booking['instructor_id']);
            
            fputcsv($csv, [
                $booking['id'],
                $booking['customer_name'],
                $booking['customer_email'],
                $class_name,
                $booking['start_time'],
                $location_name,
                $instructor_name,
                $booking['status'],
                number_format($booking['amount_cents'] / 100, 2),
                strtoupper($booking['currency']),
                $booking['payment_method'],
                $booking['created_at'],
            ]);
        }
        
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        
        return $content;
    }
    
    /**
     * Upload a file to Google Drive
     */
    private static function upload_file(string $filename, string $content, string $mime_type = 'application/octet-stream'): ?string
    {
        $folder_id = Settings::get('google_drive_folder_id');
        
        // Create file metadata
        $metadata = [
            'name' => $filename,
            'mimeType' => $mime_type,
        ];
        
        if ($folder_id) {
            $metadata['parents'] = [$folder_id];
        }
        
        // Upload file using multipart request
        $boundary = 'classflow_' . uniqid();
        
        $body = "--$boundary\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: $mime_type\r\n\r\n";
        $body .= $content . "\r\n";
        $body .= "--$boundary--";
        
        $url = self::$upload_base . '/files?uploadType=multipart';
        
        $token = self::ensure_valid_token();
        if (!$token) {
            return null;
        }
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            error_log('ClassFlow Pro: Drive upload failed - ' . $response->get_error_message());
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['id'])) {
            // Log successful upload
            self::log_backup($filename, $data['id']);
            return $data['id'];
        }
        
        if (isset($data['error'])) {
            error_log('ClassFlow Pro: Drive upload error - ' . print_r($data['error'], true));
        }
        
        return null;
    }
    
    /**
     * Export revenue report to Drive
     */
    public static function export_revenue_report(string $period = 'month'): ?string
    {
        if (!Settings::get('google_drive_enabled')) {
            return null;
        }
        
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        
        // Determine date range
        $now = new \DateTime();
        switch ($period) {
            case 'week':
                $from = (clone $now)->modify('-7 days')->format('Y-m-d 00:00:00');
                break;
            case 'month':
                $from = (clone $now)->modify('first day of this month')->format('Y-m-d 00:00:00');
                break;
            case 'year':
                $from = (clone $now)->modify('first day of january')->format('Y-m-d 00:00:00');
                break;
            default:
                $from = (clone $now)->modify('-30 days')->format('Y-m-d 00:00:00');
        }
        
        $to = $now->format('Y-m-d 23:59:59');
        
        // Get revenue data
        $query = "SELECT 
                    DATE(s.start_time) as date,
                    COUNT(b.id) as bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN b.amount_cents ELSE 0 END) as revenue_cents,
                    COUNT(DISTINCT b.customer_email) as unique_customers
                  FROM $btable b
                  JOIN $stable s ON b.schedule_id = s.id
                  WHERE s.start_time BETWEEN %s AND %s
                  GROUP BY DATE(s.start_time)
                  ORDER BY date ASC";
        
        $data = $wpdb->get_results($wpdb->prepare($query, $from, $to), ARRAY_A);
        
        // Generate CSV
        $csv = fopen('php://temp', 'r+');
        
        fputcsv($csv, ['Date', 'Bookings', 'Revenue', 'Unique Customers']);
        
        $total_bookings = 0;
        $total_revenue = 0;
        $unique_customers = [];
        
        foreach ($data as $row) {
            fputcsv($csv, [
                $row['date'],
                $row['bookings'],
                number_format($row['revenue_cents'] / 100, 2),
                $row['unique_customers'],
            ]);
            
            $total_bookings += $row['bookings'];
            $total_revenue += $row['revenue_cents'];
        }
        
        // Add summary
        fputcsv($csv, []);
        fputcsv($csv, ['Summary']);
        fputcsv($csv, ['Total Bookings', $total_bookings]);
        fputcsv($csv, ['Total Revenue', number_format($total_revenue / 100, 2)]);
        fputcsv($csv, ['Period', "$from to $to"]);
        
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        
        // Upload to Drive
        $filename = 'classflow_revenue_' . $period . '_' . date('Y-m-d') . '.csv';
        return self::upload_file($filename, $content, 'text/csv');
    }
    
    /**
     * Backup database tables to Drive
     */
    public static function backup_database(): ?string
    {
        if (!Settings::get('google_drive_enabled')) {
            return null;
        }
        
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'cfp_bookings',
            $wpdb->prefix . 'cfp_schedules',
            $wpdb->prefix . 'cfp_customers',
            $wpdb->prefix . 'cfp_locations',
            $wpdb->prefix . 'cfp_waitlist',
        ];
        
        $backup = [];
        
        foreach ($tables as $table) {
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                continue;
            }
            
            // Get table structure
            $create = $wpdb->get_row("SHOW CREATE TABLE $table", ARRAY_A);
            $backup[$table]['create'] = $create['Create Table'] ?? '';
            
            // Get table data
            $data = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            $backup[$table]['data'] = $data;
        }
        
        // Convert to JSON
        $json = json_encode($backup, JSON_PRETTY_PRINT);
        
        // Upload to Drive
        $filename = 'classflow_backup_' . date('Y-m-d_His') . '.json';
        return self::upload_file($filename, $json, 'application/json');
    }
    
    /**
     * Log backup information
     */
    private static function log_backup(string $filename, string $file_id): void
    {
        $backups = get_option('cfp_drive_backups', []);
        
        $backups[] = [
            'filename' => $filename,
            'file_id' => $file_id,
            'created_at' => current_time('mysql'),
        ];
        
        // Keep only last 100 backups
        if (count($backups) > 100) {
            $backups = array_slice($backups, -100);
        }
        
        update_option('cfp_drive_backups', $backups, false);
    }
    
    /**
     * Schedule automatic exports
     */
    public static function schedule_auto_exports(): void
    {
        if (!Settings::get('google_drive_auto_export')) {
            wp_clear_scheduled_hook('cfp_drive_auto_export');
            return;
        }
        
        if (!wp_next_scheduled('cfp_drive_auto_export')) {
            wp_schedule_event(time(), 'daily', 'cfp_drive_auto_export');
        }
    }
    
    /**
     * Run automatic exports
     */
    public static function run_auto_exports(): void
    {
        if (!Settings::get('google_drive_enabled') || !Settings::get('google_drive_auto_export')) {
            return;
        }
        
        // Export today's bookings
        $today = date('Y-m-d');
        self::export_bookings($today . ' 00:00:00', $today . ' 23:59:59');
        
        // Export weekly revenue report on Sundays
        if (date('w') == 0) {
            self::export_revenue_report('week');
        }
        
        // Export monthly revenue report on the 1st
        if (date('j') == 1) {
            self::export_revenue_report('month');
        }
        
        // Backup database weekly
        if (date('w') == 0) {
            self::backup_database();
        }
    }
}