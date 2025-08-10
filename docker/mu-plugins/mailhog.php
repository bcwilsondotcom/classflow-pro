<?php
/*
Plugin Name: ClassFlow Local Mail (MailHog)
Description: Routes all WordPress emails to MailHog in local Docker environment.
Version: 1.0.0
*/

if (!defined('ABSPATH')) { exit; }

add_action('phpmailer_init', function ($phpmailer) {
    // Only route to MailHog when the host is available
    $phpmailer->isSMTP();
    $phpmailer->Host = 'mailhog';
    $phpmailer->Port = 1025;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPSecure = false;
});

