# BHFE Groups Plugin

A WordPress plugin that allows group administrators (e.g., CPA firm admins) to manage group members and enroll/unenroll users in CPE online courses. Group members can add courses to their cart without payment, and the group administrator receives an invoice for all pending enrollments.

## Features

- **Group Management**: Create and manage groups with multiple members
- **Member Management**: Add/remove users from groups
- **Course Enrollment**: Enroll group members in courses directly from the admin interface
- **Automatic Enrollment**: Group members can checkout courses without payment - they're automatically enrolled and added to the group invoice
- **Running Totals**: Track pending invoice totals for each group
- **Invoice Management**: Create invoices for pending enrollments, either manually or through WooCommerce checkout

## Installation

1. Upload the `bhfe-groups` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the necessary database tables

## Requirements

- WordPress 5.0+
- PHP 7.2+
- WooCommerce (for course products)
- FLMS plugin (for course enrollment functionality)
- BHFE Theme (for Select2 assets)

## Usage

### For Group Administrators

1. Navigate to **Groups** in the WordPress admin menu
2. Create a new group or select an existing one
3. Add members to your group using the user search
4. Enroll members in courses:
   - Select a member from the dropdown
   - Search for and select a course
   - Click "Enroll" to enroll the member
5. View pending enrollments and running totals
6. Create invoices for pending enrollments or checkout on the site

### For Group Members

1. When logged in as a group member, you'll see group information in your My Account area
2. Add courses to your cart as normal
3. At checkout, payment will be bypassed for course products
4. Your enrollment will be tracked and added to the group's invoice
5. You can start taking courses immediately

## Database Tables

The plugin creates the following database tables:

- `wp_bhfe_groups` - Stores group information
- `wp_bhfe_group_members` - Links users to groups
- `wp_bhfe_group_enrollments` - Tracks course enrollments for group members
- `wp_bhfe_group_invoices` - Stores invoice information

## Capabilities

The plugin adds the `manage_bhfe_group` capability to the `customer` role by default. This allows WooCommerce customers to manage groups.

## Hooks and Filters

### Actions

- `bhfe_groups_user_enrolled` - Fired when a user is enrolled in a course via group
- `bhfe_groups_user_unenrolled` - Fired when a user is unenrolled from a course
- `bhfe_groups_invoice_created` - Fired when an invoice is created

### Filters

- `bhfe_groups_course_price` - Filter course price before adding to invoice
- `bhfe_groups_can_enroll` - Filter whether a user can be enrolled in a course

## File Structure

```
bhfe-groups/
├── bhfe-groups.php                    # Main plugin file
├── includes/
│   ├── class-bhfe-groups-admin.php    # Admin interface
│   ├── class-bhfe-groups-database.php # Database operations
│   ├── class-bhfe-groups-enrollment.php # Enrollment management
│   ├── class-bhfe-groups-frontend.php # Frontend functionality
│   ├── class-bhfe-groups-invoice.php  # Invoice management
│   └── class-bhfe-groups-woocommerce.php # WooCommerce integration
└── templates/
    └── admin/
        └── groups-page.php            # Admin template
```

## Support

For issues or questions, please contact the plugin developer.

