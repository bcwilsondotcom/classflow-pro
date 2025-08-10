# ClassFlow Pro - Claude Development Guide

## Project Overview
ClassFlow Pro is a WordPress plugin for managing fitness/pilates classes with booking, scheduling, and payment features. It follows modern PHP practices with PSR-4 autoloading and a service-oriented architecture.

## Key Technologies
- **PHP**: 7.4+ with type declarations and modern OOP
- **WordPress**: 5.8+ plugin architecture
- **Frontend**: Vanilla JS with Webpack bundling
- **Payments**: Stripe integration (stripe-php)
- **Styling**: SCSS compiled to CSS
- **Database**: WordPress tables with custom repositories

## Development Commands

### Setup
```bash
composer install       # Install PHP dependencies
npm install           # Install JS dependencies
```

### Build & Development
```bash
npm run dev          # Development mode with watch
npm run build        # Production build
npm run sass         # Watch SCSS files
npm run sass:build   # Build compressed CSS
```

### Code Quality
```bash
npm run lint         # ESLint for JavaScript
composer run phpcs   # PHP CodeSniffer
composer run phpstan # PHPStan static analysis
composer run test    # PHPUnit tests
```

## Architecture

### Directory Structure
- `src/` - PHP source code (PSR-4 autoloaded as ClassFlowPro\)
  - `Admin/` - WordPress admin functionality
  - `API/` - REST API endpoints
  - `Core/` - Core plugin files (Plugin.php is main entry)
  - `Frontend/` - Public-facing features & shortcodes
  - `Models/` - Entities and repositories
  - `Services/` - Business logic layer
- `assets/` - Frontend resources
  - `js/` - Source JavaScript
  - `scss/` - Source SCSS
  - `dist/` - Webpack build output
- `templates/` - PHP view templates
- `vendor/` - Composer dependencies

### Key Classes
- `Core\Plugin` - Singleton that initializes everything
- `Services\Container` - Dependency injection container
- `Admin\AdminManager` - Admin area setup
- `Frontend\FrontendManager` - Frontend setup
- `API\RestApiManager` - REST API registration

### Database Tables
Custom tables created on activation:
- `{prefix}_classflow_classes`
- `{prefix}_classflow_schedules`
- `{prefix}_classflow_bookings`
- `{prefix}_classflow_payments`
- `{prefix}_classflow_locations`
- `{prefix}_classflow_instructors`
- `{prefix}_classflow_students`
- `{prefix}_classflow_packages`

## Shortcodes
- `[classflow_classes]` - Display classes grid
- `[classflow_schedule]` - Display schedule
- `[classflow_calendar]` - Interactive calendar
- `[classflow_booking_form]` - Booking form
- `[classflow_my_bookings]` - User's bookings

## REST API Endpoints
Base: `/wp-json/classflow-pro/v1/`
- `/classes` - CRUD operations
- `/bookings` - Booking management
- `/schedules` - Schedule data
- `/payments` - Payment processing

## Common Tasks

### Adding a New Feature
1. Create service class in `src/Services/`
2. Register in `Plugin::registerServices()`
3. Add admin page in `src/Admin/Pages/`
4. Create frontend shortcode in `src/Frontend/Shortcodes/`

### Modifying Database
1. Update schema in `Core/Database.php`
2. Create/update repository in `Models/Repositories/`
3. Update entity in `Models/Entities/`

### Adding JavaScript
1. Add entry point in `webpack.config.js`
2. Enqueue in appropriate manager class
3. Build with `npm run build`

## Important Notes
- Always use the service container for dependencies
- Follow WordPress coding standards for hooks/filters
- Use prepared statements for database queries
- Sanitize all user inputs
- Escape all outputs
- Use WordPress functions for URLs and paths
- Test with different user roles and permissions

## Debugging
- Check `wp-content/debug.log` for PHP errors
- Use browser console for JS errors
- REST API responses include error details
- Enable `WP_DEBUG` for development