<?php
namespace ClassFlowPro\Admin;

class IntakeForms
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfp_save_intake')) {
            $opt = get_option('cfp_settings', []);
            $opt['intake_intro'] = wp_kses_post($_POST['intake_intro'] ?? '');
            $opt['intake_privacy'] = wp_kses_post($_POST['intake_privacy'] ?? '');
            $opt['intake_waiver'] = wp_kses_post($_POST['intake_waiver'] ?? '');
            $opt['intake_prompts'] = sanitize_textarea_field($_POST['intake_prompts'] ?? "Goals\nPrior injuries/Surgeries\nMedical conditions\nPhysician clearance provided (if needed)");
            update_option('cfp_settings', $opt, false);
            echo '<div class="updated"><p>Saved.</p></div>';
        }
        $s = get_option('cfp_settings', []);
        $intro = esc_textarea($s['intake_intro'] ?? 'Please complete this intake before your first session.');
        $privacy = esc_textarea($s['intake_privacy'] ?? '<p>We value your privacy. Your information is used to provide safe instruction and will not be shared without consent, except as required by law.</p>');
        $waiver = esc_textarea($s['intake_waiver'] ?? '<p>I acknowledge that Pilates involves physical activity and carries inherent risks. I assume all risks associated with participation, release the studio and instructors from liability, and agree to follow instructions and disclose relevant health conditions. I certify that I am physically fit or have consulted a physician.</p><p>Signature and consent required.</p>');
        $prompts = esc_textarea($s['intake_prompts'] ?? "Goals\nPrior injuries/Surgeries\nMedical conditions\nPhysician clearance provided (if needed)");
        echo '<div class="wrap"><h1>Intake Forms</h1>';
        echo '<form method="post">';
        wp_nonce_field('cfp_save_intake');
        echo '<h2>Client Instructions</h2>';
        echo '<textarea name="intake_intro" class="large-text code" rows="4">' . $intro . '</textarea>';
        echo '<h2>Privacy Notice (HTML)</h2>';
        echo '<textarea name="intake_privacy" class="large-text code" rows="8">' . $privacy . '</textarea>';
        echo '<h2>Liability Waiver (HTML)</h2>';
        echo '<textarea name="intake_waiver" class="large-text code" rows="10">' . $waiver . '</textarea>';
        echo '<h2>Instructor Prompts (one per line)</h2>';
        echo '<textarea name="intake_prompts" class="large-text code" rows="6">' . $prompts . '</textarea>';
        submit_button('Save Intake Settings');
        echo '</form></div>';
    }
}

