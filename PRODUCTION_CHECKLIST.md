# ClassFlow Pro Production Checklist

## ✅ Pre-Launch Requirements

### Configuration
- [ ] **Stripe Configuration**
  - [ ] Set production Stripe keys (pk_live_*, sk_live_*)
  - [ ] Configure webhook endpoint: `/wp-json/classflow/v1/stripe/webhook`
  - [ ] Set webhook secret (whsec_*)
  - [ ] Enable Stripe Tax if required
  - [ ] Test payment flow with live keys

- [ ] **QuickBooks Configuration** (Optional)
  - [ ] Set OAuth credentials
  - [ ] Configure redirect URI
  - [ ] Map income accounts and tax codes
  - [ ] Test connection and sync

- [ ] **Google Workspace** (Optional)
  - [ ] Configure OAuth client ID/secret
  - [ ] Set redirect URI: `/wp-json/classflow/v1/google/callback`
  - [ ] Enable required services (Calendar, Meet, Drive)
  - [ ] Authorize and test sync

- [ ] **Zoom Integration** (Optional)
  - [ ] Create Server-to-Server OAuth app
  - [ ] Configure account ID, client ID, secret
  - [ ] Test meeting creation

- [ ] **SMS Notifications** (Optional)
  - [ ] Configure Twilio credentials
  - [ ] Set from number (E.164 format)
  - [ ] Test SMS delivery

### WordPress Setup
- [ ] **Minimum Requirements**
  - [ ] WordPress 6.0+
  - [ ] PHP 8.0+
  - [ ] MySQL 5.7+ or MariaDB 10.3+
  - [ ] SSL certificate installed
  - [ ] PHP extensions: json, mysqli, openssl, fileinfo

- [ ] **Permissions**
  - [ ] File permissions: 755 for directories, 644 for files
  - [ ] Writable uploads directory
  - [ ] Database user has CREATE, ALTER, DROP privileges

### Initial Data Setup
- [ ] **Create Entities**
  - [ ] Add at least one Location with timezone
  - [ ] Create Classes with pricing
  - [ ] Add Instructors with payout settings
  - [ ] Configure Resources if needed

- [ ] **Business Settings**
  - [ ] Set business timezone
  - [ ] Configure cancellation/reschedule windows
  - [ ] Set email notification preferences
  - [ ] Configure intake form requirements

### Security Checklist
- [ ] Change default admin credentials
- [ ] Install security plugin (Wordfence, Sucuri)
- [ ] Configure regular backups
- [ ] Enable WordPress auto-updates
- [ ] Review user roles and capabilities
- [ ] Test with WPScan for vulnerabilities

## 🚀 Launch Steps

1. **Backup Current Site**
   ```bash
   wp db export backup-pre-classflow.sql
   ```

2. **Install Plugin**
   ```bash
   wp plugin install classflow-pro.zip --activate
   ```

3. **Run Database Migrations**
   - Activation automatically creates tables
   - Check ClassFlow Pro > System for status

4. **Configure Settings**
   - Navigate to ClassFlow Pro > Settings
   - Configure all required fields
   - Save and test connections

5. **Import Data** (if migrating)
   - Use ClassFlow Pro > Import
   - Upload CSV files for customers, credits, schedules

6. **Test Critical Paths**
   - [ ] Book a class as customer
   - [ ] Process payment
   - [ ] Cancel/reschedule booking
   - [ ] Purchase package
   - [ ] Check email notifications
   - [ ] Verify instructor payouts
   - [ ] Test reports generation

## 📊 Post-Launch Monitoring

### First 24 Hours
- [ ] Monitor ClassFlow Pro > Logs for errors
- [ ] Check Stripe webhook delivery
- [ ] Verify email delivery rates
- [ ] Monitor server performance
- [ ] Check for JavaScript errors

### First Week
- [ ] Review booking patterns
- [ ] Check payment success rates
- [ ] Audit failed transactions
- [ ] Review customer feedback
- [ ] Optimize slow queries

### Ongoing Maintenance
- [ ] Weekly backups
- [ ] Monthly security scans
- [ ] Quarterly plugin updates
- [ ] Annual SSL renewal
- [ ] Regular log cleanup

## 🔧 Troubleshooting

### Common Issues

**Webhook Failures**
- Check webhook secret is correct
- Verify SSL certificate is valid
- Check server firewall allows Stripe IPs

**Email Not Sending**
- Install SMTP plugin (WP Mail SMTP)
- Configure transactional email service
- Check spam folders

**Performance Issues**
- Enable object caching (Redis/Memcached)
- Optimize database indexes
- Use CDN for assets
- Increase PHP memory limit

**Payment Failures**
- Verify Stripe keys are correct
- Check currency settings match Stripe
- Review Stripe radar rules
- Check for card testing attacks

## 📝 Support Information

### Error Reporting
When reporting issues, include:
- WordPress version
- PHP version
- Error messages from Logs
- Steps to reproduce
- Browser console errors

### Performance Metrics
Track these KPIs:
- Booking conversion rate
- Payment success rate
- Average page load time
- Email delivery rate
- Customer satisfaction score

## ✅ Final Verification

Before going live, confirm:
- [ ] All test bookings cleared
- [ ] Production payment keys active
- [ ] Backup system operational
- [ ] Support documentation ready
- [ ] Staff training completed
- [ ] Emergency rollback plan prepared

## 🎉 Launch Confirmation

**Date/Time**: _______________
**Launched By**: _______________
**Version**: 1.1.0
**Status**: PRODUCTION READY

---

*This checklist ensures ClassFlow Pro is properly configured and ready for production use in your Pilates studio.*