<?php
/**
 * Plugin Name: elnk.pro Link Shortener
 * Plugin URI: https://elnk.pro
 * Description: A WordPress plugin to create short URLs using elnk.pro API
 * Version: 1.0.0
 * Author: Anis Afifi
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELNK_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELNK_PRO_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include the admin class
require_once ELNK_PRO_PLUGIN_PATH . 'includes/class-elnk-pro-admin.php';

// Plugin activation hook
function elnk_pro_activate() {
    $plugin = new ElnkProShortener();
    $plugin->create_database_table();
}
register_activation_hook(__FILE__, 'elnk_pro_activate');

// Initialize the plugin
class ElnkProShortener {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Always initialize admin class (needed for frontend force creation too)
        new ElnkProAdmin();
    }
    
    /**
     * Create database table for storing short URLs
     */
    public function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            original_url text NOT NULL,
            short_url varchar(255) NOT NULL,
            custom_alias varchar(100) DEFAULT NULL,
            link_id varchar(50) DEFAULT NULL,
            clicks int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY short_url (short_url),
            KEY link_id (link_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Start the plugin
new ElnkProShortener();

/**
 * Helper function to get short URL for a post
 * This function can be used by themes and other plugins
 */
function elnk_pro_get_short_url($post_id) {
    return ElnkProAdmin::get_short_url_for_post($post_id);
}

/**
 * Helper function to check if a post has a short URL
 */
function elnk_pro_has_short_url($post_id) {
    $short_url = elnk_pro_get_short_url($post_id);
    return !empty($short_url);
}

/**
 * Helper function to check if shortcode functionality is enabled
 */
function elnk_pro_shortcode_enabled() {
    $shortcode_enabled = get_option('elnk_pro_enable_shortcode', 0);
    $auto_generate = get_option('elnk_pro_auto_generate', 0);
    return $shortcode_enabled && $auto_generate;
}

/**
 * Helper function to display short URL with optional custom HTML
 */
function elnk_pro_the_short_url($post_id = null, $before = '', $after = '') {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $short_url = elnk_pro_get_short_url($post_id);
    
    if ($short_url) {
        echo $before . esc_url($short_url) . $after;
    }
}

/**
 * Helper function to get short URL HTML link
 */
function elnk_pro_get_short_url_link($post_id = null, $text = 'Short URL', $class = '') {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $short_url = elnk_pro_get_short_url($post_id);
    
    if ($short_url) {
        $class_attr = $class ? ' class="' . esc_attr($class) . '"' : '';
        return '<a href="' . esc_url($short_url) . '" target="_blank"' . $class_attr . '>' . esc_html($text) . '</a>';
    }
    
    return '';
}

/**
 * Shortcode to display clipboard icon for copying short URL
 * Usage: [elnk_pro_copy] or [elnk_pro_copy post_id="123"] or [elnk_pro_copy text="Copy Link" style="button"]
 */
function elnk_pro_copy_shortcode($atts) {
    // Check if shortcode is enabled
    if (!elnk_pro_shortcode_enabled()) {
        return ''; // Return empty if shortcode is disabled or auto-generation is off
    }
    
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(),
        'text' => '',
        'icon' => 'clipboard',
        'style' => 'icon', // 'icon', 'button', 'text', 'minimal'
        'class' => '',
        'show_url' => 'false'
    ), $atts, 'elnk_pro_copy');
    
    $post_id = intval($atts['post_id']);
    $short_url = elnk_pro_get_short_url($post_id);
    
    if (!$short_url) {
        return '';
    }
    
    // Generate unique ID for this shortcode instance
    $unique_id = 'elnk-copy-' . uniqid();
    
    // Determine display style
    $button_class = 'elnk-pro-copy-btn';
    if ($atts['class']) {
        $button_class .= ' ' . esc_attr($atts['class']);
    }
    
    $display_text = '';
    $icon_html = '';
    
    switch ($atts['style']) {
        case 'button':
            $button_class .= ' elnk-copy-button';
            $display_text = $atts['text'] ?: 'Copy Short URL';
            $icon_html = '<span class="elnk-copy-icon">📋</span> ';
            break;
            
        case 'text':
            $button_class .= ' elnk-copy-text';
            $display_text = $atts['text'] ?: 'Copy Link';
            break;
            
        case 'minimal':
            $button_class .= ' elnk-copy-minimal';
            $icon_html = '<span class="elnk-copy-icon">📋</span>';
            break;
            
        case 'icon':
        default:
            $button_class .= ' elnk-copy-icon-only';
            $icon_html = '<span class="elnk-copy-icon" title="Copy Short URL">📋</span>';
            break;
    }
    
    $url_display = '';
    if ($atts['show_url'] === 'true') {
        $url_display = ' <span class="elnk-short-url">' . esc_html($short_url) . '</span>';
    }
    
    // Build the HTML
    $html = '<span class="elnk-pro-copy-container">';
    $html .= '<button type="button" class="' . esc_attr($button_class) . '" ';
    $html .= 'id="' . esc_attr($unique_id) . '" ';
    $html .= 'data-url="' . esc_attr($short_url) . '" ';
    $html .= 'data-original-text="' . esc_attr($icon_html . $display_text) . '">';
    $html .= $icon_html . esc_html($display_text);
    $html .= '</button>';
    $html .= $url_display;
    $html .= '</span>';
    
    // Add inline CSS and JavaScript (only once per page)
    static $script_added = false;
    if (!$script_added) {
        $html .= elnk_pro_copy_shortcode_assets();
        $script_added = true;
    }
    
    return $html;
}
add_shortcode('elnk_pro_copy', 'elnk_pro_copy_shortcode');

