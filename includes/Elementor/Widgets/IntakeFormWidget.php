<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class IntakeFormWidget extends Widget_Base
{
    public function get_name() { return 'cfp_intake_form'; }
    public function get_title() { return __('CFP – Intake Form', 'classflow-pro'); }
    public function get_icon() { return 'eicon-form-horizontal'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        if (!is_user_logged_in()) { echo '<p>' . esc_html__('Please log in to complete intake.', 'classflow-pro') . '</p>'; return; }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-intake', CFP_PLUGIN_URL . 'assets/js/intake.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-intake" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h3>' . esc_html__('Client Intake Form', 'classflow-pro') . '</h3>';
        $s = get_option('cfp_settings', []);
        if (!empty($s['intake_intro'])) echo '<div class="cfp-intro" style="margin-bottom:8px;">' . wp_kses_post($s['intake_intro']) . '</div>';
        if (!empty($s['intake_privacy'])) echo '<div class="cfp-privacy" style="border:1px solid #e2e8f0;padding:8px;border-radius:6px;margin-bottom:8px;max-height:200px;overflow:auto;">' . wp_kses_post($s['intake_privacy']) . '</div>';
        if (!empty($s['intake_waiver'])) echo '<div class="cfp-waiver" style="border:1px solid #e2e8f0;padding:8px;border-radius:6px;margin-bottom:8px;max-height:240px;overflow:auto;">' . wp_kses_post($s['intake_waiver']) . '</div>';
        echo '<div class="cfp-intake-form">';
        echo '<label>' . esc_html__('Phone', 'classflow-pro') . ' <input type="tel" class="cfp-phone"></label>';
        echo '<label>' . esc_html__('Date of Birth', 'classflow-pro') . ' <input type="date" class="cfp-dob"></label>';
        echo '<label>' . esc_html__('Emergency Contact Name', 'classflow-pro') . ' <input type="text" class="cfp-emg-name"></label>';
        echo '<label>' . esc_html__('Emergency Contact Phone', 'classflow-pro') . ' <input type="tel" class="cfp-emg-phone"></label>';
        echo '<label>' . esc_html__('Medical Conditions', 'classflow-pro') . ' <textarea class="cfp-med"></textarea></label>';
        echo '<label>' . esc_html__('Injuries/Surgeries', 'classflow-pro') . ' <textarea class="cfp-inj"></textarea></label>';
        $prompts = array_filter(array_map('trim', explode("\n", (string)($s['intake_prompts'] ?? ''))));
        $i=0; foreach ($prompts as $p) { $i++; echo '<label>' . esc_html($p) . ' <textarea class="cfp-prompt" data-key="' . esc_attr('prompt_'.$i) . '"></textarea></label>'; }
        echo '<label><input type="checkbox" class="cfp-preg"> ' . esc_html__('Currently pregnant', 'classflow-pro') . '</label>';
        echo '<label>' . esc_html__('Type Full Name as Signature', 'classflow-pro') . ' <input type="text" class="cfp-sign"></label>';
        echo '<label><input type="checkbox" class="cfp-consent"> ' . esc_html__('I agree to the liability waiver and studio policies.', 'classflow-pro') . '</label>';
        echo '<button class="button button-primary cfp-intake-submit">' . esc_html__('Submit Intake', 'classflow-pro') . '</button>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div></div>';
    }
}
