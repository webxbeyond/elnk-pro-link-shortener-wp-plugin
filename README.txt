=== elnk.pro Link Shortener ===
Contributors: Anis Afifi
Tags: url shortener, links, elnk.pro, short links
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and manage short URLs using the elnk.pro API directly from your WordPress admin.

== Description ==

The elnk.pro Link Shortener plugin allows you to create and manage short URLs using the elnk.pro API service. This plugin integrates seamlessly with your WordPress site and provides a user-friendly interface for URL shortening.

**Key Features:**

* Create short URLs manually through the admin interface
* Auto-generate short URLs for published posts (configurable by post type)
* Force create short URLs for existing posts when visited
* Shortcode support with copy-to-clipboard functionality
* Comprehensive post deletion management (removes short URLs when posts are deleted)
* Support for custom domains and projects from elnk.pro
* Bulk URL creation capabilities
* Database storage with automatic schema management

**Requirements:**

You must have an active account with elnk.pro to use this plugin. Sign up at https://elnk.pro/register

== Installation ==

1. Upload the plugin files to your `/wp-content/plugins/elnk-pro-shortener/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to "elnk.pro" > "Settings" in your WordPress admin
4. Enter your elnk.pro API credentials
5. Configure your desired settings for auto-generation and shortcodes

== Configuration ==

1. **API Settings**: Enter your elnk.pro API key, and optionally your domain ID and project ID
2. **Auto-Generation**: Enable automatic short URL creation for selected post types
3. **Force Creation**: Enable creation of short URLs for existing posts when visited
4. **Shortcodes**: Enable the [elnk_pro_copy] shortcode functionality

== Usage ==

**Manual URL Creation:**
- Go to "elnk.pro" > "Short URLs"
- Enter your destination URL and optional custom alias
- Click "Create Short URL"

**Auto-Generation:**
- Enable in settings and select post types
- Short URLs will be created automatically when posts are published

**Shortcode:**
Use `[elnk_pro_copy]` in any post or page to display the short URL with a copy button.

**Theme Integration:**
Use the helper functions in your theme:
- `elnk_pro_get_short_url($post_id)` - Get the short URL for a post
- `elnk_pro_display_copy_button($post_id)` - Display a copy button

== Frequently Asked Questions ==

= Do I need an elnk.pro account? =

Yes, you must have an active elnk.pro account and API key to use this plugin.

= Can I use my own domain? =

Yes, if you have a custom domain configured in your elnk.pro account, you can enter the domain ID in the plugin settings.

= What happens when I delete a post? =

The plugin automatically removes the corresponding short URL from both the database and elnk.pro when a post is deleted.

== Changelog ==

= 1.0.0 =
* Initial release
* Manual URL creation
* Auto-generation for posts
* Shortcode support
* Force creation feature
* Comprehensive deletion management
* Production-ready optimized code

== Support ==

For support with the elnk.pro service, visit: https://elnk.pro/
For plugin-specific issues, please use the WordPress support forum.
