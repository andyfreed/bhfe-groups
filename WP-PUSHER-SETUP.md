# WP Pusher Setup Guide

This guide will help you deploy the BHFE Groups plugin to your WordPress site using WP Pusher.

## Prerequisites

1. WP Pusher plugin installed on your WordPress site
2. GitHub repository: https://github.com/andyfreed/bhfe-groups.git
3. GitHub personal access token (if using private repo)

## Setup Steps

### 1. Install WP Pusher (if not already installed)

1. Go to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "WP Pusher"
4. Install and activate the plugin

### 2. Connect WP Pusher to GitHub

1. In WordPress admin, go to **WP Pusher > Settings**
2. Under "GitHub", click "Connect with GitHub" or enter your GitHub credentials
3. If using a personal access token:
   - Go to GitHub.com > Settings > Developer settings > Personal access tokens
   - Generate a new token with `repo` scope
   - Enter the token in WP Pusher settings

### 3. Install the Plugin via WP Pusher

1. Go to **WP Pusher > Install Plugin**
2. Enter the repository URL: `https://github.com/andyfreed/bhfe-groups.git`
3. Select **Branch**: `main` (or `master` if that's your default branch)
4. Choose **Subdirectory**: Leave empty (plugin is in root)
5. Click **Install Plugin**

### 4. Activate the Plugin

1. Go to **Plugins** in WordPress admin
2. Find "BHFE Groups" in the list
3. Click **Activate**

The plugin will automatically create the necessary database tables upon activation.

### 5. Set Up Automatic Updates (Optional)

1. Go to **WP Pusher > Installed Plugins**
2. Find "BHFE Groups"
3. Enable "Auto-update" if you want automatic updates when you push to GitHub

## Updating the Plugin

After making changes to the plugin:

1. Commit your changes:
   ```bash
   git add .
   git commit -m "Your commit message"
   git push origin main
   ```

2. If auto-update is enabled, WP Pusher will automatically update the plugin on your site
3. If auto-update is disabled, go to **WP Pusher > Installed Plugins** and click "Update" for BHFE Groups

## Troubleshooting

### Plugin Not Installing

- Verify the repository URL is correct
- Check that the branch name matches (main vs master)
- Ensure WP Pusher has access to your GitHub account/token

### Database Tables Not Created

- Deactivate and reactivate the plugin
- Check WordPress error logs for any PHP errors
- Verify WooCommerce is installed and active

### Select2 Not Loading

- Ensure the BHFE theme is active (Select2 assets are in the theme)
- Check browser console for JavaScript errors

## Repository Structure

```
bhfe-groups/
├── .gitignore
├── README.md
├── WP-PUSHER-SETUP.md
├── bhfe-groups.php
├── includes/
│   ├── class-bhfe-groups-admin.php
│   ├── class-bhfe-groups-database.php
│   ├── class-bhfe-groups-enrollment.php
│   ├── class-bhfe-groups-frontend.php
│   ├── class-bhfe-groups-invoice.php
│   └── class-bhfe-groups-woocommerce.php
└── templates/
    └── admin/
        └── groups-page.php
```

## Support

For issues with:
- **WP Pusher**: Check [WP Pusher Documentation](https://wppusher.com/docs)
- **Plugin functionality**: See README.md in this repository

