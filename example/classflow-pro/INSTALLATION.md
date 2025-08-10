# ClassFlow Pro Installation Guide

## Prerequisites

- PHP 7.4 or higher
- WordPress 5.8 or higher
- Composer (for dependency management)

## Installation Steps

### 1. Install Dependencies

The plugin requires several PHP packages to function properly. You must install these dependencies using Composer before activating the plugin.

```bash
cd /path/to/wordpress/wp-content/plugins/classflow-pro
composer install --no-dev
```

This will install:
- `stripe/stripe-php` - For payment processing
- `nesbot/carbon` - For date/time handling
- `ramsey/uuid` - For generating unique identifiers

### 2. Activate the Plugin

After installing dependencies, activate the plugin through the WordPress admin panel:

1. Go to **Plugins** → **Installed Plugins**
2. Find "ClassFlow Pro"
3. Click **Activate**

### 3. Configure Stripe

The plugin requires Stripe API keys to process payments:

1. Go to **ClassFlow Pro** → **Settings**
2. Navigate to the **Payment** tab
3. Enter your Stripe API keys:
   - Test Mode:
     - Test Publishable Key
     - Test Secret Key
   - Live Mode:
     - Live Publishable Key
     - Live Secret Key
4. Configure webhook endpoint in Stripe Dashboard:
   - Endpoint URL: `https://yoursite.com/wp-json/classflow-pro/v1/webhooks/stripe`
   - Events to send:
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`
     - `charge.refunded`
     - For Stripe Connect (if enabled):
       - `account.updated`
       - `account.application.deauthorized`
       - `transfer.created`
       - `transfer.updated`

### 4. Initial Setup

After activation, configure the basic settings:

1. **Business Information**
   - Business name
   - Timezone
   - Currency

2. **Booking Settings**
   - Advance booking days
   - Minimum booking hours
   - Cancellation policy

3. **Email Settings**
   - From name and email
   - Enable/disable specific notifications

4. **User Roles**
   - Assign instructors the "ClassFlow Instructor" role
   - Students will automatically get "ClassFlow Student" role on first booking

## Stripe Connect Setup (Optional)

If you want to pay instructors directly through the platform:

1. Enable Stripe Connect in settings
2. Set platform fee percentage
3. Instructors can connect their Stripe accounts through their profile

## Troubleshooting

### "Fatal error: require(): Failed opening required vendor/autoload.php"

This means Composer dependencies are not installed. Run:
```bash
composer install --no-dev
```

### "ClassFlow Pro requires PHP 7.4 or higher"

Update your PHP version. The plugin uses modern PHP features that require at least PHP 7.4.

### Database tables not created

Deactivate and reactivate the plugin. This will trigger the database installation routine.

## Development Setup

For development environments:

```bash
composer install  # Includes dev dependencies
```

This will also install:
- PHPUnit for testing
- PHP CodeSniffer for code standards
- PHPStan for static analysis

## Support

For issues or questions:
- Check the documentation in `/docs`
- Submit issues to your support system
- Review error logs in `wp-content/debug.log`