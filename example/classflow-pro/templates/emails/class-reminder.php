<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Class Reminder', 'classflow-pro'); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #17a2b8; padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; text-align: center;">
                                <?php esc_html_e('Class Reminder', 'classflow-pro'); ?>
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                                <?php printf(esc_html__('Hi %s,', 'classflow-pro'), $student_name); ?>
                            </p>
                            
                            <p style="color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 30px;">
                                <?php 
                                if ($hours_until_class <= 1) {
                                    esc_html_e('Your class is starting soon!', 'classflow-pro');
                                } else {
                                    printf(
                                        esc_html__('This is a reminder that you have a class in %s hours.', 'classflow-pro'),
                                        number_format($hours_until_class, 0)
                                    );
                                }
                                ?>
                            </p>
                            
                            <!-- Class Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <h2 style="color: #17a2b8; font-size: 20px; margin: 0 0 15px;">
                                            <?php echo esc_html($class_name); ?>
                                        </h2>
                                        
                                        <table width="100%" cellpadding="5" cellspacing="0">
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Date:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($class_date); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Time:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($class_time . ' - ' . $class_end_time); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Instructor:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($instructor_name); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Location:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($location_name); ?>
                                                    <?php if (!empty($location_address)): ?>
                                                        <br><small style="color: #666666;"><?php echo esc_html($location_address); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Preparation Tips -->
                            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 30px;">
                                <p style="color: #856404; font-size: 14px; margin: 0;">
                                    <strong><?php esc_html_e('Preparation Tips:', 'classflow-pro'); ?></strong><br>
                                    <?php esc_html_e('• Arrive 10 minutes early', 'classflow-pro'); ?><br>
                                    <?php esc_html_e('• Bring water and a towel', 'classflow-pro'); ?><br>
                                    <?php esc_html_e('• Wear comfortable clothing', 'classflow-pro'); ?>
                                </p>
                            </div>
                            
                            <!-- Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                <tr>
                                    <td align="center">
                                        <a href="<?php echo esc_url($booking_url); ?>" 
                                           style="display: inline-block; background-color: #2271b1; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 4px; font-size: 16px;">
                                            <?php esc_html_e('View Booking Details', 'classflow-pro'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                <?php esc_html_e('We look forward to seeing you in class!', 'classflow-pro'); ?>
                            </p>
                            
                            <p style="color: #666666; font-size: 12px; line-height: 1.6; margin: 20px 0 0; padding-top: 20px; border-top: 1px solid #e9ecef;">
                                <?php esc_html_e('Need to cancel? Please do so at least 24 hours in advance through your account.', 'classflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="color: #666666; font-size: 12px; margin: 0 0 10px;">
                                <?php echo esc_html($site_name); ?><br>
                                <a href="<?php echo esc_url($site_url); ?>" style="color: #2271b1; text-decoration: none;">
                                    <?php echo esc_url($site_url); ?>
                                </a>
                            </p>
                            <p style="color: #999999; font-size: 12px; margin: 0;">
                                <?php esc_html_e('You received this reminder because you have an upcoming class booking.', 'classflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>