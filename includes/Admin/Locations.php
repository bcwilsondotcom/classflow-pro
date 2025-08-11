<?php
namespace ClassFlowPro\Admin;
if (!defined('ABSPATH')) { exit; }

class Locations
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['cfp_action']) && $_POST['cfp_action']==='save') { self::handle_save(); return; }
        if ((isset($_GET['action']) && $_GET['action']==='delete') && isset($_GET['id'])) { self::handle_delete(); return; }
        $action = $_GET['action'] ?? 'list';
        if ($action==='new' || $action==='edit') self::render_form(); else self::render_list();
    }
    private static function render_list(): void
    {
        $page=isset($_GET['paged'])?max(1,(int)$_GET['paged']):1; $per=20; $s=isset($_GET['s'])?sanitize_text_field($_GET['s']):'';
        $repo=new \ClassFlowPro\DB\Repositories\LocationsRepository(); $res=$repo->paginate($page,$per,$s); $items=$res['items']; $total=(int)$res['total'];
        ?>
        <div class="wrap"><h1 class="wp-heading-inline"><?php esc_html_e('Locations','classflow-pro'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-locations&action=new')); ?>" class="page-title-action"><?php esc_html_e('Add New','classflow-pro'); ?></a>
        <hr class="wp-header-end"/>
        <form method="get"><input type="hidden" name="page" value="classflow-pro-locations"/><p class="search-box"><label class="screen-reader-text" for="loc-search"><?php esc_html_e('Search Locations','classflow-pro'); ?></label><input type="search" id="loc-search" name="s" value="<?php echo esc_attr($s); ?>"/><?php submit_button(__('Search'),'button','',false); ?></p></form>
        <table class="wp-list-table widefat fixed striped"><thead><tr><th><?php esc_html_e('Name','classflow-pro'); ?></th><th><?php esc_html_e('Timezone','classflow-pro'); ?></th><th><?php esc_html_e('Actions','classflow-pro'); ?></th></tr></thead><tbody>
        <?php if(empty($items)): ?><tr><td colspan="3"><?php esc_html_e('No locations found.','classflow-pro'); ?></td></tr><?php else: foreach($items as $r): ?>
        <tr><td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-locations&action=edit&id='.$r['id'])); ?>"><?php echo esc_html($r['name']); ?></a></strong></td><td><?php echo esc_html($r['timezone']?:''); ?></td>
        <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-locations&action=edit&id='.$r['id'])); ?>"><?php esc_html_e('Edit','classflow-pro'); ?></a>
        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=classflow-pro-locations&action=delete&id='.$r['id']),'cfp_delete_location_'.$r['id'])); ?>" onclick="return confirm('<?php echo esc_attr__('Are you sure?','classflow-pro'); ?>');"><?php esc_html_e('Delete','classflow-pro'); ?></a></td></tr>
        <?php endforeach; endif; ?></tbody></table>
        <?php $pages=(int)ceil($total/$per); if($pages>1){ echo '<div class="tablenav bottom"><div class="tablenav-pages">'.paginate_links(['base'=>add_query_arg('paged','%#%'),'total'=>$pages,'current'=>$page]).'</div></div>'; } ?>
        </div>
        <?php
    }
    private static function render_form(): void
    {
        $id=isset($_GET['id'])?(int)$_GET['id']:0; $repo=new \ClassFlowPro\DB\Repositories\LocationsRepository(); $r=$id?$repo->find($id):null; if($id && !$r) wp_die(esc_html__('Invalid location.','classflow-pro'));
        $name=$r['name']??''; $tz=$r['timezone']??(wp_timezone_string()); $address1=$r['address1']??''; $address2=$r['address2']??''; $city=$r['city']??''; $state=$r['state']??''; $postal=$r['postal_code']??''; $country=$r['country']??'US';
        
        // Common countries for dropdown
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'PT' => 'Portugal',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'ZA' => 'South Africa',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'KR' => 'South Korea',
            'IL' => 'Israel',
            'AE' => 'United Arab Emirates',
        ];
        
        // US States
        $us_states = [
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
            'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
            'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
            'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
            'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
            'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
            'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
            'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
            'DC' => 'District of Columbia'
        ];
        
        // Canadian Provinces
        $ca_provinces = [
            'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
            'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories',
            'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec',
            'SK' => 'Saskatchewan', 'YT' => 'Yukon'
        ];
        ?>
        <div class="wrap"><h1><?php echo $id?esc_html__('Edit Location','classflow-pro'):esc_html__('Add Location','classflow-pro'); ?></h1>
        <form method="post"><?php wp_nonce_field('cfp_save_location'); ?><input type="hidden" name="cfp_action" value="save"/><?php if($id):?><input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>"/><?php endif; ?>
        <table class="form-table">
        <tr><th><label for="name"><?php esc_html_e('Location Name','classflow-pro'); ?></label></th>
            <td><input class="regular-text" id="name" name="name" value="<?php echo esc_attr($name); ?>" placeholder="<?php esc_attr_e('e.g., Downtown Studio','classflow-pro'); ?>" required/>
            <p class="description"><?php esc_html_e('A friendly name for this location','classflow-pro'); ?></p></td></tr>
        
        <tr><th scope="row"><?php esc_html_e('Address','classflow-pro'); ?></th>
            <td>
                <div style="margin-bottom:10px;">
                    <input class="regular-text" id="address1" name="address1" value="<?php echo esc_attr($address1); ?>" placeholder="<?php esc_attr_e('Street address','classflow-pro'); ?>" style="width:100%;max-width:400px;"/>
                </div>
                <div style="margin-bottom:10px;">
                    <input class="regular-text" id="address2" name="address2" value="<?php echo esc_attr($address2); ?>" placeholder="<?php esc_attr_e('Apartment, suite, unit, etc. (optional)','classflow-pro'); ?>" style="width:100%;max-width:400px;"/>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <input id="city" name="city" value="<?php echo esc_attr($city); ?>" placeholder="<?php esc_attr_e('City','classflow-pro'); ?>" style="flex:1;min-width:150px;"/>
                    <select id="state" name="state" style="flex:0 0 200px;">
                        <option value=""><?php esc_html_e('Select State/Province','classflow-pro'); ?></option>
                        <optgroup label="<?php esc_attr_e('United States','classflow-pro'); ?>" class="us-states">
                            <?php foreach($us_states as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($state, $code); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Canada','classflow-pro'); ?>" class="ca-provinces" style="display:none;">
                            <?php foreach($ca_provinces as $code => $label): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($state, $code); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Other','classflow-pro'); ?>" class="other-states" style="display:none;">
                            <option value="other"><?php esc_html_e('Enter manually','classflow-pro'); ?></option>
                        </optgroup>
                    </select>
                    <input id="state_other" name="state_other" value="<?php echo esc_attr($state); ?>" placeholder="<?php esc_attr_e('State/Province','classflow-pro'); ?>" style="flex:0 0 150px;display:none;"/>
                    <input id="postal" name="postal_code" value="<?php echo esc_attr($postal); ?>" placeholder="<?php esc_attr_e('ZIP/Postal Code','classflow-pro'); ?>" style="flex:0 0 120px;"/>
                </div>
            </td>
        </tr>
        
        <tr><th><label for="country"><?php esc_html_e('Country','classflow-pro'); ?></label></th>
            <td>
                <select id="country" name="country" class="regular-text">
                    <?php foreach($countries as $code => $label): ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($country, $code); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        
        <tr><th><label for="timezone"><?php esc_html_e('Timezone','classflow-pro'); ?></label></th>
            <td>
                <select id="timezone" name="timezone" class="regular-text">
                    <?php echo wp_timezone_choice($tz); ?>
                </select>
                <p class="description">
                    <span id="tz-status"><?php esc_html_e('Timezone will be automatically detected based on address','classflow-pro'); ?></span>
                    <button type="button" class="button button-small" id="detect-timezone" style="margin-left:10px;"><?php esc_html_e('Auto-detect','classflow-pro'); ?></button>
                </p>
            </td>
        </tr>
        </table>
        <?php submit_button($id?__('Update Location','classflow-pro'):__('Add Location','classflow-pro')); ?>
        </form></div>
        
        <style>
        #state optgroup { font-weight: bold; }
        #state option { font-weight: normal; }
        .form-table input[type="text"], .form-table input[type="email"], .form-table input[type="url"], .form-table select { font-size: 14px; padding: 6px 8px; }
        </style>
        
        <script>
        jQuery(function($) {
            // Country change handler
            $('#country').on('change', function() {
                var country = $(this).val();
                var $state = $('#state');
                var $stateOther = $('#state_other');
                
                // Reset state dropdown
                $state.find('optgroup').hide();
                $state.val('');
                
                if (country === 'US') {
                    $state.find('.us-states').show();
                    $state.show();
                    $stateOther.hide();
                } else if (country === 'CA') {
                    $state.find('.ca-provinces').show();
                    $state.show();
                    $stateOther.hide();
                } else {
                    $state.find('.other-states').show();
                    $state.show();
                    // For other countries, show text input
                    $state.val('other');
                    $stateOther.show();
                }
            });
            
            // State dropdown change
            $('#state').on('change', function() {
                if ($(this).val() === 'other') {
                    $('#state_other').show();
                } else {
                    $('#state_other').hide();
                }
            });
            
            // Initialize on load
            $('#country').trigger('change');
            
            // Auto-detect timezone
            function detectTimezone() {
                var city = $('#city').val();
                var state = $('#state').val() === 'other' ? $('#state_other').val() : $('#state').val();
                var country = $('#country').val();
                
                if (!city || !country) {
                    $('#tz-status').text('<?php echo esc_js(__('Please enter city and country first','classflow-pro')); ?>').css('color', '#d63638');
                    return;
                }
                
                $('#tz-status').text('<?php echo esc_js(__('Detecting timezone...','classflow-pro')); ?>').css('color', '#2271b1');
                
                // Use Google Maps Time Zone API or a simpler mapping
                // For now, we'll use a simple mapping based on common locations
                var timezoneMap = {
                    'US': {
                        'CA': 'America/Los_Angeles',
                        'WA': 'America/Los_Angeles', 
                        'OR': 'America/Los_Angeles',
                        'NV': 'America/Los_Angeles',
                        'AZ': 'America/Phoenix',
                        'MT': 'America/Denver',
                        'ID': 'America/Denver',
                        'WY': 'America/Denver',
                        'UT': 'America/Denver',
                        'CO': 'America/Denver',
                        'NM': 'America/Denver',
                        'TX': 'America/Chicago',
                        'OK': 'America/Chicago',
                        'KS': 'America/Chicago',
                        'NE': 'America/Chicago',
                        'SD': 'America/Chicago',
                        'ND': 'America/Chicago',
                        'MN': 'America/Chicago',
                        'IA': 'America/Chicago',
                        'MO': 'America/Chicago',
                        'AR': 'America/Chicago',
                        'LA': 'America/Chicago',
                        'WI': 'America/Chicago',
                        'IL': 'America/Chicago',
                        'MS': 'America/Chicago',
                        'AL': 'America/Chicago',
                        'TN': 'America/Chicago',
                        'KY': 'America/Chicago',
                        'IN': 'America/New_York',
                        'MI': 'America/New_York',
                        'OH': 'America/New_York',
                        'WV': 'America/New_York',
                        'VA': 'America/New_York',
                        'PA': 'America/New_York',
                        'NY': 'America/New_York',
                        'VT': 'America/New_York',
                        'NH': 'America/New_York',
                        'ME': 'America/New_York',
                        'MA': 'America/New_York',
                        'RI': 'America/New_York',
                        'CT': 'America/New_York',
                        'NJ': 'America/New_York',
                        'DE': 'America/New_York',
                        'MD': 'America/New_York',
                        'DC': 'America/New_York',
                        'NC': 'America/New_York',
                        'SC': 'America/New_York',
                        'GA': 'America/New_York',
                        'FL': 'America/New_York',
                        'HI': 'Pacific/Honolulu',
                        'AK': 'America/Anchorage'
                    },
                    'CA': {
                        'BC': 'America/Vancouver',
                        'AB': 'America/Edmonton',
                        'SK': 'America/Regina',
                        'MB': 'America/Winnipeg',
                        'ON': 'America/Toronto',
                        'QC': 'America/Montreal',
                        'NB': 'America/Halifax',
                        'NS': 'America/Halifax',
                        'PE': 'America/Halifax',
                        'NL': 'America/St_Johns',
                        'YT': 'America/Whitehorse',
                        'NT': 'America/Yellowknife',
                        'NU': 'America/Iqaluit'
                    },
                    'GB': 'Europe/London',
                    'FR': 'Europe/Paris',
                    'DE': 'Europe/Berlin',
                    'ES': 'Europe/Madrid',
                    'IT': 'Europe/Rome',
                    'AU': 'Australia/Sydney',
                    'NZ': 'Pacific/Auckland',
                    'JP': 'Asia/Tokyo',
                    'CN': 'Asia/Shanghai',
                    'IN': 'Asia/Kolkata',
                    'BR': 'America/Sao_Paulo',
                    'MX': 'America/Mexico_City',
                    'AR': 'America/Argentina/Buenos_Aires'
                };
                
                var detectedTz = null;
                if (timezoneMap[country]) {
                    if (typeof timezoneMap[country] === 'string') {
                        detectedTz = timezoneMap[country];
                    } else if (state && timezoneMap[country][state]) {
                        detectedTz = timezoneMap[country][state];
                    }
                }
                
                if (detectedTz) {
                    $('#timezone').val(detectedTz);
                    $('#tz-status').text('<?php echo esc_js(__('Timezone detected:','classflow-pro')); ?> ' + detectedTz).css('color', '#00a32a');
                } else {
                    $('#tz-status').text('<?php echo esc_js(__('Could not detect timezone. Please select manually.','classflow-pro')); ?>').css('color', '#996800');
                }
            }
            
            $('#detect-timezone').on('click', function(e) {
                e.preventDefault();
                detectTimezone();
            });
            
            // Auto-detect on address change
            $('#city, #state, #country').on('change', function() {
                if ($('#city').val() && $('#country').val()) {
                    detectTimezone();
                }
            });
        });
        </script>
        <?php
    }
    private static function handle_save(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'cfp_save_location')) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $id=isset($_POST['id'])?(int)$_POST['id']:0; $repo=new \ClassFlowPro\DB\Repositories\LocationsRepository();
        
        // Handle state field - use state_other if state is 'other'
        $state = sanitize_text_field($_POST['state'] ?? '');
        if ($state === 'other') {
            $state = sanitize_text_field($_POST['state_other'] ?? '');
        }
        
        $data=[ 
            'name'=>sanitize_text_field($_POST['name']??''), 
            'address1'=>sanitize_text_field($_POST['address1']??''), 
            'address2'=>sanitize_text_field($_POST['address2']??''), 
            'city'=>sanitize_text_field($_POST['city']??''), 
            'state'=>$state, 
            'postal_code'=>sanitize_text_field($_POST['postal_code']??''), 
            'country'=>sanitize_text_field($_POST['country']??''), 
            'timezone'=>sanitize_text_field($_POST['timezone']??'') 
        ];
        if($id){ $repo->update($id,$data); $m='updated'; } else { $repo->create($data); $m='created'; }
        wp_safe_redirect(admin_url('admin.php?page=classflow-pro-locations&message='.$m)); exit;
    }
    private static function handle_delete(): void
    {
        $id=isset($_GET['id'])?(int)$_GET['id']:0; if(!$id) wp_die(esc_html__('Invalid request.','classflow-pro'));
        if (!isset($_GET['_wpnonce'])||!wp_verify_nonce($_GET['_wpnonce'],'cfp_delete_location_'.$id)) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $repo=new \ClassFlowPro\DB\Repositories\LocationsRepository(); $repo->delete($id); wp_safe_redirect(admin_url('admin.php?page=classflow-pro-locations&message=deleted')); exit;
    }
}

