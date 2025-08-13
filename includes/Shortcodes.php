<?php
namespace ClassFlowPro;

class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('cfp_calendar_booking', [self::class, 'calendar_booking']);
        add_shortcode('cfp_small_calendar_booking', [self::class, 'small_calendar_booking']);
        add_shortcode('cfp_step_booking', [self::class, 'step_booking']);
        add_shortcode('cfp_intake_form', [self::class, 'intake_form']);
        add_shortcode('cfp_booking_funnel', [self::class, 'booking_funnel']);
        add_shortcode('cfp_user_portal', [self::class, 'user_portal']);
        add_shortcode('cfp_checkout_success', [self::class, 'checkout_success']);
        add_shortcode('cfp_waitlist_response', [self::class, 'waitlist_response']);
        // Parity for Elementor-only widgets
        add_shortcode('cfp_book_class', [self::class, 'book_class']);
        add_shortcode('cfp_buy_package', [self::class, 'buy_package']);
        add_shortcode('cfp_book_private', [self::class, 'book_private']);
        add_shortcode('cfp_client_dashboard', [self::class, 'client_dashboard']);
        add_shortcode('cfp_gift_card', [self::class, 'gift_card_purchase']);
        add_shortcode('cfp_gift_card_redeem', [self::class, 'gift_card_redeem']);
        add_shortcode('cfp_series', [self::class, 'series']);
    }

    public static function small_calendar_booking($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_small_calendar_booking');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        ?>
        <div class="cfp-calendar-booking cfp-small-calendar" data-class-id="<?php echo esc_attr((int)$atts['class_id']); ?>" data-location-id="<?php echo esc_attr((int)$atts['location_id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cfp-cal-container">
                <div class="cfp-cal-main">
                    <div class="cfp-cal-toolbar">
                        <div class="cfp-cal-head">
                            <button class="cfp-cal-prev" aria-label="Previous">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <span class="cfp-cal-title">Loading...</span>
                            <button class="cfp-cal-next" aria-label="Next">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                        <div class="cfp-cal-views">
                            <button class="cfp-view active" data-view="month">Month</button>
                            <button class="cfp-view" data-view="week">Week</button>
                            <button class="cfp-view" data-view="agenda">List</button>
                        </div>
                        <div class="cfp-cal-filters">
                            <select class="cfp-filter-class">
                                <option value="">All Classes</option>
                            </select>
                            <select class="cfp-filter-location">
                                <option value="">All Locations</option>
                            </select>
                            <select class="cfp-filter-instructor">
                                <option value="">All Instructors</option>
                            </select>
                            <div class="cfp-cal-legend" style="display:flex;flex-wrap:wrap;gap:8px;margin-left:8px;"></div>
                        </div>
                    </div>
                    <div class="cfp-cal-grid cfp-loading"></div>
                    <div class="cfp-agenda" style="display:none"></div>
                </div>
                <div class="cfp-cal-sidebar">
                    <h4>Book Your Class</h4>
                    <div class="cfp-cal-selected"></div>
                    <label>
                        Your Name
                        <input type="text" class="cfp-name" placeholder="Enter your name" required>
                    </label>
                    <label>
                        Email Address
                        <input type="email" class="cfp-email" placeholder="your@email.com" required>
                    </label>
                    
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" class="cfp-use-credits">
                        <span>Use available credits</span>
                    </label>
                    <button class="cfp-book">Book Class</button>
                    <div class="cfp-payment" style="display:none">
                        <div class="cfp-card-element"></div>
                        <button class="cfp-pay">Complete Payment</button>
                    </div>
                    <div class="cfp-msg" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function calendar_booking($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_calendar_booking');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        ?>
        <div class="cfp-calendar-booking cfp-full-calendar" data-class-id="<?php echo esc_attr((int)$atts['class_id']); ?>" data-location-id="<?php echo esc_attr((int)$atts['location_id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cfp-full-cal-wrapper">
                <div class="cfp-full-cal-header">
                    <div class="cfp-full-cal-nav">
                        <button class="cfp-cal-prev" aria-label="Previous month">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <h2 class="cfp-cal-title">Loading...</h2>
                        <button class="cfp-cal-next" aria-label="Next month">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                    <div class="cfp-full-cal-controls">
                        <div class="cfp-cal-views">
                            <button class="cfp-view active" data-view="month">Month</button>
                            <button class="cfp-view" data-view="week">Week</button>
                            <button class="cfp-view" data-view="agenda">List</button>
                        </div>
                        <div class="cfp-cal-filters">
                            <select class="cfp-filter-class">
                                <option value="">All Classes</option>
                            </select>
                            <select class="cfp-filter-location">
                                <option value="">All Locations</option>
                            </select>
                            <select class="cfp-filter-instructor">
                                <option value="">All Instructors</option>
                            </select>
                            <div class="cfp-cal-legend" style="display:flex;flex-wrap:wrap;gap:8px;margin-left:8px;"></div>
                        </div>
                    </div>
                </div>
                <div class="cfp-full-cal-body">
                    <div class="cfp-full-cal-main">
                        <div class="cfp-cal-grid cfp-loading"></div>
                        <div class="cfp-agenda" style="display:none"></div>
                    </div>
                    <div class="cfp-full-cal-sidebar">
                        <!-- Step Indicator -->
                        <div class="cfp-booking-steps">
                            <div class="cfp-step-indicator active" data-step="1">
                                <span class="cfp-step-number">1</span>
                                <span class="cfp-step-label">Select Classes</span>
                            </div>
                            <div class="cfp-step-connector"></div>
                            <div class="cfp-step-indicator" data-step="2">
                                <span class="cfp-step-number">2</span>
                                <span class="cfp-step-label">Your Details</span>
                            </div>
                            <div class="cfp-step-connector"></div>
                            <div class="cfp-step-indicator" data-step="3">
                                <span class="cfp-step-number">3</span>
                                <span class="cfp-step-label">Confirm</span>
                            </div>
                        </div>

                        <!-- Selected Classes Section -->
                        <div class="cfp-sidebar-section cfp-selected-section">
                            <div class="cfp-section-header">
                                <h3>Selected Classes</h3>
                                <span class="cfp-selected-count">0 selected</span>
                            </div>
                            <div class="cfp-cal-selected">
                                <div class="cfp-empty-selection">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <p>Click on classes in the calendar to select them</p>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Details Section -->
                        <div class="cfp-sidebar-section cfp-details-section">
                            <div class="cfp-section-header">
                                <h3>Booking Details</h3>
                            </div>
                            <form class="cfp-booking-form">
                                <!-- Contact Information Group -->
                                <div class="cfp-form-group">
                                    <h4 class="cfp-form-group-title">Contact Information</h4>
                                    <div class="cfp-input-wrapper">
                                        <label for="cfp-name">Full Name</label>
                                        <input type="text" id="cfp-name" class="cfp-name" placeholder="John Doe" required>
                                    </div>
                                    <div class="cfp-input-wrapper">
                                        <label for="cfp-email">Email Address</label>
                                        <input type="email" id="cfp-email" class="cfp-email" placeholder="john@example.com" required>
                                    </div>
                                    <div class="cfp-input-wrapper">
                                        <label for="cfp-phone">Phone Number <span class="cfp-optional">(optional)</span></label>
                                        <input type="tel" id="cfp-phone" class="cfp-phone" placeholder="(555) 123-4567">
                                    </div>
                                </div>

                                <!-- Account Options Group -->
                                <div class="cfp-form-group">
                                    <h4 class="cfp-form-group-title">Account Options</h4>
                                    <div class="cfp-input-wrapper cfp-password-wrapper">
                                        <label for="cfp-password">Password <span class="cfp-optional">(optional)</span></label>
                                        <input type="password" id="cfp-password" class="cfp-password" autocomplete="new-password" placeholder="Create account password">
                                        <small class="cfp-help-text">Create a password to save your booking history</small>
                                    </div>
                                </div>

                                <!-- Payment Options Group -->
                                <div class="cfp-form-group cfp-payment-options-group">
                                    <h4 class="cfp-form-group-title">Payment Method</h4>
                                    
                                    <!-- Credits Section -->
                                    <div class="cfp-credits-section">
                                        <!-- Will be dynamically populated based on user credits -->
                                        <div class="cfp-credits-container">
                                            <!-- For users with credits -->
                                            <div class="cfp-has-credits" style="display:none;">
                                                <label class="cfp-checkbox-label cfp-styled-checkbox cfp-credits-available">
                                                    <input type="checkbox" class="cfp-use-credits" checked>
                                                    <span class="cfp-checkbox-custom"></span>
                                                    <span class="cfp-checkbox-text">
                                                        <strong>Use Class Credits</strong>
                                                        <small class="cfp-credits-balance">You have <span class="cfp-credit-count">0</span> credits available</small>
                                                    </span>
                                                </label>
                                                <div class="cfp-credits-info">
                                                    <p class="cfp-credits-coverage"></p>
                                                </div>
                                            </div>
                                            
                                            <!-- For users without credits -->
                                            <div class="cfp-no-credits" style="display:none;">
                                                <div class="cfp-package-upsell">
                                                    <div class="cfp-upsell-badge">Save Money!</div>
                                                    <h5>Get Better Value with a Package</h5>
                                                    <p>Purchase a class package and save up to 20% on your bookings</p>
                                                    <button type="button" class="cfp-view-packages">
                                                        <span>View Package Options</span>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="cfp-pay-option">
                                                    <label class="cfp-checkbox-label cfp-styled-checkbox">
                                                        <input type="radio" name="payment_method" value="card" checked>
                                                        <span class="cfp-checkbox-custom"></span>
                                                        <span class="cfp-checkbox-text">
                                                            <strong>Pay with Card</strong>
                                                            <small>One-time payment for selected classes</small>
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SMS Notifications -->
                                    <div class="cfp-notifications-section">
                                        <label class="cfp-checkbox-label cfp-styled-checkbox">
                                            <input type="checkbox" class="cfp-sms-optin">
                                            <span class="cfp-checkbox-custom"></span>
                                            <span class="cfp-checkbox-text">
                                                <strong>SMS Reminders</strong>
                                                <small>Get text reminders 24 hours before your class</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="cfp-form-actions">
                                    <button type="button" class="cfp-book cfp-book-primary">
                                        <span class="cfp-button-text">Proceed to Booking</span>
                                        <svg class="cfp-button-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Payment Section (Hidden by default) -->
                            <div class="cfp-payment" style="display:none">
                                <div class="cfp-payment-header">
                                    <h4>Payment Information</h4>
                                    <span class="cfp-payment-amount"></span>
                                </div>
                                <div class="cfp-card-element"></div>
                                <button class="cfp-pay cfp-pay-button">
                                    <span>Complete Payment</span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Messages -->
                            <div class="cfp-msg" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function step_booking($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-step', CFP_PLUGIN_URL . 'assets/js/step-booking.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-step-booking" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-step cfp-step-1"><h4>Step 1 — Choose</h4><label>Location <select class="cfp-loc"><option value="">All</option></select></label> <label>Class <select class="cfp-class"><option value="">All</option></select></label> <label>Date <input type="date" class="cfp-date"></label> <button class="button cfp-next-1">Next</button></div>';
        echo '<div class="cfp-step cfp-step-2" style="display:none"><h4>Step 2 — Select Time</h4><div class="cfp-times"></div><button class="button cfp-prev-2">Back</button> <button class="button cfp-next-2">Next</button></div>';
        echo '<div class="cfp-step cfp-step-3" style="display:none"><h4>Step 3 — Your Details</h4><label>Name <input type="text" class="cfp-name"></label> <label>Email <input type="email" class="cfp-email" autocomplete="email"></label> <label>Phone <input type="tel" class="cfp-phone" autocomplete="tel"></label> <div class="cfp-account-fields" style="display:block;margin:8px 0;"><label>Create password <input type="password" class="cfp-password" autocomplete="new-password"></label> <small style="display:block;color:#64748b;">If you don\'t have an account, we\'ll create one using this password.</small> <label style="display:block;margin-top:6px;"><input type="checkbox" class="cfp-sms-optin"> Send me text messages about my bookings (optional)</label></div> <label><input type="checkbox" class="cfp-use-credits"> Use credits</label> <button class="button cfp-prev-3">Back</button> <button class="button button-primary cfp-next-3">Review</button></div>';
        echo '<div class="cfp-step cfp-step-4" style="display:none"><h4>Step 4 — Payment</h4><div class="cfp-review"></div><div class="cfp-payment" style="display:none"><div class="cfp-card-element"></div></div><button class="button cfp-prev-4">Back</button> <button class="button button-primary cfp-pay">Pay</button><div class="cfp-msg" aria-live="polite"></div></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function intake_form($atts): string
    {
        if (!is_user_logged_in()) return '<p>Please log in to complete intake.</p>';
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-intake', CFP_PLUGIN_URL . 'assets/js/intake.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-intake" data-nonce="' . esc_attr($nonce) . '"><h3>Client Intake Form</h3><div class="cfp-intake-form">';
        echo '<label>Phone <input type="tel" class="cfp-phone"></label><label>Date of Birth <input type="date" class="cfp-dob"></label><label>Emergency Contact Name <input type="text" class="cfp-emg-name"></label><label>Emergency Contact Phone <input type="tel" class="cfp-emg-phone"></label><label>Medical Conditions <textarea class="cfp-med"></textarea></label><label>Injuries/Surgeries <textarea class="cfp-inj"></textarea></label><label><input type="checkbox" class="cfp-preg"> Currently pregnant</label><label>Type Full Name as Signature <input type="text" class="cfp-sign"></label><label><input type="checkbox" class="cfp-consent"> I agree to the liability waiver and studio policies.</label><button class="button button-primary cfp-intake-submit">Submit Intake</button><div class="cfp-msg" aria-live="polite"></div>';
        echo '</div></div>';
        return ob_get_clean();
    }

    public static function booking_funnel($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        $out = '';
        $login_url = wp_login_url(esc_url(add_query_arg([], home_url($_SERVER['REQUEST_URI'] ?? '/'))));
        $register_url = wp_registration_url();
        if (!is_user_logged_in()) {
            $out .= '<div class="cfp-funnel-auth"><p>Please log in or register to book.</p>';
            $out .= '<p><a class="button" href="' . esc_url($login_url) . '">Log In</a> ';
            $out .= '<a class="button" href="' . esc_url($register_url) . '">Register</a></p></div>';
            return $out;
        }
        // Intake gating if required
        if (\ClassFlowPro\Admin\Settings::get('require_intake', 0)) {
            global $wpdb; $t=$wpdb->prefix.'cfp_intake_forms';
            $has = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE user_id = %d", get_current_user_id()));
            if ($has <= 0) {
                $out .= '<div class="cfp-funnel-intake"><p>Please complete your intake before booking.</p>';
                $out .= do_shortcode('[cfp_intake_form]');
                $out .= '</div>';
                return $out;
            }
        }
        // Show booking widget
        $out .= do_shortcode('[cfp_step_booking]');
        return $out;
    }

    public static function gift_card_purchase($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        $min = (int) Admin\Settings::get('giftcard_min_credits', 1);
        $max = (int) Admin\Settings::get('giftcard_max_credits', 100);
        $value = (int) Admin\Settings::get('giftcard_credit_value_cents', 0);
        if ($value <= 0) return '<p>' . esc_html__('Gift cards are not available.', 'classflow-pro') . '</p>';
        ob_start();
        ?>
        <div class="cfp-giftcard" style="max-width:420px">
            <h3><?php esc_html_e('Purchase Gift Card', 'classflow-pro'); ?></h3>
            <label><?php esc_html_e('Credits', 'classflow-pro'); ?> <input type="number" class="cfp-gc-credits" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" value="<?php echo esc_attr($min); ?>" /></label>
            <label><?php esc_html_e('Recipient Email (optional)', 'classflow-pro'); ?> <input type="email" class="cfp-gc-recipient" placeholder="friend@example.com" /></label>
            <?php if (!is_user_logged_in()): ?><label><?php esc_html_e('Your Email', 'classflow-pro'); ?> <input type="email" class="cfp-gc-email" placeholder="you@example.com" /></label><?php endif; ?>
            <div class="cfp-gc-total" style="margin:8px 0;color:#475569;"></div>
            <button class="button button-primary cfp-gc-buy"><?php esc_html_e('Buy Gift Card', 'classflow-pro'); ?></button>
            <div class="cfp-msg" style="margin-top:8px;color:#ef4444"></div>
        </div>
        <script>
        (function(){
          const root = document.currentScript.previousElementSibling;
          const credits = root.querySelector('.cfp-gc-credits');
          const recipient = root.querySelector('.cfp-gc-recipient');
          const buyer = root.querySelector('.cfp-gc-email');
          const total = root.querySelector('.cfp-gc-total');
          const msg = root.querySelector('.cfp-msg');
          const value = <?php echo (int)$value; ?>;
          function updateTotal(){ const n = parseInt(credits.value||0,10); total.textContent = 'Total: $' + ((n*value)/100).toFixed(2); }
          credits.addEventListener('input', updateTotal); updateTotal();
          root.querySelector('.cfp-gc-buy').addEventListener('click', async function(){
            msg.textContent=''; const n=parseInt(credits.value||0,10); if (!n||n<<?php echo (int)$min; ?>||n><?php echo (int)$max; ?>){ msg.textContent='<?php echo esc_js(__('Invalid credits amount.', 'classflow-pro')); ?>'; return; }
            const payload={ credits:n, recipient_email:(recipient.value||'')<?php if (!is_user_logged_in()): ?>, email:(buyer.value||'')<?php endif; ?> };
            try{
              const base=(window.CFP_DATA?CFP_DATA.restUrl:'<?php echo esc_js(rest_url('classflow/v1/')); ?>');
              const res = await fetch(base+'giftcards/checkout', { method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce': (window.CFP_DATA?CFP_DATA.nonce:'')}, body: JSON.stringify(payload) });
              const js = await res.json(); if(!res.ok){ msg.textContent = (js&&js.message)||'<?php echo esc_js(__('Failed to start checkout', 'classflow-pro')); ?>'; return; }
              if (js.url) window.location.href = js.url; else msg.textContent='<?php echo esc_js(__('Failed to get checkout link.', 'classflow-pro')); ?>';
            }catch(e){ msg.textContent='<?php echo esc_js(__('Failed to start checkout', 'classflow-pro')); ?>'; }
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function gift_card_redeem($atts): string
    {
        if (!is_user_logged_in()) return '<p>' . esc_html__('Please log in to redeem a gift card.', 'classflow-pro') . '</p>';
        wp_enqueue_style('cfp-frontend');
        ob_start();
        ?>
        <div class="cfp-giftcard-redeem" style="max-width:420px">
            <h3><?php esc_html_e('Redeem Gift Card', 'classflow-pro'); ?></h3>
            <label><?php esc_html_e('Code', 'classflow-pro'); ?> <input type="text" class="cfp-gc-code" placeholder="XXXX-XXXX" /></label>
            <button class="button button-primary cfp-gc-redeem"><?php esc_html_e('Redeem', 'classflow-pro'); ?></button>
            <div class="cfp-msg" style="margin-top:8px;"></div>
        </div>
        <script>
        (function(){
          const root = document.currentScript.previousElementSibling;
          const code = root.querySelector('.cfp-gc-code');
          const msg = root.querySelector('.cfp-msg');
          root.querySelector('.cfp-gc-redeem').addEventListener('click', async function(){
            msg.textContent=''; const c=(code.value||'').trim(); if (!c){ msg.textContent='<?php echo esc_js(__('Enter a code.', 'classflow-pro')); ?>'; return; }
            try{
              const base=(window.CFP_DATA?CFP_DATA.restUrl:'<?php echo esc_js(rest_url('classflow/v1/')); ?>');
              const res = await fetch(base+'giftcards/redeem', { method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce': (window.CFP_DATA?CFP_DATA.nonce:'')}, body: JSON.stringify({ code:c }) });
              const js = await res.json(); if(!res.ok){ msg.textContent = (js&&js.message)||'<?php echo esc_js(__('Failed to redeem', 'classflow-pro')); ?>'; return; }
              msg.style.color='#16a34a'; msg.textContent='<?php echo esc_js(__('Redeemed', 'classflow-pro')); ?> ' + (js.credits||0) + ' <?php echo esc_js(__('credits', 'classflow-pro')); ?>.';
            }catch(e){ msg.textContent='<?php echo esc_js(__('Failed to redeem', 'classflow-pro')); ?>'; }
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function book_class($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_book_class');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-book-class" data-class-id="' . esc_attr((int)$atts['class_id']) . '" data-location-id="' . esc_attr((int)$atts['location_id']) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-book-class__filters">';
        echo '<label>' . esc_html__('Date from', 'classflow-pro') . ' <input type="date" class="cfp-date-from"></label> ';
        echo '<label>' . esc_html__('Date to', 'classflow-pro') . ' <input type="date" class="cfp-date-to"></label> ';
        echo '<button class="button cfp-load-schedules">' . esc_html__('Load Schedules', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-schedules"></div>';
        echo '<div class="cfp-booking-form" style="display:none">';
        echo '<h4>' . esc_html__('Book Selected Class', 'classflow-pro') . '</h4>';
        echo '<label>' . esc_html__('Your name', 'classflow-pro') . ' <input type="text" class="cfp-name"></label>';
        echo '<label>' . esc_html__('Email', 'classflow-pro') . ' <input type="email" class="cfp-email" autocomplete="email"></label>';
        echo '<label>' . esc_html__('Phone', 'classflow-pro') . ' <input type="tel" class="cfp-phone" autocomplete="tel"></label>';
        echo '<div class="cfp-account-fields" style="display:block;margin:8px 0;">';
        echo '<label>' . esc_html__('Create password', 'classflow-pro') . ' <input type="password" class="cfp-password" autocomplete="new-password"></label> ';
        echo '<small style="display:block;color:#64748b;">' . esc_html__('If you don\'t have an account, we\'ll create one using this password.', 'classflow-pro') . '</small>';
        echo '<label style="display:block;margin-top:6px;"><input type="checkbox" class="cfp-sms-optin"> ' . esc_html__('Send me text messages about my bookings (optional)', 'classflow-pro') . '</label>';
        echo '</div>';
        echo '<label><input type="checkbox" class="cfp-use-credits"> ' . esc_html__('Use available credits', 'classflow-pro') . '</label>';
        echo '<button class="button button-primary cfp-book">' . esc_html__('Book Now', 'classflow-pro') . '</button>';
        echo '<div class="cfp-payment" style="display:none">';
        echo '<div class="cfp-prb" style="display:none;margin:8px 0;"><div class="cfp-prb-element"></div></div>';
        echo '<div class="cfp-card-element"></div>';
        echo '<button class="button button-primary cfp-pay">' . esc_html__('Pay', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function buy_package($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-buy-package" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h4>' . esc_html__('Purchase Class Package', 'classflow-pro') . '</h4>';
        echo '<label>' . esc_html__('Package Name', 'classflow-pro') . ' <input type="text" class="cfp-pkg-name" placeholder="10-Class Pack"></label>';
        echo '<label>' . esc_html__('Credits', 'classflow-pro') . ' <input type="number" class="cfp-pkg-credits" value="10" min="1"></label>';
        echo '<label>' . esc_html__('Price (cents)', 'classflow-pro') . ' <input type="number" class="cfp-pkg-price" value="15000" min="50"></label>';
        echo '<div class="cfp-card-element"></div>';
        echo '<button class="button button-primary cfp-pkg-pay">' . esc_html__('Pay & Add Credits', 'classflow-pro') . '</button>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function book_private($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
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
        return ob_get_clean();
    }

    public static function client_dashboard($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your bookings and packages.', 'classflow-pro') . '</p>';
        }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-client', CFP_PLUGIN_URL . 'assets/js/client.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-client-dashboard" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h3>' . esc_html__('My Dashboard', 'classflow-pro') . '</h3>';
        echo '<div class="cfp-kpis" style="display:flex;gap:12px;flex-wrap:wrap;">';
        echo '<div class="cfp-kpi"><strong>' . esc_html__('Credits', 'classflow-pro') . ':</strong> <span class="cfp-credits">—</span></div>';
        echo '</div>';
        echo '<h4>' . esc_html__('Upcoming Bookings', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-upcoming"></div>';
        echo '<h4>' . esc_html__('Past Bookings', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-past"></div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function user_portal($atts): string
    {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(esc_url(add_query_arg([], home_url($_SERVER['REQUEST_URI'] ?? '/'))));
            return '<p>Please <a href="' . esc_url($login_url) . '">log in</a> to view your portal.</p>';
        }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-portal', CFP_PLUGIN_URL . 'assets/js/portal.js', ['jquery'], '1.0.0', true);
        // Localize data expected by portal.js
        wp_localize_script('cfp-portal', 'CFP_DATA', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'businessTimezone' => \ClassFlowPro\Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
        ]);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        $show_thanks = isset($_GET['cfp_checkout']) && sanitize_text_field((string)$_GET['cfp_checkout']) === 'success';
        echo '<div class="cfp-portal" data-nonce="' . esc_attr($nonce) . '">';
        if ($show_thanks) {
            echo '<div class="cfp-portal-banner cfp-portal-success" role="status">' . esc_html__('Thank you! Your checkout completed successfully.', 'classflow-pro') . '</div>';
        }
        echo '<div class="cfp-portal-profile"><h3>Your Profile</h3><div class="cfp-profile-fields">';
        $u = wp_get_current_user();
        echo '<p><strong>Name:</strong> ' . esc_html($u->display_name ?: ($u->user_firstname . ' ' . $u->user_lastname)) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html($u->user_email) . '</p>';
        // Client-editable basics
        echo '<div class="cfp-profile-edit">'
            . '<label>Phone <input type="tel" class="cfp-prof-phone"/></label>'
            . '<label>Date of Birth <input type="date" class="cfp-prof-dob"/></label>'
            . '<label>Emergency Contact Name <input type="text" class="cfp-prof-emg-name"/></label>'
            . '<label>Emergency Contact Phone <input type="tel" class="cfp-prof-emg-phone"/></label>'
            . '<button class="button button-primary cfp-prof-save">' . esc_html__('Save Profile', 'classflow-pro') . '</button>'
            . '<div class="cfp-msg" aria-live="polite"></div>'
            . '</div>';
        echo '</div></div>';
        echo '<div class="cfp-portal-upcoming"><h3>Upcoming Classes</h3><div class="cfp-list cfp-upcoming-list">Loading…</div></div>';
        echo '<div class="cfp-portal-past"><h3>Past Classes</h3><div class="cfp-list cfp-past-list">Loading…</div></div>';
        echo '<div class="cfp-portal-credits"><h3>Credits</h3><div class="cfp-credits">Loading…</div>';
        echo '<div class="cfp-gc-redeem" style="margin-top:12px; padding:10px; border:1px solid #e2e4e7; border-radius:6px; max-width:420px;">';
        echo '<label style="display:block; margin-bottom:6px;"><strong>' . esc_html__('Redeem gift card', 'classflow-pro') . '</strong></label>';
        echo '<div style="display:flex; gap:8px; align-items:center;">'
            . '<input type="text" class="cfp-gc-code" placeholder="' . esc_attr__('Enter code', 'classflow-pro') . '" style="flex:1;" />'
            . '<button class="button cfp-gc-redeem-btn">' . esc_html__('Redeem', 'classflow-pro') . '</button>'
            . '</div>';
        echo '<div class="cfp-gc-msg" role="status" style="margin-top:8px;"></div>';
        echo '</div></div>';
        echo '<div class="cfp-portal-notes"><h3>' . esc_html__('Notes', 'classflow-pro') . '</h3><div class="cfp-notes-list">Loading…</div></div>';
        echo '<div class="cfp-portal-series"><h3>' . esc_html__('My Series', 'classflow-pro') . '</h3><div class="cfp-series-list">Loading…</div></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function checkout_success($atts = []): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-checkout-success', CFP_PLUGIN_URL . 'assets/js/checkout-success.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-checkout-success" data-nonce="' . esc_attr($nonce) . '"><div class="cfp-msg" aria-live="polite">Processing your checkout result…</div></div>';
        return ob_get_clean();
    }

    public static function waitlist_response($atts = []): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-waitlist-response', CFP_PLUGIN_URL . 'assets/js/waitlist-response.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-waitlist-response" data-nonce="' . esc_attr($nonce) . '"><div class="cfp-msg" aria-live="polite">Processing your response…</div></div>';
        return ob_get_clean();
    }

    public static function series($atts = []): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-series', CFP_PLUGIN_URL . 'assets/js/series.js', ['jquery'], '1.0.0', true);
        wp_localize_script('cfp-series', 'CFP_DATA', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
        ]);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-series" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h3>' . esc_html__('Programs & Series', 'classflow-pro') . '</h3>';
        echo '<div class="cfp-series-list">' . esc_html__('Loading…', 'classflow-pro') . '</div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        return ob_get_clean();
    }
}
