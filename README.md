# Elnk.pro Link Shortener WordPress Plugin

A WordPress plugin that integrates with the elnk.pro API to create short URLs directly from your WordPress admin dashboard.

## Features

- Easy-to-use admin interface for creating short URLs
- Support for both single URL and bulk URL creation
- Secure storage of API credentials in WordPress options
- Custom alias support for branded short URLs
- Real-time URL validation
- Copy-to-clipboard functionality for generated URLs
- Responsive design that works on all devices
- Comprehensive error handling and user feedback

## Installation

1. Upload the `elnk-pro-shortener` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Tools > Elnk.pro Shortener to configure and use the plugin

## Configuration

### API Credentials

Before you can create short URLs, you need to configure your elnk.pro API credentials:

1. **API Key**: Your elnk.pro API authentication token (required)
2. **Domain ID**: The ID of your elnk.pro domain (optional)
3. **Project ID**: The ID of your elnk.pro project (optional)

The API Key is required for authentication. Domain ID and Project ID are optional and will be included in the request only if provided. These credentials are securely stored in your WordPress database and will be remembered for future use.

## Usage

### Single URL Mode

1. Go to Tools > Elnk.pro Shortener in your WordPress admin
2. Enter your API credentials (if not already saved)
3. Enter the destination URL you want to shorten
4. Optionally enter a custom alias for your short URL
5. Click "Create Short URL(s)"
6. Copy the generated short URL from the results section

### Bulk URL Mode

1. Toggle on "Bulk Mode" 
2. Enter multiple URLs in the "Multiple URLs" field (one per line)
3. Optionally enter a custom alias (will be used as base for all URLs)
4. Click "Create Short URL(s)"
5. Copy the generated short URLs from the results section

## API Integration

This plugin integrates with the elnk.pro API using the following endpoint:
- **URL**: `https://elnk.pro/api/links`
- **Method**: POST
- **Authentication**: Bearer token

### Request Format

**Single URL:**
```json
{
  "location_url": "https://example.com",
  "url": "custom-alias", // optional
  "domain_id": "your-domain-id", // optional
  "project_id": "your-project-id" // optional
}
```

**Bulk URLs:**
```json
{
  "location_urls": [
    "https://example1.com",
    "https://example2.com"
  ],
  "url": "custom-alias", // optional
  "domain_id": "your-domain-id", // optional
  "project_id": "your-project-id" // optional
}
```

## Security

- API credentials are stored securely using WordPress options
- All user inputs are sanitized and validated
- CSRF protection using WordPress nonces
- URL validation to prevent malicious inputs
- Capability checks to ensure only authorized users can access the admin page

## System Requirements

- WordPress 4.0 or higher
- PHP 5.6 or higher
- cURL extension enabled
- Valid elnk.pro account and API access

## Troubleshooting

### Common Issues

1. **"API Key is required" error**
   - Ensure the API Key field is filled out
   - Check that your API key is valid and active

2. **"Curl error" messages**
   - Verify your server has cURL extension installed
   - Check your server's outbound connection settings
   - Ensure your server can connect to https://elnk.pro

3. **"API request failed" errors**
   - Verify your API credentials are correct
   - Check that your elnk.pro account is active
   - Ensure your domain and project IDs are valid

4. **URLs not validating**
   - Make sure URLs include the protocol (http:// or https://)
   - Check that URLs are properly formatted

### Getting Help

If you encounter issues:
1. Check the WordPress error logs
2. Verify your elnk.pro account status
3. Test your API credentials using other methods
4. Contact elnk.pro support for API-related issues

## File Structure

```
elnk-pro-shortener/
├── elnk-pro-shortener.php          # Main plugin file
├── includes/
│   └── class-elnk-pro-admin.php    # Admin functionality
├── assets/
│   ├── admin-style.css             # Admin page styles
│   └── admin-script.js             # Admin page JavaScript
└── README.md                       # This file
```

## License

This plugin is released under the GPL v2 license, the same license as WordPress itself.

## Support

For plugin-specific issues, please check the troubleshooting section above. For elnk.pro API issues, contact elnk.pro support directly.

## Changelog

### Version 1.0.0
- Initial release
- Single and bulk URL creation
- Admin interface with credential storage
- Real-time validation and error handling
- Responsive design
