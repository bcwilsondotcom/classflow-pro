# ClassFlow Pro - Admin Error Fix

## Issue
The admin pages were showing critical errors because:
1. Some repositories weren't registered in the container
2. BookingRepository was missing methods that BookingsPage expected

## Fixed Files

### 1. /src/Core/Plugin.php
Added missing repository registrations:
- category_repository
- instructor_repository  
- student_repository

### 2. /src/Models/Repositories/BookingRepository.php
Added missing methods:
- `findByBookingCode()` - alias for findByCode()
- `paginate()` - pagination support
- `count()` - count bookings with filters

## To Apply the Fix

1. Replace the two files mentioned above with the updated versions
2. Clear any WordPress caches
3. Try accessing the admin pages again

## Testing

After applying the fix:
1. Go to **ClassFlow Pro → Classes** - Should show empty list with "Add New" button
2. Go to **ClassFlow Pro → Bookings** - Should show empty list with filters
3. Try creating a new class to verify full functionality

## If Issues Persist

Check the WordPress debug log for specific errors:
```
wp-content/debug.log
```

Common issues:
- PHP version incompatibility (needs 7.4+)
- Database tables not created (deactivate/reactivate plugin)
- File permissions preventing writes