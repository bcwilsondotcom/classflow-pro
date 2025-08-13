<?php
namespace ClassFlowPro\Admin;

class Import
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $message = '';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cfp_action']) && check_admin_referer('cfp_import_csv')) {
            $type = sanitize_key((string)$_POST['cfp_action']);
            if (!empty($_FILES['csv']['tmp_name'])) {
                $message = self::handle_upload($type, $_FILES['csv']);
            } else {
                $message = __('Please choose a CSV file.', 'classflow-pro');
            }
        }
        echo '<div class="wrap"><h1>' . esc_html__('Import', 'classflow-pro') . '</h1>';
        if ($message) echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        echo '<p>' . esc_html__('Upload CSV exports to import Customers, Credits, and Classes/Schedules. Supports MyBestStudio exports (auto-mapping columns).', 'classflow-pro') . '</p>';
        $forms = [
            'import_customers' => __('Import Customers', 'classflow-pro'),
            'import_credits' => __('Import Credits (Packages)', 'classflow-pro'),
            'import_schedules' => __('Import Classes/Schedules', 'classflow-pro'),
        ];
        foreach ($forms as $action => $label) {
            echo '<h2>' . esc_html($label) . '</h2>';
            echo '<form method="post" enctype="multipart/form-data">'; wp_nonce_field('cfp_import_csv');
            echo '<input type="hidden" name="cfp_action" value="' . esc_attr($action) . '" />';
            echo '<input type="file" name="csv" accept=".csv" required /> ';
            echo '<button class="button button-primary" type="submit">' . esc_html__('Upload & Import', 'classflow-pro') . '</button>';
            echo '</form>';
        }
        echo '</div>';
    }

    private static function handle_upload(string $action, array $file): string
    {
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return __('File upload failed.', 'classflow-pro');
        }
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            return __('Invalid file type. Please upload a CSV file.', 'classflow-pro');
        }
        
        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            return __('Invalid file extension. Please upload a CSV file.', 'classflow-pro');
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return __('File size too large. Maximum size is 10MB.', 'classflow-pro');
        }
        
        $tmp = $file['tmp_name'];
        $count = 0; $errors = 0;
        if (($fh = fopen($tmp, 'r')) !== false) {
            $header = fgetcsv($fh);
            if (!is_array($header)) { fclose($fh); return __('Invalid CSV header.', 'classflow-pro'); }
            $map = self::detect_mapping($action, $header);
            while (($row = fgetcsv($fh)) !== false) {
                try {
                    switch ($action) {
                        case 'import_customers': self::import_customer_row($map, $header, $row); break;
                        case 'import_credits': self::import_credits_row($map, $header, $row); break;
                        case 'import_schedules': self::import_schedule_row($map, $header, $row); break;
                    }
                    $count++;
                } catch (\Throwable $e) { $errors++; }
            }
            fclose($fh);
        }
        return sprintf(__('Imported %d rows (%d errors).', 'classflow-pro'), $count, $errors);
    }

    private static function detect_mapping(string $action, array $header): array
    {
        // Lowercase, trim header names for matching
        $h = array_map(function($v){ return strtolower(trim((string)$v)); }, $header);
        $idx = function($keys) use ($h){ foreach ((array)$keys as $k){ $i = array_search($k, $h, true); if ($i !== false) return $i; } return -1; };
        if ($action === 'import_customers') {
            return [
                'email' => $idx(['email','email address','client email']),
                'first' => $idx(['first name','firstname','first']),
                'last' => $idx(['last name','lastname','last']),
                'phone' => $idx(['phone','phone number','mobile']),
                'dob' => $idx(['dob','date of birth','birthdate']),
                'notes' => $idx(['notes','note','comments']),
            ];
        } elseif ($action === 'import_credits') {
            return [
                'email' => $idx(['email','email address','client email']),
                'name' => $idx(['package','package name','product']),
                'credits' => $idx(['credits','remaining credits','credit balance']),
                'price' => $idx(['price','amount paid','price cents']),
            ];
        }
        // schedules/classes
        return [
            'class' => $idx(['class','class name','service name']),
            'date' => $idx(['date','start date','class date']),
            'time' => $idx(['time','start time','class time']),
            'instructor' => $idx(['instructor','teacher']),
            'location' => $idx(['location','studio']),
            'capacity' => $idx(['capacity','seats']),
        ];
    }

    private static function import_customer_row(array $map, array $header, array $row): void
    {
        $val = function($k) use ($map,$row){ $i=$map[$k]??-1; return $i>=0?trim((string)$row[$i]):''; };
        $email = sanitize_email($val('email'));
        if (!$email) return;
        $first = sanitize_text_field($val('first')); $last = sanitize_text_field($val('last'));
        $phone = sanitize_text_field($val('phone')); $dob = sanitize_text_field($val('dob')); $notes = sanitize_textarea_field($val('notes'));
        if ($u = get_user_by('email', $email)) {
            // Update meta
            if ($phone) update_user_meta($u->ID, 'cfp_phone', $phone);
            if ($dob) update_user_meta($u->ID, 'cfp_dob', $dob);
            if ($notes) add_user_meta($u->ID, 'cfp_import_note', $notes);
        } else {
            $username = sanitize_user(current(explode('@', $email))); if (username_exists($username)) { $username .= '_' . wp_generate_password(4, false, false); }
            $uid = wp_create_user($username, wp_generate_password(12, true), $email);
            if (!is_wp_error($uid)) {
                wp_update_user(['ID'=>$uid, 'display_name'=> trim($first . ' ' . $last)]);
                $user = new \WP_User($uid); $user->set_role('customer');
                if ($phone) update_user_meta($uid, 'cfp_phone', $phone);
                if ($dob) update_user_meta($uid, 'cfp_dob', $dob);
                if ($notes) add_user_meta($uid, 'cfp_import_note', $notes);
            }
        }
    }

    private static function import_credits_row(array $map, array $header, array $row): void
    {
        $val = function($k) use ($map,$row){ $i=$map[$k]??-1; return $i>=0?trim((string)$row[$i]):''; };
        $email = sanitize_email($val('email')); if (!$email) return; $u = get_user_by('email', $email); $uid = $u ? (int)$u->ID : 0;
        $name = sanitize_text_field($val('name')) ?: __('Imported Package','classflow-pro');
        $credits = max(0, (int)$val('credits'));
        $price_cents = max(0, (int)round(floatval($val('price'))*100));
        if ($uid) { \ClassFlowPro\Packages\Manager::grant_package($uid, $name, $credits, $price_cents, 'usd', null); }
    }

    private static function import_schedule_row(array $map, array $header, array $row): void
    {
        global $wpdb;
        $val = function($k) use ($map,$row){ $i=$map[$k]??-1; return $i>=0?trim((string)$row[$i]):''; };
        $class_name = sanitize_text_field($val('class')); if (!$class_name) return;
        // Ensure class exists
        $c = $wpdb->prefix.'cfp_classes';
        $class_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $c WHERE name=%s", $class_name));
        if (!$class_id) { $wpdb->insert($c, ['name'=>$class_name, 'price_cents'=>0, 'currency'=>'usd'], ['%s','%d','%s']); $class_id = (int)$wpdb->insert_id; }
        // Location ensure
        $loc_name = sanitize_text_field($val('location')); $loc_id = null;
        if ($loc_name) {
            $lt = $wpdb->prefix.'cfp_locations';
            $loc_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $lt WHERE name=%s", $loc_name));
            if (!$loc_id) { $wpdb->insert($lt, ['name'=>$loc_name, 'timezone'=>\ClassFlowPro\Admin\Settings::get('business_timezone','UTC')], ['%s','%s']); $loc_id = (int)$wpdb->insert_id; }
        }
        // Instructor ensure (name only)
        $ins_name = sanitize_text_field($val('instructor')); $ins_id = null;
        if ($ins_name) {
            $it = $wpdb->prefix.'cfp_instructors';
            $ins_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $it WHERE name=%s", $ins_name));
            if (!$ins_id) { $wpdb->insert($it, ['name'=>$ins_name], ['%s']); $ins_id = (int)$wpdb->insert_id; }
        }
        $date = $val('date'); $time = $val('time'); if (!$date || !$time) return;
        $tz = \ClassFlowPro\Admin\Settings::get('business_timezone', 'UTC');
        $dt = new \DateTime($date . ' ' . $time, new \DateTimeZone($tz));
        $start = clone $dt; $end = clone $dt; $end->modify('+60 minutes');
        $start->setTimezone(new \DateTimeZone('UTC')); $end->setTimezone(new \DateTimeZone('UTC'));
        $capacity = max(1, (int)$val('capacity'));
        $wpdb->insert($wpdb->prefix.'cfp_schedules', [
            'class_id'=>$class_id,
            'instructor_id'=>$ins_id?:null,
            'location_id'=>$loc_id?:null,
            'start_time'=>$start->format('Y-m-d H:i:s'),
            'end_time'=>$end->format('Y-m-d H:i:s'),
            'capacity'=>$capacity,
            'price_cents'=>0,
            'currency'=>'usd',
            'is_private'=>0,
        ], ['%d','%d','%d','%s','%s','%d','%d','%s','%d']);
    }
}

