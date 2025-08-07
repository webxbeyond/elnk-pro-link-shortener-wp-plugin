# Theme and Plugin Integration Guide for Short URL Display

## Your Plugin's Meta Integration

Your elnk.pro plugin already includes robust meta integration features:

### Built-in Meta Storage
- **Meta Key**: `_elnk_pro_short_url` (stores the short URL)
- **Meta Key**: `_elnk_pro_link_id` (stores the elnk.pro link ID)
- **Setting**: Enable "Save to Post Meta" in plugin settings

### Configuration Requirements
To use all features of the plugin, configure these settings in **Elnk.pro Shortener > Settings**:

1. **API Credentials**: Add your elnk.pro API key (required)
2. **Auto Short URL Generation**: Enable to automatically create short URLs when posts are published
3. **Post Types**: Select which post types should generate short URLs
4. **Save to Post Meta**: Enable for theme/plugin compatibility 
5. **Enable Copy Shortcode**: Enable to use [elnk_pro_copy] shortcode (requires auto-generation)

**Note**: The copy shortcode feature requires both "Auto Short URL Generation" and "Enable Copy Shortcode" to be enabled.

### Helper Functions Available
```php
// Get short URL for a post
$short_url = elnk_pro_get_short_url($post_id);

// Check if post has short URL
$has_url = elnk_pro_has_short_url($post_id);

// Check if shortcode functionality is enabled
$shortcode_enabled = elnk_pro_shortcode_enabled();

// Display short URL with custom HTML
elnk_pro_the_short_url($post_id, '<p>Short URL: ', '</p>');

// Get short URL as HTML link
$link = elnk_pro_get_short_url_link($post_id, 'Share', 'share-button');
```

### Shortcode for Copy to Clipboard
**Requirements**: Enable "Auto Short URL Generation" and "Enable Copy Shortcode" in plugin settings.

```
[elnk_pro_copy]                           <!-- Simple clipboard icon -->
[elnk_pro_copy style="button"]            <!-- Button style with text -->
[elnk_pro_copy style="text" text="Copy URL"] <!-- Text link style -->
[elnk_pro_copy style="minimal"]           <!-- Minimal icon -->
[elnk_pro_copy show_url="true"]           <!-- Show URL next to icon -->
[elnk_pro_copy post_id="123"]             <!-- Specific post ID -->
[elnk_pro_copy class="my-custom-class"]   <!-- Add custom CSS class -->
```

**Shortcode Parameters:**
- `post_id` - Specific post ID (default: current post)
- `text` - Custom button/link text
- `style` - Display style: `icon`, `button`, `text`, `minimal` (default: `icon`)
- `class` - Additional CSS classes
- `show_url` - Show the short URL next to the button (`true`/`false`)

**Note**: The shortcode only works when both "Auto Short URL Generation" and "Enable Copy Shortcode" are enabled in the plugin settings. This ensures consistent functionality and proper URL availability.

## Plugins That Support Custom Meta Display

### 1. **Advanced Custom Fields (ACF)**
- **Purpose**: Create custom fields and display them anywhere
- **Integration**: Can display your `_elnk_pro_short_url` meta field
- **Usage**:
```php
// In theme templates
$short_url = get_field('_elnk_pro_short_url');
if ($short_url) {
    echo '<a href="' . $short_url . '">Share</a>';
}
```

### 2. **Meta Box**
- **Purpose**: Create custom meta boxes and fields
- **Integration**: Can read and display your existing meta fields
- **Features**: Frontend display, conditional logic

### 3. **Toolset Types**
- **Purpose**: Custom post types and fields
- **Integration**: Can display custom meta in post listings
- **Features**: Views and templates for frontend display

### 4. **Pods**
- **Purpose**: Custom content types and fields
- **Integration**: Can extend existing post types with custom field display
- **Features**: Frontend forms and displays

### 5. **Custom Fields Suite (CFS)**
- **Purpose**: Simple custom fields management
- **Integration**: Can read existing meta fields
- **Features**: Loop-based display in templates

## Popular Themes with Meta Field Support

### 1. **GeneratePress**
- **Hook Integration**: Supports custom hooks for meta display
- **Elements**: Can add custom elements showing meta fields
- **Integration Example**:
```php
// In theme's functions.php or child theme
add_action('generate_after_entry_title', 'display_short_url');
function display_short_url() {
    if (elnk_pro_has_short_url(get_the_ID())) {
        echo '<div class="short-url-display">';
        echo elnk_pro_get_short_url_link(get_the_ID(), 'Share This Post', 'btn btn-share');
        echo '</div>';
    }
}
```

