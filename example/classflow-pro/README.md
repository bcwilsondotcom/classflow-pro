# ClassFlow Pro

Modern WordPress class and course booking system with advanced scheduling, payments, and management features.

## Features

### Phase 1, 2 & 3 (Implemented)

#### Core Functionality
- **Class Management**: Create and manage classes with rich descriptions, categories, images, and prerequisites
- **Scheduling System**: Single and recurring schedules with instructor assignment and location management
- **Booking Engine**: Real-time availability, instant confirmations, waitlist management
- **User Management**: Student profiles, instructor management, role-based permissions

#### Advanced Features
- **Payment Processing**: Stripe integration with payment intents API (Apple Pay, Google Pay, etc.)
- **Package System**: Multi-class packages, memberships, validity periods
- **Email Notifications**: Automated confirmations, reminders, cancellations
- **Admin Dashboard**: Comprehensive overview with stats and quick actions
- **REST API**: Full API for headless implementations
- **Calendar Views**: Interactive calendar with month/week/day views and filters

## Installation

1. Upload the `classflow-pro` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Run `composer install` to install PHP dependencies
4. Run `npm install` to install JavaScript dependencies
5. Run `npm run build` to build assets

## Configuration

### Payment Setup
1. Go to ClassFlow Pro > Settings > Payment
2. Enter your Stripe API keys
3. Configure payment settings

### Email Setup
1. Go to ClassFlow Pro > Settings > Email
2. Configure sender information
3. Enable/disable notification types

## Development

### Requirements
- PHP 7.4+
- WordPress 5.8+
- Composer
- Node.js & npm

### Build Commands
```bash
# Install dependencies
composer install
npm install

# Development mode
npm run dev

# Production build
npm run build

# Run PHP linting
composer run phpcs

# Run tests
composer run test
```

### Project Structure
```
classflow-pro/
├── src/                 # PHP source code
│   ├── Admin/          # Admin functionality
│   ├── API/            # REST API endpoints
│   ├── Core/           # Core plugin files
│   ├── Frontend/       # Frontend functionality
│   ├── Models/         # Data models and repositories
│   └── Services/       # Business logic services
├── assets/             # CSS, JS, images
├── templates/          # PHP templates
├── languages/          # Translation files
└── vendor/             # Composer dependencies
```

## API Documentation

### Classes Endpoint
```
GET    /wp-json/classflow-pro/v1/classes
POST   /wp-json/classflow-pro/v1/classes
GET    /wp-json/classflow-pro/v1/classes/{id}
PUT    /wp-json/classflow-pro/v1/classes/{id}
DELETE /wp-json/classflow-pro/v1/classes/{id}
```

### Bookings Endpoint
```
GET    /wp-json/classflow-pro/v1/bookings
POST   /wp-json/classflow-pro/v1/bookings
GET    /wp-json/classflow-pro/v1/bookings/{id}
DELETE /wp-json/classflow-pro/v1/bookings/{id}
```

## Shortcodes

### Display Classes Grid
```
[classflow_classes category="yoga" limit="12"]
```

### Display Schedule
```
[classflow_schedule view="calendar" location="1"]
```

### Display Calendar
```
[classflow_calendar view="month" show_filters="yes" height="600"]
```

### Booking Form
```
[classflow_booking_form schedule_id="123"]
```

### My Bookings
```
[classflow_my_bookings]
```

## Hooks & Filters

### Actions
- `classflow_pro_class_created` - Fired after a class is created
- `classflow_pro_booking_created` - Fired after a booking is created
- `classflow_pro_payment_confirmed` - Fired after payment confirmation

### Filters
- `classflow_pro_class_capacity` - Filter class capacity
- `classflow_pro_booking_allowed` - Filter if booking is allowed
- `classflow_pro_email_content` - Filter email content

## Support

For support, please visit our documentation or contact support.

## License

GPL v2 or later