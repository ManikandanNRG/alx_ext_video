# Cloudflare Stream Integration - Deployment Checklist

## Prerequisites

### System Requirements

#### Moodle Environment
- [ ] **Moodle Version**: 3.9 LTS or higher (4.0+ recommended)
- [ ] **PHP Version**: 7.4 or higher (8.0+ recommended)
- [ ] **Database**: MySQL 5.7+ or PostgreSQL 10+
- [ ] **Web Server**: Apache 2.4+ or Nginx 1.18+
- [ ] **HTTPS**: SSL certificate installed and configured (required for secure token transmission)
- [ ] **Memory Limit**: PHP memory_limit ≥ 256MB
- [ ] **Upload Limits**: 
  - `upload_max_filesize` ≥ 100MB (for initial file validation)
  - `post_max_size` ≥ 100MB
  - `max_execution_time` ≥ 300 seconds

#### Server Configuration
- [ ] **Outbound HTTPS**: Server can make HTTPS requests to api.cloudflare.com
- [ ] **Firewall**: Port 443 outbound allowed for Cloudflare API
- [ ] **DNS**: Server can resolve cloudflare.com domains
- [ ] **Disk Space**: Sufficient space for Moodle database growth (video metadata only)

#### Browser Compatibility
- [ ] **Modern Browsers**: Chrome 80+, Firefox 75+, Safari 13+, Edge 80+
- [ ] **JavaScript**: Enabled (required for upload and playback functionality)
- [ ] **TLS 1.2+**: Modern TLS support for secure connections

### PHP Extensions
- [ ] **curl**: For API requests to Cloudflare
- [ ] **json**: For API response parsing
- [ ] **openssl**: For encryption of API tokens
- [ ] **mbstring**: For string handling
- [ ] **gd** or **imagick**: For thumbnail processing (if implemented)

## Cloudflare Account Setup

### Account Configuration
- [ ] **Cloudflare Account**: Active Cloudflare account with billing enabled
- [ ] **Stream Service**: Cloudflare Stream enabled on account
- [ ] **API Token**: Custom API token created with Stream permissions
- [ ] **Account ID**: Cloudflare account ID identified and documented

### API Token Creation Steps
1. [ ] Log into Cloudflare Dashboard
2. [ ] Navigate to "My Profile" → "API Tokens"
3. [ ] Click "Create Token"
4. [ ] Use "Custom token" template
5. [ ] Configure permissions:
   - [ ] **Account**: Cloudflare Stream:Edit
   - [ ] **Zone Resources**: Include All zones (or specific zones if preferred)
6. [ ] Add IP restrictions (optional but recommended):
   - [ ] Include your Moodle server's public IP address
7. [ ] Set token expiration (recommended: 1 year)
8. [ ] [ ] Copy and securely store the generated token
9. [ ] **Test token** using curl:
   ```bash
   curl -X GET "https://api.cloudflare.com/client/v4/accounts/{account_id}/stream" \
        -H "Authorization: Bearer {api_token}"
   ```

### Stream Service Configuration
- [ ] **Storage Limits**: Review and set appropriate storage quotas
- [ ] **Bandwidth Limits**: Configure bandwidth alerts if needed
- [ ] **Retention Policy**: Set default video retention (recommend 90 days)
- [ ] **Webhook Configuration**: Optional - set up webhooks for video processing events

## Staging Deployment Procedure

### Pre-Deployment Preparation
- [ ] **Backup**: Full Moodle database and file system backup
- [ ] **Maintenance Mode**: Enable Moodle maintenance mode
- [ ] **Plugin Download**: Download latest plugin release or prepare from source
- [ ] **Dependencies**: Verify all PHP dependencies are available

### Plugin Installation
1. [ ] **Extract Plugin**:
   ```bash
   cd /path/to/moodle
   unzip cloudflarestream-plugin.zip
   mv cloudflarestream mod/assign/submission/
   ```

2. [ ] **Set Permissions**:
   ```bash
   chown -R www-data:www-data mod/assign/submission/cloudflarestream
   chmod -R 755 mod/assign/submission/cloudflarestream
   ```