### 2. **Astra**
- **Custom Layouts**: Supports custom meta field displays
- **Hooks**: Multiple action hooks for content customization
- **Integration Example**:
```php
add_action('astra_entry_content_after', 'astra_show_short_url');
function astra_show_short_url() {
    $short_url = elnk_pro_get_short_url(get_the_ID());
    if ($short_url) {
        echo '<div class="ast-short-url"><strong>Short URL:</strong> <a href="' . esc_url($short_url) . '">' . esc_html($short_url) . '</a></div>';
    }
}
```

### 3. **OceanWP**
- **Hooks**: Extensive hook system
- **Custom Fields**: Built-in support for displaying custom meta
- **Integration Example**:
```php
add_action('ocean_after_single_post_title', 'ocean_display_short_url');
function ocean_display_short_url() {
    if (is_single() && elnk_pro_has_short_url(get_the_ID())) {
        elnk_pro_the_short_url(get_the_ID(), '<p class="short-url-meta">🔗 ', '</p>');
    }
}
```

### 4. **Neve**
- **Customizer**: Options for post meta display
- **Hooks**: Action hooks for content modification
- **Integration**: Custom post meta display options

### 5. **Kadence**
- **Meta Options**: Built-in post meta customization
- **Custom Elements**: Can create custom elements for meta display
- **Integration**: Supports custom meta field display

## Page Builders with Meta Support

### 1. **Elementor**
- **Dynamic Content**: Can display custom meta fields
- **Widgets**: Custom meta field widgets available
- **Integration**:
  - Use Dynamic Tags to display `_elnk_pro_short_url`
  - Create custom widgets for short URL display

### 2. **Beaver Builder**
- **Field Connections**: Connect to custom meta fields
- **Modules**: Custom modules for meta display
- **Integration**: Field connection to your meta fields

### 3. **Oxygen Builder**
- **Dynamic Data**: Extensive meta field support
- **Custom Elements**: Can create short URL display elements
- **Integration**: Direct meta field connections

### 4. **Divi**
- **Dynamic Content**: Meta field display options
- **Custom Modules**: Third-party modules for meta display
- **Integration**: Custom CSS and PHP for meta display

## Social Sharing Plugins Integration

### 1. **Social Warfare**
- **Custom URLs**: Can use custom meta for sharing URLs
- **Integration**: Hook into share URL generation
```php
add_filter('social_warfare_custom_url', function($url, $post_id) {
    $short_url = elnk_pro_get_short_url($post_id);
    return $short_url ?: $url;
}, 10, 2);
```

### 2. **AddToAny**
- **Custom Sharing**: Can customize share URLs
- **Integration**: Use your short URLs for sharing

### 3. **Ultimate Social Media Icons**
- **Custom URLs**: Supports custom meta for sharing
- **Integration**: Meta field integration for share URLs

## SEO Plugin Integration

### 1. **Yoast SEO**
- **Custom Fields**: Can display meta in snippets
- **Schema**: Can use meta fields in schema markup

### 2. **RankMath**
- **Custom Meta**: Support for custom meta display
- **Schema**: Rich snippets with custom meta

## Custom Integration Examples

### Using the Copy Shortcode
```html
<!-- In post content or widgets -->
Share this post: [elnk_pro_copy style="button" text="Copy Link"]

<!-- In theme templates -->
<?php echo do_shortcode('[elnk_pro_copy style="minimal"]'); ?>

<!-- Different styles -->
[elnk_pro_copy]                                    <!-- 📋 icon only -->
[elnk_pro_copy style="button"]                     <!-- Button: "📋 Copy Short URL" -->
[elnk_pro_copy style="text" text="Get Link"]       <!-- Text link: "Get Link" -->
[elnk_pro_copy style="minimal"]                    <!-- Minimal: "📋" -->
[elnk_pro_copy show_url="true"]                    <!-- Icon + URL display -->
```

### Display in Post Content
```php
// Automatically add short URL to post content
add_filter('the_content', 'add_short_url_to_content');
function add_short_url_to_content($content) {
    if (is_single() && elnk_pro_has_short_url(get_the_ID())) {
        $short_url_html = '<div class="post-short-url">';
        $short_url_html .= '<strong>Share this post:</strong> ';
        $short_url_html .= elnk_pro_get_short_url_link(get_the_ID(), elnk_pro_get_short_url(get_the_ID()), 'short-url-link');
        $short_url_html .= '</div>';
        $content .= $short_url_html;
    }
    return $content;
}
```

### Widget Integration
```php
// Create widget to display short URL
class Short_URL_Widget extends WP_Widget {
    // Widget implementation
    public function widget($args, $instance) {
        if (is_single() && elnk_pro_has_short_url(get_the_ID())) {
            echo $args['before_widget'];
            echo '<h3>Share This Post</h3>';
            elnk_pro_the_short_url(get_the_ID(), '<p>', '</p>');
            echo $args['after_widget'];
        }
    }
}
```

