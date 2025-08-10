# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ClassFlow Pro is a WordPress plugin for managing Pilates/fitness studios with booking, scheduling, and payment features. The plugin uses PHP 8.0+ for backend functionality, vanilla JavaScript for frontend interactions, and integrates with Stripe for payments.

## Development Commands

### Docker Environment
```bash
# Start development environment (WordPress + MySQL + PHPMyAdmin + MailHog)
docker-compose up -d

# Access WordPress at http://localhost:8080
# PHPMyAdmin at http://localhost:8081
# MailHog (email testing) at http://localhost:8025

# WordPress CLI commands
docker-compose run --rm wpcli --info

# Stop environment
docker-compose down
```

### Build Commands
```bash
# Create distribution ZIP file
./build.sh

# The example/ directory shows intended build commands (not yet implemented in main):
# npm run dev          - Development mode with watch
# npm run build        - Production build
# npm run sass         - Watch SCSS files
# npm run sass:build   - Build compressed CSS
# npm run lint         - ESLint for JavaScript
```

## Architecture

### Core Plugin Structure
The plugin follows WordPress coding standards with a namespace of `ClassFlowPro\`. Main components are organized in `/includes/`:

- **Admin/** - WordPress admin interface, settings pages, and dashboard
- **Booking/** - Core booking logic, availability checking, and validation
- **PostTypes/** - Custom post types for classes, instructors, and locations
- **Payments/** - Stripe integration with webhook handling
- **REST/** - Custom REST API endpoints for frontend interactions
- **DB/** - Database schema and migrations for custom tables
- **Elementor/** - Widget integration for the Elementor page builder
- **Calendar/** - Google Calendar and iCal export functionality
- **Notifications/** - Email notification system
- **Packages/** - Class packages and membership management

### Frontend Architecture
- JavaScript files in `/assets/js/` use vanilla ES6+ JavaScript
- No framework dependencies - direct DOM manipulation
- Event-driven architecture for booking interactions
- AJAX calls to custom REST endpoints

### Database Design
The plugin creates custom WordPress tables on activation:
- Booking records with status tracking
- Package purchases and usage
- Payment transaction logs
- Attendance tracking

### Integration Points
1. **Stripe Webhooks** - Handles payment confirmations at `/wp-json/classflow-pro/v1/stripe/webhook`
2. **Elementor Widgets** - Custom widgets registered when Elementor is active
3. **QuickBooks** - Optional accounting integration in `/includes/Accounting/`
4. **Google Calendar** - Two-way sync capabilities in `/includes/Calendar/`

### Key Development Patterns
- Use WordPress hooks (`add_action`, `add_filter`) for extending functionality
- Follow WordPress naming conventions for database operations
- Sanitize all user inputs using WordPress sanitization functions
- Use WordPress nonce verification for AJAX requests
- Leverage WordPress transients API for caching

### Testing Approach
While the main codebase lacks formal tests, the example/ directory shows the intended testing structure:
- PHPUnit for PHP testing
- Jest for JavaScript testing
- PHPStan for static analysis
- PHP CodeSniffer for coding standards