3. [ ] **Database Installation**:
   - [ ] Access Moodle admin interface
   - [ ] Navigate to Site Administration → Notifications
   - [ ] Follow database upgrade prompts
   - [ ] Verify new table `mdl_assignsubmission_cfstream` created

### Configuration
4. [ ] **Plugin Settings**:
   - [ ] Navigate to Site Administration → Plugins → Assignment → Submission plugins
   - [ ] Click "Settings" for Cloudflare Stream
   - [ ] Configure:
     - [ ] **API Token**: Enter Cloudflare API token (will be encrypted)
     - [ ] **Account ID**: Enter Cloudflare account ID
     - [ ] **Max File Size**: Set to 5GB (5368709120 bytes)
     - [ ] **Retention Days**: Set to 90 days
     - [ ] **Enable Plugin**: Check to enable

5. [ ] **Test Configuration**:
   - [ ] Click "Test Connection" button in settings
   - [ ] Verify successful connection to Cloudflare API
   - [ ] Check error logs for any issues

### Functional Testing
6. [ ] **Create Test Assignment**:
   - [ ] Create new course (if needed)
   - [ ] Add new assignment
   - [ ] Enable "Cloudflare Stream video submission" in submission types
   - [ ] Set due date in future

7. [ ] **Test Upload Flow**:
   - [ ] Login as test student
   - [ ] Navigate to test assignment
   - [ ] Upload small test video (< 100MB)
   - [ ] Verify progress bar displays
   - [ ] Confirm upload completes successfully
   - [ ] Check database record created in `mdl_assignsubmission_cfstream`

8. [ ] **Test Playback Flow**:
   - [ ] Login as teacher
   - [ ] Navigate to assignment grading
   - [ ] Open student submission
   - [ ] Verify video player loads
   - [ ] Confirm video plays without errors
   - [ ] Test grading interface functionality

9. [ ] **Test Access Control**:
   - [ ] Login as different student
   - [ ] Attempt to access other student's video
   - [ ] Verify access denied
   - [ ] Test teacher access to multiple submissions

### Performance Testing
10. [ ] **Large File Test**:
    - [ ] Upload 1GB+ test video
    - [ ] Monitor upload progress and completion
    - [ ] Verify server performance during upload

11. [ ] **Concurrent Upload Test**:
    - [ ] Simulate 5-10 concurrent uploads
    - [ ] Monitor system resources
    - [ ] Verify all uploads complete successfully

### Security Verification
12. [ ] **Token Security**:
    - [ ] Verify API token encrypted in database
    - [ ] Check that token not exposed in browser network requests
    - [ ] Confirm signed playback tokens expire correctly

13. [ ] **Access Control**:
    - [ ] Test unauthorized access attempts
    - [ ] Verify proper error messages displayed
    - [ ] Check audit logs for access attempts

### Cleanup and Documentation
14. [ ] **Test Data Cleanup**:
    - [ ] Delete test videos from Cloudflare
    - [ ] Remove test assignments and submissions
    - [ ] Clear test user accounts if created

15. [ ] **Documentation**:
    - [ ] Document any configuration changes made
    - [ ] Note any issues encountered and resolutions
    - [ ] Update deployment notes for production

16. [ ] **Disable Maintenance Mode**:
    - [ ] Turn off Moodle maintenance mode
    - [ ] Verify site accessible to users

## Production Rollout Plan

### Pre-Production Checklist
- [ ] **Staging Success**: All staging tests passed successfully
- [ ] **Backup Strategy**: Production backup and recovery plan confirmed
- [ ] **Rollback Plan**: Rollback procedure tested and documented
- [ ] **Monitoring**: Monitoring and alerting systems prepared
- [ ] **Support Team**: Technical support team briefed and available
- [ ] **User Communication**: Users notified of new feature and any downtime

### Production Deployment Steps

#### Phase 1: Off-Peak Deployment (Recommended: Weekend/Evening)
1. [ ] **Maintenance Window**: Schedule 2-hour maintenance window
2. [ ] **User Notification**: Send advance notice to all users (48-72 hours)
3. [ ] **Final Backup**: Complete database and file system backup
4. [ ] **Enable Maintenance Mode**: Put Moodle in maintenance mode