### REST API Integration
```php
// Add short URL to REST API
add_action('rest_api_init', function() {
    register_rest_field('post', 'short_url', array(
        'get_callback' => function($post) {
            return elnk_pro_get_short_url($post['id']);
        }
    ));
});
```

### Theme Template Integration

### Shortcode in Templates
```php
// In single.php or content-single.php
if (elnk_pro_has_short_url(get_the_ID())) {
    echo '<div class="entry-meta-short-url">';
    echo '<span class="short-url-label">Quick Share:</span> ';
    echo do_shortcode('[elnk_pro_copy style="button" text="Copy"]');
    echo '</div>';
}

// In header.php or anywhere
echo do_shortcode('[elnk_pro_copy style="minimal"]');
```

### Single Post Template
```php
// In single.php or content-single.php
if (elnk_pro_has_short_url(get_the_ID())) {
    echo '<div class="entry-meta-short-url">';
    echo '<span class="short-url-label">Quick Share:</span> ';
    echo elnk_pro_get_short_url_link(get_the_ID(), 'Copy Link', 'copy-short-url');
    echo '</div>';
}
```

### Archive Template
```php
// In archive.php or content.php
echo '<div class="post-meta">';
echo '<span class="post-date">' . get_the_date() . '</span>';
if (elnk_pro_has_short_url(get_the_ID())) {
    echo '<span class="post-share">';
    echo elnk_pro_get_short_url_link(get_the_ID(), '🔗', 'quick-share');
    echo '</span>';
}
echo '</div>';
```

## Recommendations

1. **For Theme Developers**: Use the provided helper functions for clean integration
2. **For Plugin Developers**: Check for `elnk_pro_has_short_url()` before displaying
3. **For Site Owners**: Enable "Save to Post Meta" in plugin settings for maximum compatibility
4. **For Advanced Users**: Create custom templates using the helper functions

## CSS Styling Examples

```css
/* Short URL display styling */
.short-url-display {
    background: #f8f9fa;
    padding: 10px;
    border-left: 4px solid #007cba;
    margin: 15px 0;
}

.short-url-link {
    color: #007cba;
    text-decoration: none;
    font-weight: 600;
}

.short-url-link:hover {
    text-decoration: underline;
}

/* Meta box styling */
.entry-meta-short-url {
    font-size: 14px;
    color: #666;
    margin: 10px 0;
}

.copy-short-url {
    background: #007cba;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    text-decoration: none;
    font-size: 12px;
}

/* Custom shortcode styling */
.elnk-pro-copy-container {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Custom button style */
.my-custom-copy-btn {
    background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.my-custom-copy-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

/* Floating copy button */
.floating-copy {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    background: #007cba;
    color: white;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
```

## Advanced Shortcode Examples

### Multiple Style Options
```html
<!-- Minimal icon with tooltip -->
[elnk_pro_copy style="icon"]

<!-- Button with custom text -->
[elnk_pro_copy style="button" text="Share Post"]

<!-- Text link style -->
[elnk_pro_copy style="text" text="Copy Short URL"]

<!-- Show URL alongside button -->
[elnk_pro_copy style="button" show_url="true"]

<!-- Custom styling -->
[elnk_pro_copy style="button" class="my-custom-copy-btn"]

<!-- For specific post -->
[elnk_pro_copy post_id="123" style="minimal"]
```

### Widget and Sidebar Usage
```php
// In WordPress widgets or sidebars
if (is_single()) {
    echo '<div class="widget-short-url">';
    echo '<h4>Share This Post</h4>';
    echo do_shortcode('[elnk_pro_copy style="button" text="Copy Link"]');
    echo '</div>';
}
```

### Content Filter Integration
```php
// Automatically add copy button to all post content
add_filter('the_content', function($content) {
    if (is_single() && elnk_pro_has_short_url(get_the_ID()) && elnk_pro_shortcode_enabled()) {
        $copy_button = '<div class="auto-copy-section">';
        $copy_button .= '<p>Share this post: ' . do_shortcode('[elnk_pro_copy style="button" text="Copy URL"]') . '</p>';
        $copy_button .= '</div>';
        $content .= $copy_button;
    }
    return $content;
});

// Alternative: Check settings manually
add_filter('the_content', function($content) {
    if (is_single() && elnk_pro_has_short_url(get_the_ID())) {
        // Check if shortcode is enabled before using it
        if (elnk_pro_shortcode_enabled()) {
            $content .= '<p>Quick share: ' . do_shortcode('[elnk_pro_copy style="minimal"]') . '</p>';
        } else {
            // Fallback to regular link if shortcode is disabled
            $short_url = elnk_pro_get_short_url(get_the_ID());
            $content .= '<p>Share: <a href="' . esc_url($short_url) . '">' . esc_html($short_url) . '</a></p>';
        }
    }
    return $content;
});
```

Your elnk.pro plugin is already well-designed for integration with other themes and plugins through its meta field storage and helper functions!
