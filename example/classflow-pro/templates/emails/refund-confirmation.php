<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Refund Confirmation', 'classflow-pro'); ?></title>
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
                                <?php esc_html_e('Refund Processed', 'classflow-pro'); ?>
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
                                <?php esc_html_e('Your refund has been processed successfully.', 'classflow-pro'); ?>
                            </p>
                            
                            <!-- Refund Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <h2 style="color: #17a2b8; font-size: 20px; margin: 0 0 15px;">
                                            <?php esc_html_e('Refund Details', 'classflow-pro'); ?>
                                        </h2>
                                        
                                        <table width="100%" cellpadding="5" cellspacing="0">
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Refund Amount:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($formatted_refund_amount); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Original Class:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($class_name); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; padding: 5px 0;">
                                                    <strong><?php esc_html_e('Original Date:', 'classflow-pro'); ?></strong>
                                                </td>
                                                <td style="color: #333333; font-size: 14px; padding: 5px 0;">
                                                    <?php echo esc_html($class_date); ?>
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
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Processing Time -->
                            <div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 15px; margin-bottom: 30px;">
                                <p style="color: #155724; font-size: 14px; margin: 0;">
                                    <strong><?php esc_html_e('Processing Time:', 'classflow-pro'); ?></strong><br>
                                    <?php esc_html_e('Your refund will appear in your account within 5-10 business days, depending on your bank.', 'classflow-pro'); ?>
                                </p>
                            </div>
                            
                            <p style="color: #666666; font-size: 14px; line-height: 1.6; margin: 0;">
                                <?php esc_html_e('Thank you for your understanding. We hope to see you in a future class!', 'classflow-pro'); ?>
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
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>