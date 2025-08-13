<?php
namespace ClassFlowPro\Utils;

class Permissions
{
    public static function allowed_location_ids_for_user(?int $user_id = null): array
    {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) return [];
        $u = get_user_by('id', $user_id);
        if (!$u) return [];
        // Managers/admins can see all
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'cfp_manage')) return [];
        $raw = get_user_meta($user_id, 'cfp_locations', true);
        if (is_array($raw)) return array_values(array_filter(array_map('intval', $raw)));
        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $raw))));
        }
        return [];
    }
}

