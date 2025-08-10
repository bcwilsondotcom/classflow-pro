<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Booking Confirmation', 'classflow-pro'); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #2271b1; padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; text-align: center;">
                                <?php esc_html_e('Booking Confirmed!', 'classflow-pro'); ?>
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
                                <?php esc_html_e('Your booking has been confirmed! Here are the details:', 'classflow-pro'); ?>
                            </p>
                            
                            <!-- Booking Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <h2 style="color: #2271b1; font-size: 20px; margin: 0 0 15px;">
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
                                                    <strong><?php esc_html_e('Duration:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($class_duration); ?>
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
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Booking Code:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <code style="background-color: #e9ecef; padding: 2px 6px; border-radius: 3px;">
                                                        <?php echo esc_html($booking_code); ?>
                                                    </code>
                                                </td>
                                            </tr>
                                            <?php if (!empty($booking_amount)): ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Amount Paid:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($booking_amount); ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Action Buttons -->
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
                            
                            <!-- Important Notes -->
                            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
                                <p style="color: #856404; font-size: 14px; margin: 0;">
                                    <strong><?php esc_html_e('Important:', 'classflow-pro'); ?></strong><br>
                                    <?php esc_html_e('Please arrive 10 minutes before the class starts. If you need to cancel, please do so at least 24 hours in advance.', 'classflow-pro'); ?>
                                </p>
                            </div>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                <?php esc_html_e('If you have any questions, please don\'t hesitate to contact us.', 'classflow-pro'); ?>
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
                                <?php esc_html_e('You received this email because you made a booking on our website.', 'classflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>