/**
 * Generate CSS and JavaScript for copy shortcode
 */
function elnk_pro_copy_shortcode_assets() {
    return '
    <style>
    .elnk-pro-copy-container {
        display: inline-block;
        margin: 0 5px;
    }
    
    .elnk-pro-copy-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        margin: 0;
        transition: all 0.2s ease;
    }
    
    .elnk-copy-icon-only {
        font-size: 16px;
        opacity: 0.7;
        vertical-align: middle;
    }
    
    .elnk-copy-icon-only:hover {
        opacity: 1;
        transform: scale(1.1);
    }
    
    .elnk-copy-button {
        background: #0073aa;
        color: white;
        border: 1px solid #0073aa;
        padding: 6px 12px;
        border-radius: 3px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .elnk-copy-button:hover {
        background: #005a87;
        border-color: #005a87;
    }
    
    .elnk-copy-text {
        color: #0073aa;
        text-decoration: underline;
        font-size: 14px;
    }
    
    .elnk-copy-text:hover {
        color: #005a87;
    }
    
    .elnk-copy-minimal {
        color: #666;
        font-size: 14px;
        opacity: 0.8;
    }
    
    .elnk-copy-minimal:hover {
        opacity: 1;
        color: #333;
    }
    
    .elnk-copy-success {
        color: #00a32a !important;
        background: #d4edda !important;
        border-color: #00a32a !important;
    }
    
    .elnk-short-url {
        font-family: monospace;
        font-size: 12px;
        color: #666;
        margin-left: 8px;
    }
    
    @media (max-width: 480px) {
        .elnk-copy-button {
            padding: 4px 8px;
            font-size: 12px;
        }
    }
    </style>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Handle copy button clicks
        document.addEventListener("click", function(e) {
            if (e.target.closest(".elnk-pro-copy-btn")) {
                e.preventDefault();
                
                const button = e.target.closest(".elnk-pro-copy-btn");
                const url = button.getAttribute("data-url");
                const originalText = button.getAttribute("data-original-text");
                
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    // Modern clipboard API
                    navigator.clipboard.writeText(url).then(function() {
                        showCopySuccess(button, originalText);
                    }).catch(function() {
                        fallbackCopy(url, button, originalText);
                    });
                } else {
                    // Fallback for older browsers
                    fallbackCopy(url, button, originalText);
                }
            }
        });
        
        function showCopySuccess(button, originalText) {
            button.classList.add("elnk-copy-success");
            button.innerHTML = "✓ Copied!";
            
            setTimeout(function() {
                button.classList.remove("elnk-copy-success");
                button.innerHTML = originalText;
            }, 2000);
        }
        
        function fallbackCopy(text, button, originalText) {
            // Create temporary textarea
            const textarea = document.createElement("textarea");
            textarea.value = text;
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            
            try {
                textarea.select();
                document.execCommand("copy");
                showCopySuccess(button, originalText);
            } catch (err) {
                // If all else fails, show the URL in a prompt
                prompt("Copy this URL:", text);
            }
            
            document.body.removeChild(textarea);
        }
    });
    </script>';
}