#### Phase 2: Plugin Installation
5. [ ] **Deploy Plugin Files**:
   ```bash
   # Backup existing plugins (if upgrading)
   cp -r mod/assign/submission mod/assign/submission.backup.$(date +%Y%m%d)
   
   # Deploy new plugin
   rsync -av cloudflarestream/ mod/assign/submission/cloudflarestream/
   chown -R www-data:www-data mod/assign/submission/cloudflarestream
   ```

6. [ ] **Database Upgrade**:
   - [ ] Run Moodle upgrade process
   - [ ] Monitor for any database errors
   - [ ] Verify new tables and indexes created

7. [ ] **Configuration**:
   - [ ] Apply production configuration settings
   - [ ] Test API connectivity
   - [ ] Verify encryption of sensitive settings

#### Phase 3: Verification
8. [ ] **Smoke Tests**:
   - [ ] Test plugin loads without errors
   - [ ] Verify assignment creation with video submission enabled
   - [ ] Test basic upload functionality with small file
   - [ ] Confirm playback works correctly

9. [ ] **Performance Check**:
   - [ ] Monitor server resources during tests
   - [ ] Check response times for key pages
   - [ ] Verify no impact on existing functionality

#### Phase 4: Go-Live
10. [ ] **Disable Maintenance Mode**: Make site available to users
11. [ ] **Monitor Closely**: Watch for errors in first 2 hours
12. [ ] **User Support**: Have support team ready for user questions

### Post-Deployment Monitoring (First 48 Hours)

#### Immediate Monitoring (First 2 Hours)
- [ ] **Error Logs**: Monitor Moodle and web server error logs
- [ ] **Performance**: Check page load times and server resources
- [ ] **User Reports**: Monitor support channels for user issues
- [ ] **API Usage**: Monitor Cloudflare API usage and errors

#### Extended Monitoring (48 Hours)
- [ ] **Upload Success Rate**: Track upload completion rates
- [ ] **Playback Issues**: Monitor video playback errors
- [ ] **Database Performance**: Check query performance on new tables
- [ ] **Storage Usage**: Monitor Cloudflare storage consumption

#### Success Metrics
- [ ] **Upload Success Rate**: > 95% successful uploads
- [ ] **Playback Success Rate**: > 98% successful playback attempts
- [ ] **Page Load Impact**: < 10% increase in assignment page load times
- [ ] **Error Rate**: < 1% of requests result in errors
- [ ] **User Satisfaction**: No critical user complaints

## Rollback Strategy

### Rollback Triggers
Execute rollback if any of the following occur:
- [ ] **Critical Errors**: Plugin causes site-wide errors or crashes
- [ ] **Data Loss**: Any indication of data corruption or loss
- [ ] **Performance Impact**: > 50% degradation in site performance
- [ ] **Security Issues**: Discovery of security vulnerabilities
- [ ] **High Error Rate**: > 10% of plugin operations failing

### Rollback Procedure

#### Immediate Actions (Within 15 Minutes)
1. [ ] **Enable Maintenance Mode**: Prevent further user impact
2. [ ] **Disable Plugin**:
   ```bash
   # Quick disable via database
   mysql -u root -p moodle -e "UPDATE mdl_config_plugins SET value='0' WHERE plugin='assignsubmission_cloudflarestream' AND name='enabled';"
   ```

3. [ ] **Clear Caches**:
   ```bash
   php admin/cli/purge_caches.php
   ```

#### Full Rollback (Within 1 Hour)
4. [ ] **Restore Plugin Files**:
   ```bash
   rm -rf mod/assign/submission/cloudflarestream
   mv mod/assign/submission.backup.$(date +%Y%m%d)/cloudflarestream mod/assign/submission/ 2>/dev/null || true
   ```

5. [ ] **Database Rollback**:
   - [ ] Restore from pre-deployment backup if database changes cause issues
   - [ ] Or manually remove plugin tables:
     ```sql
     DROP TABLE IF EXISTS mdl_assignsubmission_cfstream;
     DELETE FROM mdl_config_plugins WHERE plugin = 'assignsubmission_cloudflarestream';
     ```

6. [ ] **Verify Rollback**:
   - [ ] Test assignment functionality
   - [ ] Verify no plugin references remain
   - [ ] Check error logs clear

