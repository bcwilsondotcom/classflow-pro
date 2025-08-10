<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class BookPrivateWidget extends Widget_Base
{
    public function get_name() { return 'cfp_book_private'; }
    public function get_title() { return __('CFP – Book Private Session', 'classflow-pro'); }
    public function get_icon() { return 'eicon-user-circle-o'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-book-private" data-private="1" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h4>' . esc_html__('Book a Private Session', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-private-form">';
        echo '<label>' . esc_html__('Choose Instructor (post ID)', 'classflow-pro') . ' <input type="number" class="cfp-instructor-id" min="1" step="1"></label>';
        echo '<label>' . esc_html__('Preferred Date', 'classflow-pro') . ' <input type="date" class="cfp-date"></label>';
        echo '<label>' . esc_html__('Preferred Time', 'classflow-pro') . ' <input type="time" class="cfp-time"></label>';
        echo '<label>' . esc_html__('Notes', 'classflow-pro') . ' <textarea class="cfp-notes"></textarea></label>';
        echo '<label>' . esc_html__('Your name', 'classflow-pro') . ' <input type="text" class="cfp-name"></label>';
        echo '<label>' . esc_html__('Email', 'classflow-pro') . ' <input type="email" class="cfp-email"></label>';
        echo '<button class="button button-primary cfp-request-private">' . esc_html__('Request Private Session', 'classflow-pro') . '</button>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>'; 
        echo '</div>';
    }
}