7. [ ] **Disable Maintenance Mode**: Restore user access

#### Post-Rollback Actions
8. [ ] **User Communication**: Notify users of temporary service interruption
9. [ ] **Issue Analysis**: Investigate root cause of rollback
10. [ ] **Cloudflare Cleanup**: Manually delete any test videos from Cloudflare
11. [ ] **Documentation**: Document rollback reason and lessons learned

### Rollback Testing
- [ ] **Test Rollback Procedure**: Practice rollback on staging environment
- [ ] **Backup Verification**: Verify backups can be restored successfully
- [ ] **Recovery Time**: Measure and document rollback time requirements
- [ ] **Team Training**: Ensure operations team familiar with rollback steps

## Post-Deployment Tasks

### Week 1: Intensive Monitoring
- [ ] **Daily Log Review**: Check error logs daily for issues
- [ ] **User Feedback**: Collect and respond to user feedback
- [ ] **Performance Metrics**: Monitor upload/playback success rates
- [ ] **Cost Tracking**: Monitor Cloudflare usage and costs

### Week 2-4: Optimization
- [ ] **Performance Tuning**: Optimize based on usage patterns
- [ ] **User Training**: Provide additional training if needed
- [ ] **Documentation Updates**: Update user guides based on feedback
- [ ] **Feature Requests**: Collect and prioritize enhancement requests

### Month 1: Review and Planning
- [ ] **Success Review**: Evaluate deployment against success metrics
- [ ] **Cost Analysis**: Review actual vs. projected costs
- [ ] **User Adoption**: Measure feature adoption rates
- [ ] **Future Planning**: Plan next phase enhancements

## Emergency Contacts

### Technical Contacts
- [ ] **Moodle Administrator**: [Name, Phone, Email]
- [ ] **System Administrator**: [Name, Phone, Email]
- [ ] **Database Administrator**: [Name, Phone, Email]
- [ ] **Network Administrator**: [Name, Phone, Email]

### Vendor Contacts
- [ ] **Cloudflare Support**: support.cloudflare.com
- [ ] **Moodle Partner** (if applicable): [Contact details]

### Escalation Procedures
- [ ] **Level 1**: Technical team attempts resolution (0-2 hours)
- [ ] **Level 2**: Escalate to senior technical staff (2-4 hours)
- [ ] **Level 3**: Engage external support/vendors (4+ hours)
- [ ] **Critical**: Immediate rollback if site stability threatened

## Documentation Requirements

### Technical Documentation
- [ ] **Installation Guide**: Complete installation instructions
- [ ] **Configuration Guide**: All configuration options documented
- [ ] **API Documentation**: Cloudflare API integration details
- [ ] **Database Schema**: Table structures and relationships
- [ ] **Security Guide**: Security considerations and best practices

### User Documentation
- [ ] **Student Guide**: How to upload video submissions
- [ ] **Teacher Guide**: How to view and grade video submissions
- [ ] **Administrator Guide**: Plugin management and monitoring
- [ ] **Troubleshooting Guide**: Common issues and solutions

### Operational Documentation
- [ ] **Monitoring Procedures**: What to monitor and how
- [ ] **Backup Procedures**: Backup and recovery processes
- [ ] **Maintenance Procedures**: Regular maintenance tasks
- [ ] **Incident Response**: How to handle issues and outages

---

## Deployment Sign-off

### Pre-Deployment Approval
- [ ] **Technical Lead**: _________________ Date: _______
- [ ] **System Administrator**: _________________ Date: _______
- [ ] **Security Officer**: _________________ Date: _______
- [ ] **Project Manager**: _________________ Date: _______

### Post-Deployment Verification
- [ ] **Deployment Successful**: _________________ Date: _______
- [ ] **Testing Complete**: _________________ Date: _______
- [ ] **Monitoring Active**: _________________ Date: _______
- [ ] **Documentation Updated**: _________________ Date: _______

### Final Approval
- [ ] **Production Ready**: _________________ Date: _______
- [ ] **Go-Live Authorized**: _________________ Date: _______

---

*This checklist should be customized based on your specific environment and organizational requirements. Review and update regularly based on deployment experience and changing requirements.*