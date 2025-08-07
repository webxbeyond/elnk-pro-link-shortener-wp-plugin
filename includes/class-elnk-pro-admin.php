<?php
/**
 * Admin functionality for Elnk.pro Link Shortener
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElnkProAdmin {
    
    public function __construct() {
        // Admin-specific hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menus'));
            add_action('admin_init', array($this, 'handle_form_submission'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
            add_action('wp_ajax_elnk_pro_delete_url', array($this, 'ajax_delete_url'));
            
            // Ensure table schema is up to date
            add_action('admin_init', array($this, 'maybe_update_database'));
            
            // Add meta box to post edit screens
            add_action('add_meta_boxes', array($this, 'add_short_url_meta_box'));
        }
        
        // Hooks that work on both admin and frontend
        // Auto short URL creation on post publish
        add_action('transition_post_status', array($this, 'auto_create_short_url_on_publish'), 10, 3);
        
        // Alternative hook for post publishing (fallback)
        add_action('wp_insert_post', array($this, 'auto_create_short_url_on_insert'), 10, 3);
        
        // Force create short URL on post visit (frontend only)
        if (!is_admin()) {
            add_action('wp', array($this, 'force_create_short_url_on_visit'));
        }
        
        // Delete short URL when post is deleted - comprehensive hook coverage
        add_action('before_delete_post', array($this, 'delete_short_url_on_post_delete'), 10, 1);
        add_action('deleted_post', array($this, 'delete_short_url_on_post_delete'), 10, 1);
        add_action('wp_delete_post', array($this, 'delete_short_url_on_post_delete'), 10, 1);
        add_action('delete_post', array($this, 'delete_short_url_on_post_delete'), 10, 1);
        
        // Handle trash and untrash scenarios
        add_action('wp_trash_post', array($this, 'delete_short_url_on_post_delete'), 10, 1);
        add_action('untrashed_post', array($this, 'maybe_restore_short_url'), 10, 1);
        
        // Handle bulk delete operations
        add_action('wp_ajax_delete-post', array($this, 'ajax_delete_post_handler'), 5);
        add_action('wp_ajax_nopriv_delete-post', array($this, 'ajax_delete_post_handler'), 5);
        
        // Add universal deletion monitoring
        add_action('init', array($this, 'setup_deletion_monitoring'), 1);
        
        // Hook into WordPress shutdown to catch any missed deletions
        add_action('shutdown', array($this, 'check_for_deleted_posts'), 999);
        
        // Hook into REST API deletions
        add_action('rest_after_delete_post', array($this, 'rest_delete_post_handler'), 10, 3);
        add_action('rest_delete_post', array($this, 'rest_delete_post_handler'), 10, 3);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menus() {
        // Main menu page for URL creation
        add_menu_page(
            'elnk.pro',
            'elnk.pro',
            'manage_options',
            'elnk-pro-shortener',
            array($this, 'url_creation_page'),
            'dashicons-admin-links',
            30
        );
        
        // Submenu for short URLs
        add_submenu_page(
            'elnk-pro-shortener',
            'Short URLs',
            'Short URLs',
            'manage_options',
            'elnk-pro-shortener',
            array($this, 'url_creation_page')
        );
        
        // Submenu for settings
        add_submenu_page(
            'elnk-pro-shortener',
            'Settings',
            'Settings',
            'manage_options',
            'elnk-pro-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_styles($hook) {
        if (!in_array($hook, array('toplevel_page_elnk-pro-shortener', 'elnk-pro-shortener_page_elnk-pro-settings'))) {
            return;
        }
        
        wp_enqueue_style(
            'elnk-pro-admin',
            ELNK_PRO_PLUGIN_URL . 'assets/admin-style.css',
            array(),
            '1.0.1'
        );
        
        wp_enqueue_script(
            'elnk-pro-admin',
            ELNK_PRO_PLUGIN_URL . 'assets/admin-script.js',
            array('jquery'),
            '1.0.2',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('elnk-pro-admin', 'elnkProAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'deleteNonce' => wp_create_nonce('elnk_pro_delete_nonce')
        ));
    }
    
    /**
     * Maybe update database schema if needed
     */
    public function maybe_update_database() {
        // Only run on admin pages for this plugin
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'elnk-pro') === false)) {
            return;
        }
        
        // Check if table exists and update schema if needed
        if ($this->check_table_exists()) {
            $this->update_table_schema();
        }
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submission() {
        // Check if this is a POST request
        if (empty($_POST)) {
            return;
        }
        
        // Check for settings form submission by presence of required fields
        if (isset($_POST['elnk_pro_nonce']) && isset($_POST['api_key'])) {
            // Check if we're on settings page or came from it
            $current_screen = get_current_screen();
            $is_settings_page = ($current_screen && $current_screen->id === 'elnk-pro-shortener_page_elnk-pro-settings') ||
                               (isset($_POST['_wp_http_referer']) && strpos($_POST['_wp_http_referer'], 'page=elnk-pro-settings') !== false);
            
            if ($is_settings_page) {
                // Verify nonce
                if (!wp_verify_nonce($_POST['elnk_pro_nonce'], 'elnk_pro_action')) {
                    wp_die('Security check failed');
                }
                $this->save_settings();
                return;
            }
        }
        
        // Check for URL creation form submission by presence of required fields
        if (isset($_POST['elnk_pro_nonce']) && (isset($_POST['destination_url']) || isset($_POST['multiple_urls']))) {
            // Check if we're on URL creation page or came from it
            $current_screen = get_current_screen();
            $is_url_creation_page = ($current_screen && $current_screen->id === 'toplevel_page_elnk-pro-shortener') ||
                                   (isset($_POST['_wp_http_referer']) && strpos($_POST['_wp_http_referer'], 'page=elnk-pro-shortener') !== false);
            
            if ($is_url_creation_page) {
                // Verify nonce
                if (!wp_verify_nonce($_POST['elnk_pro_nonce'], 'elnk_pro_action')) {
                    wp_die('Security check failed');
                }
                $this->create_short_url();
                return;
            }
        }
        
        // Handle explicit settings save button
        if (isset($_POST['elnk_pro_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['elnk_pro_nonce']) || !wp_verify_nonce($_POST['elnk_pro_nonce'], 'elnk_pro_action')) {
                wp_die('Security check failed');
            }
            $this->save_settings();
            return;
        }
        
        // Handle URL creation
        if (isset($_POST['elnk_pro_create_url'])) {
            // Verify nonce
            if (!isset($_POST['elnk_pro_nonce']) || !wp_verify_nonce($_POST['elnk_pro_nonce'], 'elnk_pro_action')) {
                wp_die('Security check failed');
            }
            $this->create_short_url();
            return;
        }
    }
    
    /**
     * Auto create short URL when post is published
     */
    public function auto_create_short_url_on_publish($new_status, $old_status, $post) {
        // Check if auto-generation is enabled
        $auto_generate = get_option('elnk_pro_auto_generate', 0);
        if (!$auto_generate) {
            return;
        }
        
        // Check if API key is configured
        $api_key = get_option('elnk_pro_api_key');
        if (empty($api_key)) {
            return;
        }
        
        // Check if this is a transition to published status
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        // Check if this post type is enabled for auto-generation
        $enabled_post_types = get_option('elnk_pro_auto_post_types', array());
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Check if short URL already exists for this post
        if ($this->post_has_short_url($post->ID)) {
            return;
        }
        
        // Generate short URL
        $post_url = get_permalink($post->ID);
        $custom_alias = '';
        
        $result = $this->make_api_request($api_key, array(
            'location_url' => $post_url,
            'url' => null // Send null to let API generate random alias
        ));
        
        if ($result['success']) {
            // Get link details and construct URL
            $response_data = $result['data'];
            $link_id = null;
            
            if (isset($response_data['data']['id'])) {
                $link_id = $response_data['data']['id'];
            } elseif (isset($response_data['id'])) {
                $link_id = $response_data['id'];
            }
            
            if ($link_id) {
                $link_details = $this->get_link_details($api_key, $link_id);
                
                if ($link_details['success']) {
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    
                    if ($short_url) {
                        // Save to database
                        $this->save_url_to_database($post_url, $short_url, $custom_alias, $link_id);
                        
                        // Save to post meta if enabled
                        if (get_option('elnk_pro_save_to_meta', 0)) {
                            update_post_meta($post->ID, '_elnk_pro_short_url', $short_url);
                            update_post_meta($post->ID, '_elnk_pro_link_id', $link_id);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Alternative auto create short URL when post is inserted/updated (fallback method)
     */
    public function auto_create_short_url_on_insert($post_id, $post, $update) {
        // Check if auto-generation is enabled
        $auto_generate = get_option('elnk_pro_auto_generate', 0);
        if (!$auto_generate) {
            return;
        }
        
        // Check if API key is configured
        $api_key = get_option('elnk_pro_api_key');
        if (empty($api_key)) {
            return;
        }
        
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if this post type is enabled for auto-generation
        $enabled_post_types = get_option('elnk_pro_auto_post_types', array());
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Check if short URL already exists for this post
        if ($this->post_has_short_url($post_id)) {
            return;
        }
        
        // Avoid infinite loops - check if we're already processing this post
        static $processing = array();
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;
        
        // Generate short URL
        $post_url = get_permalink($post_id);
        $custom_alias = '';
        
        $result = $this->make_api_request($api_key, array(
            'location_url' => $post_url,
            'url' => null // Send null to let API generate random alias
        ));
        
        if ($result['success']) {
            $response_data = $result['data'];
            $link_id = null;
            
            if (isset($response_data['data']['id'])) {
                $link_id = $response_data['data']['id'];
            } elseif (isset($response_data['id'])) {
                $link_id = $response_data['id'];
            }
            
            if ($link_id) {
                $link_details = $this->get_link_details($api_key, $link_id);
                
                if ($link_details['success']) {
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    
                    if ($short_url) {
                        $this->save_url_to_database($post_url, $short_url, $custom_alias, $link_id);
                        
                        if (get_option('elnk_pro_save_to_meta', 0)) {
                            update_post_meta($post_id, '_elnk_pro_short_url', $short_url);
                            update_post_meta($post_id, '_elnk_pro_link_id', $link_id);
                        }
                    }
                }
            }
        }
        
        unset($processing[$post_id]);
    }
    
    /**
     * Force create short URL when user visits a post that doesn't have one
     */
    public function force_create_short_url_on_visit() {
        // Only run on single post pages
        if (!is_single()) {
            return;
        }
        
        // Check if force create is enabled
        $force_create = get_option('elnk_pro_force_create_on_visit', 0);
        if (!$force_create) {
            return;
        }
        
        // Check if auto-generation is enabled (required for force create)
        $auto_generate = get_option('elnk_pro_auto_generate', 0);
        if (!$auto_generate) {
            return;
        }
        
        // Check if API key is configured
        $api_key = get_option('elnk_pro_api_key');
        if (empty($api_key)) {
            return;
        }
        
        $post_id = get_the_ID();
        $post = get_post($post_id);
        
        if (!$post) {
            return;
        }
        
        // Check if this post type is enabled for auto-generation
        $enabled_post_types = get_option('elnk_pro_auto_post_types', array());
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }
        
        // Check if post is published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if short URL already exists for this post
        if ($this->post_has_short_url($post_id)) {
            return;
        }
        
        // Avoid multiple simultaneous requests for the same post
        $transient_key = 'elnk_pro_creating_' . $post_id;
        if (get_transient($transient_key)) {
            return;
        }
        
        // Set transient to prevent duplicate requests (expires in 5 minutes)
        set_transient($transient_key, 1, 300);
        
        // Create short URL in background (non-blocking)
        $this->create_short_url_background($post_id, $api_key);
    }
    
    /**
     * Create short URL in background for force create functionality
     */
    private function create_short_url_background($post_id, $api_key) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Generate short URL
        $post_url = get_permalink($post_id);
        $custom_alias = '';
        
        $result = $this->make_api_request($api_key, array(
            'location_url' => $post_url,
            'url' => null // Use random alias
        ));
        
        if ($result['success']) {
            // Get link details and construct URL
            $response_data = $result['data'];
            $link_id = null;
            
            if (isset($response_data['data']['id'])) {
                $link_id = $response_data['data']['id'];
            } elseif (isset($response_data['id'])) {
                $link_id = $response_data['id'];
            }
            
            if ($link_id) {
                $link_details = $this->get_link_details($api_key, $link_id);
                
                if ($link_details['success']) {
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    
                    if ($short_url) {
                        // Save to database
                        $this->save_url_to_database($post_url, $short_url, $custom_alias, $link_id);
                        
                        // Save to post meta if enabled
                        if (get_option('elnk_pro_save_to_meta', 0)) {
                            update_post_meta($post_id, '_elnk_pro_short_url', $short_url);
                            update_post_meta($post_id, '_elnk_pro_link_id', $link_id);
                        }
                    }
                }
            }
        }
        
        // Clear the transient
        delete_transient('elnk_pro_creating_' . $post_id);
    }
    
    /**
     * Delete short URL when WordPress post is deleted
     */
    public function delete_short_url_on_post_delete($post_id) {
        // Prevent duplicate processing from multiple hooks
        static $processing = array();
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;
        
        // Get post data before it's potentially deleted
        $post = get_post($post_id);
        if (!$post) {
            unset($processing[$post_id]);
            return;
        }
        
        // Check if this post type is enabled for auto-generation
        $enabled_post_types = get_option('elnk_pro_auto_post_types', array());
        if (!in_array($post->post_type, $enabled_post_types)) {
            unset($processing[$post_id]);
            return;
        }
        
        // Get the post URL to find the short URL
        $post_url = get_permalink($post_id);
        if (!$post_url || $post_url === false) {
            // If permalink fails, construct URL manually
            $post_url = home_url("?p={$post_id}");
        }
        
        // Find short URL entry in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        
        $url_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE original_url = %s",
            $post_url
        ));
        
        if (!$url_data) {
            unset($processing[$post_id]);
            return;
        }
        
        // Try to delete from elnk.pro API first
        $api_key = get_option('elnk_pro_api_key');
        if (!empty($api_key) && !empty($url_data->link_id)) {
            $this->delete_link_from_api($api_key, $url_data->link_id);
        }
        
        // Always delete from WordPress database regardless of API result
        $wpdb->delete(
            $table_name,
            array('id' => $url_data->id),
            array('%d')
        );
        
        // Also remove from post meta if it exists
        delete_post_meta($post_id, '_elnk_pro_short_url');
        delete_post_meta($post_id, '_elnk_pro_link_id');
        
        unset($processing[$post_id]);
    }
    
    /**
     * AJAX handler for post deletion - catches AJAX delete requests
     */
    public function ajax_delete_post_handler() {
        // Check if this is a post deletion request
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'delete-post' && isset($_REQUEST['id'])) {
            $post_id = intval($_REQUEST['id']);
            $this->delete_short_url_on_post_delete($post_id);
        }
    }
    
    /**
     * Handle post restoration from trash (placeholder for future functionality)
     */
    public function maybe_restore_short_url($post_id) {
        // Future: We could implement logic to restore short URLs if needed
    }
    
    /**
     * Setup comprehensive deletion monitoring
     */
    public function setup_deletion_monitoring() {
        // Monitor all possible deletion actions
        $deletion_actions = array(
            'wp_delete_post_revision',
            'wp_delete_attachment',
            'delete_user_meta',
            'wp_ajax_delete-post',
            'wp_ajax_trash-post',
            'admin_action_delete',
            'admin_action_trash'
        );
        
        foreach ($deletion_actions as $action) {
            add_action($action, array($this, 'monitor_deletion_action'), 1, 3);
        }
        
        // Monitor POST requests for deletion patterns
        if (!empty($_POST) && is_admin()) {
            $this->check_for_deletion_request();
        }
    }
    
    /**
     * Monitor various deletion actions
     */
    public function monitor_deletion_action($post_id = null) {
        $current_action = current_action();
        
        // Check for delete/trash in the action name and valid post ID
        if ($post_id && is_numeric($post_id) && (strpos($current_action, 'delete') !== false || strpos($current_action, 'trash') !== false)) {
            $this->delete_short_url_on_post_delete($post_id);
        }
    }
    
    /**
     * Check for deletion requests in POST data
     */
    private function check_for_deletion_request() {
        // Check for various deletion patterns
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            // Check for delete/trash actions
            if (strpos($action, 'delete') !== false || strpos($action, 'trash') !== false) {
                // Look for post ID in various places
                $post_id = null;
                if (isset($_POST['post'])) {
                    $post_id = intval($_POST['post']);
                } elseif (isset($_POST['post_ID'])) {
                    $post_id = intval($_POST['post_ID']);
                } elseif (isset($_POST['id'])) {
                    $post_id = intval($_POST['id']);
                }
                
                if ($post_id) {
                    $this->delete_short_url_on_post_delete($post_id);
                }
            }
        }
        
        // Check for bulk delete operations
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['post'])) {
            if (is_array($_POST['post'])) {
                foreach ($_POST['post'] as $post_id) {
                    $this->delete_short_url_on_post_delete(intval($post_id));
                }
            }
        }
    }
    
    /**
     * Handle REST API post deletion
     */
    public function rest_delete_post_handler($post, $response, $request) {
        if (is_object($post) && isset($post->ID)) {
            $this->delete_short_url_on_post_delete($post->ID);
        }
    }
    
    /**
     * Final check for deleted posts at shutdown
     */
    public function check_for_deleted_posts() {
        // Only run on admin pages where deletions might occur
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Check if we're on a post management page and any deletion might have occurred
        global $pagenow;
        if (in_array($pagenow, array('edit.php', 'post.php', 'admin.php'))) {
            // If there were any POST requests with deletion indicators, process them
            if (!empty($_POST)) {
                // Check for common deletion indicators in POST data
                $post_keys = array_keys($_POST);
                $post_values = array_values($_POST);
                $combined_data = implode('|', array_merge($post_keys, $post_values));
                
                if (strpos($combined_data, 'delete') !== false || strpos($combined_data, 'trash') !== false) {
                    // Additional processing could be added here if needed
                }
            }
        }
    }
    
    /**
     * Check if post already has a short URL
     */
    private function post_has_short_url($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        $post_url = get_permalink($post_id);
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE original_url = %s",
            $post_url
        ));
        
        return !empty($existing);
    }
    
    /**
     * Generate alias from post title
     */
    private function generate_alias_from_post($post) {
        // Use post slug as base
        $alias = $post->post_name;
        
        // If slug is empty or too short, generate from title
        if (empty($alias) || strlen($alias) < 6) {
            $alias = sanitize_title($post->post_title);
        }
        
        // Ensure minimum length of 6 characters
        if (strlen($alias) < 6) {
            $alias = $alias . '-' . $post->ID;
        }
        
        // Limit length to avoid issues
        $alias = substr($alias, 0, 50);
        
        return $alias;
    }
    
    /**
     * Get short URL for a specific post
     */
    public function get_post_short_url($post_id) {
        // First check post meta
        $short_url = get_post_meta($post_id, '_elnk_pro_short_url', true);
        if (!empty($short_url)) {
            return $short_url;
        }
        
        // Fallback to database lookup
        global $wpdb;
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        $post_url = get_permalink($post_id);
        
        $short_url = $wpdb->get_var($wpdb->prepare(
            "SELECT short_url FROM $table_name WHERE original_url = %s ORDER BY created_at DESC LIMIT 1",
            $post_url
        ));
        
        return $short_url;
    }
    
    /**
     * Public static method to get short URL for a post (for external use)
     */
    public static function get_short_url_for_post($post_id) {
        // Create instance to access the method
        $instance = new self();
        return $instance->get_post_short_url($post_id);
    }
    
    /**
     * Add meta box to post edit screens for enabled post types
     */
    public function add_short_url_meta_box() {
        $enabled_post_types = get_option('elnk_pro_auto_post_types', array());
        
        foreach ($enabled_post_types as $post_type) {
            add_meta_box(
                'elnk_pro_short_url',
                'elnk.pro Short URL',
                array($this, 'short_url_meta_box_callback'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Meta box callback to display short URL
     */
    public function short_url_meta_box_callback($post) {
        $short_url = $this->get_post_short_url($post->ID);
        $auto_generate = get_option('elnk_pro_auto_generate', 0);
        
        echo '<div class="elnk-pro-meta-box">';
        
        if ($short_url) {
            echo '<p><strong>Short URL:</strong></p>';
            echo '<input type="text" value="' . esc_attr($short_url) . '" readonly style="width: 100%; margin-bottom: 10px;" onclick="this.select()">';
            echo '<div class="elnk-pro-meta-actions">';
            echo '<a href="' . esc_url($short_url) . '" target="_blank" class="button button-secondary">Visit</a> ';
            echo '<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(\'' . esc_js($short_url) . '\'); this.textContent=\'Copied!\'; setTimeout(() => this.textContent=\'Copy\', 2000);">Copy</button>';
            echo '</div>';
        } else {
            if ($auto_generate && $post->post_status === 'publish') {
                echo '<p style="color: #d63638;"><em>Short URL will be generated when post is published.</em></p>';
            } elseif ($auto_generate) {
                echo '<p style="color: #d63638;"><em>Auto-generation is enabled. Short URL will be created when this post is published.</em></p>';
            } else {
                echo '<p style="color: #646970;"><em>No short URL available. Enable auto-generation in plugin settings or create manually.</em></p>';
            }
        }
        
        echo '</div>';
        
        // Add some inline CSS for the meta box
        echo '<style>
            .elnk-pro-meta-box input[readonly] {
                background: #f6f7f7;
                cursor: text;
            }
            .elnk-pro-meta-actions {
                display: flex;
                gap: 5px;
            }
            .elnk-pro-meta-actions .button {
                flex: 1;
                text-align: center;
                justify-content: center;
            }
        </style>';
    }
    
    /**
     * Save API settings
     */
    private function save_settings() {
        // Save API key
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        update_option('elnk_pro_api_key', $api_key);
        
        // Save domain ID
        $domain_id = isset($_POST['domain_id']) ? sanitize_text_field($_POST['domain_id']) : '';
        update_option('elnk_pro_domain_id', $domain_id);
        
        // Save project ID
        $project_id = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
        update_option('elnk_pro_project_id', $project_id);
        
        // Save auto-generation settings
        $auto_generate = isset($_POST['auto_generate']) ? 1 : 0;
        update_option('elnk_pro_auto_generate', $auto_generate);
        
        // Save selected post types for auto-generation
        $auto_post_types = isset($_POST['auto_post_types']) && is_array($_POST['auto_post_types']) ? $_POST['auto_post_types'] : array();
        update_option('elnk_pro_auto_post_types', $auto_post_types);
        
        // Save custom field option
        $save_to_meta = isset($_POST['save_to_meta']) ? 1 : 0;
        update_option('elnk_pro_save_to_meta', $save_to_meta);
        
        // Save shortcode option (only if auto-generation is enabled)
        $enable_shortcode = isset($_POST['enable_shortcode']) ? 1 : 0;
        update_option('elnk_pro_enable_shortcode', $enable_shortcode);
        
        // Save force create option
        $force_create = isset($_POST['force_create_on_visit']) ? 1 : 0;
        update_option('elnk_pro_force_create_on_visit', $force_create);
        
        // Show success message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        });
    }
    
    /**
     * Create short URL and save to database
     */
    private function create_short_url() {
        $api_key = get_option('elnk_pro_api_key');
        
        if (empty($api_key)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Please configure your API settings first.</p></div>';
            });
            return;
        }
        
        $is_bulk = isset($_POST['is_bulk']) && $_POST['is_bulk'] === '1';
        $alias = sanitize_text_field($_POST['alias']);
        
        if ($is_bulk) {
            $this->create_bulk_urls($api_key, $alias);
        } else {
            $this->create_single_url($api_key, $alias);
        }
    }
    
    /**
     * Create single short URL
     */
    private function create_single_url($api_key, $alias) {
        $destination_url = esc_url_raw($_POST['destination_url']);
        
        if (empty($destination_url)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Destination URL is required.</p></div>';
            });
            return;
        }
        
        // Validate alias length if provided
        if (!empty($alias) && strlen($alias) < 6) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Custom alias must be at least 6 characters long.</p></div>';
            });
            return;
        }
        
        $result = $this->make_api_request($api_key, array(
            'location_url' => $destination_url,
            'url' => !empty($alias) ? $alias : null
        ));
        
        if ($result['success']) {
            // Check if we have the expected response structure - handle nested data
            $response_data = $result['data'];
            if (isset($response_data['data']['id'])) {
                $link_id = $response_data['data']['id'];
            } elseif (isset($response_data['id'])) {
                $link_id = $response_data['id'];
            } else {
                $link_id = null;
            }
            
            if ($link_id) {
                // Fetch the link details to get the URL slug and domain_id
                $link_details = $this->get_link_details($api_key, $link_id);
                
                if ($link_details['success']) {
                    // Construct the short URL using the proper API workflow
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    
                    if ($short_url) {
                        $this->save_url_to_database($destination_url, $short_url, $alias, $link_id);
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success"><p>Short URL created successfully!</p></div>';
                        });
                    } else {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error"><p>Failed to construct the short URL. Please check your domain configuration.</p></div>';
                        });
                    }
                } else {
                    add_action('admin_notices', function() use ($link_details) {
                        echo '<div class="notice notice-error"><p>Error fetching link details: ' . esc_html($link_details['message']) . '</p></div>';
                    });
                }
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>API response is missing the link ID. Please check your API configuration.</p></div>';
                });
            }
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result['message']) . '</p></div>';
            });
        }
    }
    
    /**
     * Create bulk short URLs
     */
    private function create_bulk_urls($api_key, $alias) {
        $multiple_urls = sanitize_textarea_field($_POST['multiple_urls']);
        
        if (empty($multiple_urls)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Multiple URLs field is required for bulk mode.</p></div>';
            });
            return;
        }
        
        $urls = array_filter(array_map('trim', explode("\n", $multiple_urls)));
        
        if (empty($urls)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Please provide valid URLs for bulk mode.</p></div>';
            });
            return;
        }
        
        // Validate alias length if provided
        if (!empty($alias) && strlen($alias) < 6) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Custom alias must be at least 6 characters long.</p></div>';
            });
            return;
        }
        
        // For bulk requests, format the data according to API specs
        $api_data = array(
            'is_bulk' => '1',
            'location_urls' => implode("\n", $urls) // API expects newline-separated URLs
        );
        
        if (!empty($alias)) {
            $api_data['url'] = $alias;
        }
        
        $result = $this->make_api_request($api_key, $api_data);
        
        if ($result['success']) {
            // Handle nested response structure
            $response_data = $result['data'];
            
            // Save each URL to database
            if (isset($response_data['data']['ids']) && is_array($response_data['data']['ids'])) {
                foreach ($response_data['data']['ids'] as $index => $id) {
                    $original_url = isset($urls[$index]) ? $urls[$index] : '';
                    if ($original_url && $id) {
                        // Fetch link details for each created URL
                        $link_details = $this->get_link_details($api_key, $id);
                        
                        if ($link_details['success']) {
                            $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                            if ($short_url) {
                                $this->save_url_to_database($original_url, $short_url, $alias, $id);
                            }
                        }
                    }
                }
            } elseif (isset($response_data['ids']) && is_array($response_data['ids'])) {
                foreach ($response_data['ids'] as $index => $id) {
                    $original_url = isset($urls[$index]) ? $urls[$index] : '';
                    if ($original_url && $id) {
                        // Fetch link details for each created URL
                        $link_details = $this->get_link_details($api_key, $id);
                        
                        if ($link_details['success']) {
                            $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                            if ($short_url) {
                                $this->save_url_to_database($original_url, $short_url, $alias, $id);
                            }
                        }
                    }
                }
            } elseif (isset($response_data['data']['id'])) {
                // Single URL response in bulk mode (nested)
                $link_details = $this->get_link_details($api_key, $response_data['data']['id']);
                
                if ($link_details['success']) {
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    if ($short_url) {
                        $this->save_url_to_database($urls[0], $short_url, $alias, $response_data['data']['id']);
                    }
                }
            } elseif (isset($response_data['id'])) {
                // Single URL response in bulk mode (direct)
                $link_details = $this->get_link_details($api_key, $response_data['id']);
                
                if ($link_details['success']) {
                    $short_url = $this->construct_short_url_from_details($api_key, $link_details['data']);
                    if ($short_url) {
                        $this->save_url_to_database($urls[0], $short_url, $alias, $response_data['id']);
                    }
                }
            }
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>Bulk short URLs created successfully!</p></div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($result['message']) . '</p></div>';
            });
        }
    }
    
    /**
     * Save URL to database
     */
    private function save_url_to_database($original_url, $short_url, $alias = null, $link_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        
        // Ensure table schema is up to date before saving
        $this->ensure_table_schema();
        
        $wpdb->insert(
            $table_name,
            array(
                'original_url' => $original_url,
                'short_url' => $short_url,
                'custom_alias' => $alias,
                'link_id' => $link_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ensure table schema is up to date
     */
    private function ensure_table_schema() {
        // Check if table exists and update schema if needed
        if ($this->check_table_exists()) {
            $this->update_table_schema();
        } else {
            // Create table if it doesn't exist
            $this->create_database_table();
        }
    }
    
    /**
     * AJAX handler for deleting URLs
     */
    public function ajax_delete_url() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'elnk_pro_delete_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $url_id = intval($_POST['url_id']);
        $database_only = isset($_POST['database_only']) && $_POST['database_only'] === 'true';
        
        if (empty($url_id)) {
            wp_send_json_error('Invalid URL ID');
            return;
        }
        
        // Get URL details from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        $url_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $url_id
        ));
        
        if (!$url_data) {
            wp_send_json_error('URL not found in database');
            return;
        }
        
        // If not database-only mode, try to delete from elnk.pro API first
        if (!$database_only) {
            // Extract link ID from database or URL
            $link_id = $url_data->link_id;
            
            // If we don't have link_id stored, try to extract it from URL
            if (empty($link_id)) {
                $link_id = $this->extract_link_id_from_url($url_data->short_url);
            }
            
            if ($link_id) {
                // Try to delete from elnk.pro API
                $api_key = get_option('elnk_pro_api_key');
                if (!empty($api_key)) {
                    $api_result = $this->delete_link_from_api($api_key, $link_id);
                    
                    if (!$api_result['success']) {
                        wp_send_json_error('Failed to delete from elnk.pro: ' . $api_result['message']);
                        return;
                    }
                }
            }
        }
        
        // Delete from WordPress database
        $deleted = $wpdb->delete(
            $table_name,
            array('id' => $url_id),
            array('%d')
        );
        
        if ($deleted === false) {
            wp_send_json_error('Failed to delete from database');
            return;
        }
        
        if ($database_only) {
            wp_send_json_success('URL removed from WordPress database successfully (link remains active on elnk.pro)');
        } else {
            wp_send_json_success('URL deleted successfully from both elnk.pro and WordPress database');
        }
    }
    
    /**
     * Extract link ID from short URL
     */
    private function extract_link_id_from_url($short_url) {
        // Parse the URL to get the path
        $parsed_url = parse_url($short_url);
        
        if (isset($parsed_url['path'])) {
            // Remove leading slash and get the slug
            $slug = ltrim($parsed_url['path'], '/');
            
            // For elnk.pro URLs, we need to make an API call to find the link ID
            // We'll store the link ID in the database for future use
            return $this->find_link_id_by_slug($slug);
        }
        
        return null;
    }
    
    /**
     * Find link ID by slug using API search
     */
    private function find_link_id_by_slug($slug) {
        $api_key = get_option('elnk_pro_api_key');
        
        if (empty($api_key)) {
            return null;
        }
        
        // Try to get all links and find the one with matching slug
        $url = 'https://elnk.pro/api/links';
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code >= 400) {
            return null;
        }
        
        $response_data = json_decode($response, true);
        
        // Search through the links to find matching slug
        if (isset($response_data['data']) && is_array($response_data['data'])) {
            foreach ($response_data['data'] as $link) {
                if (isset($link['url']) && $link['url'] === $slug) {
                    return $link['id'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Delete link from elnk.pro API
     */
    private function delete_link_from_api($api_key, $link_id) {
        $url = 'https://elnk.pro/api/links/' . $link_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            return array('success' => false, 'message' => 'Curl error: ' . $curl_error);
        }
        
        if ($http_code >= 200 && $http_code < 300) {
            return array('success' => true);
        } else {
            $response_data = json_decode($response, true);
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'API request failed with status code: ' . $http_code;
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Get link details from elnk.pro API
     */
    private function get_link_details($api_key, $link_id) {
        $url = 'https://elnk.pro/api/links/' . $link_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            return array('success' => false, 'message' => 'Curl error: ' . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return array('success' => true, 'data' => $response_data['data']);
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'API request failed with status code: ' . $http_code;
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Get domain details from elnk.pro API
     */
    private function get_domain_details($api_key, $domain_id) {
        $url = 'https://elnk.pro/api/domains/' . $domain_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            return array('success' => false, 'message' => 'Curl error: ' . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return array('success' => true, 'data' => $response_data['data']);
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'API request failed with status code: ' . $http_code;
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Construct short URL from link and domain details
     */
    private function construct_short_url_from_details($api_key, $link_data) {
        // Get the URL slug from link data
        $url_slug = isset($link_data['url']) ? $link_data['url'] : null;
        $domain_id = isset($link_data['domain_id']) ? $link_data['domain_id'] : 0;
        
        if (empty($url_slug)) {
            return false;
        }
        
        // If domain_id is 0 or not set, use default elnk.pro domain
        if (empty($domain_id)) {
            return 'https://elnk.pro/' . $url_slug;
        }
        
        // Fetch domain details
        $domain_details = $this->get_domain_details($api_key, $domain_id);
        
        if ($domain_details['success']) {
            $scheme = $domain_details['data']['scheme'];
            $host = $domain_details['data']['host'];
            
            return $scheme . $host . '/' . $url_slug;
        } else {
            return 'https://elnk.pro/' . $url_slug;
        }
    }
    
    /**
     * Make API request to elnk.pro
     */
    private function make_api_request($api_key, $data) {
        $domain_id = get_option('elnk_pro_domain_id');
        $project_id = get_option('elnk_pro_project_id');
        
        // Add optional fields if available
        if (!empty($domain_id)) {
            $data['domain_id'] = $domain_id;
        }
        if (!empty($project_id)) {
            $data['project_id'] = $project_id;
        }
        
        // Add type parameter
        $data['type'] = 'link';
        
        // Remove null values
        $data = array_filter($data, function($value) {
            return $value !== null;
        });
        
        $url = 'https://elnk.pro/api/links';
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data, // Send as form data, not JSON
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
                // Remove Content-Type header to let cURL set it automatically for form data
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            return array('success' => false, 'message' => 'Curl error: ' . $curl_error);
        }
        
        $response_data = json_decode($response, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return array('success' => true, 'data' => $response_data);
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'API request failed with status code: ' . $http_code;
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Get saved URLs from database
     */
    private function get_saved_urls($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        
        // Check if table exists first
        if (!$this->check_table_exists()) {
            return array();
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Check if database table exists
     */
    private function check_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        return $table_exists == $table_name;
    }
    
    /**
     * Create database table for storing short URLs
     */
    private function create_database_table() {
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
        
        // Also update existing table structure if needed
        $this->update_table_schema();
    }
    
    /**
     * Update table schema to add missing columns
     */
    private function update_table_schema() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'elnk_pro_urls';
        
        // Check if link_id column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'link_id'");
        
        if (empty($column_exists)) {
            // Add link_id column
            $result1 = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN link_id varchar(50) DEFAULT NULL AFTER custom_alias");
            $result2 = $wpdb->query("ALTER TABLE {$table_name} ADD KEY link_id (link_id)");
        }
    }
    
    /**
     * Display settings page
     */
    public function settings_page() {
        // Create database table if requested
        if (isset($_GET['create_table'])) {
            $this->create_database_table();
            echo '<div class="notice notice-success"><p>Database table created successfully!</p></div>';
        }
        
        // Check if table exists
        $table_exists = $this->check_table_exists();
        
        $api_key = get_option('elnk_pro_api_key', '');
        $domain_id = get_option('elnk_pro_domain_id', '');
        $project_id = get_option('elnk_pro_project_id', '');
        $auto_generate = get_option('elnk_pro_auto_generate', 0);
        $auto_post_types = get_option('elnk_pro_auto_post_types', array());
        $save_to_meta = get_option('elnk_pro_save_to_meta', 0);
        $enable_shortcode = get_option('elnk_pro_enable_shortcode', 0);
        $force_create = get_option('elnk_pro_force_create_on_visit', 0);
        
        // Get all public post types
        $post_types = get_post_types(array('public' => true), 'objects');
        
        ?>
        <div class="wrap elnk-pro-admin">
            <h1>Elnk.pro API Settings</h1>
            
            <!-- Account Warning Notice -->
            <div class="notice notice-info" style="border-left-color: #2271b1;">
                <p><strong>📋 Important Notice:</strong> To use this plugin, you must have an active account with <a href="https://elnk.pro" target="_blank" style="text-decoration: none;"><strong>elnk.pro</strong></a>.</p>
                <p>
                    🔹 <strong>New to elnk.pro?</strong> <a href="https://elnk.pro/register" target="_blank" style="text-decoration: none;">Create your free account here</a><br>
                    🔹 <strong>Already have an account?</strong> <a href="https://elnk.pro/dashboard" target="_blank" style="text-decoration: none;">Access your dashboard to get your API key</a><br>
                    🔹 <strong>Need help?</strong> <a href="https://elnk.pro/account-api" target="_blank" style="text-decoration: none;">View the documentation</a>
                </p>
            </div>
            
            <?php if (!$table_exists): ?>
                <div class="notice notice-error">
                    <p><strong>Database table is missing!</strong> Click <a href="<?php echo add_query_arg('create_table', '1'); ?>">here to create it</a>.</p>
                </div>
            <?php endif; ?>
            
            <?php 
            // Show status info for auto-generation
            if ($auto_generate && !empty($auto_post_types) && !empty($api_key)): 
            ?>
                <div class="notice notice-info">
                    <p><strong>Auto-generation is active!</strong> Short URLs will be created for: <?php echo implode(', ', $auto_post_types); ?>
                    <?php if ($enable_shortcode): ?>
                        <br><strong>Copy shortcode is enabled!</strong> You can now use [elnk_pro_copy] in your content.
                    <?php endif; ?>
                    <?php if ($force_create): ?>
                        <br><strong>Force create on visit is enabled!</strong> Short URLs will be created when visitors view posts without them.
                    <?php endif; ?>
                    </p>
                </div>
            <?php elseif ($auto_generate && empty($auto_post_types)): ?>
                <div class="notice notice-warning">
                    <p><strong>Auto-generation is enabled but no post types are selected.</strong> Please select post types below.</p>
                </div>
            <?php elseif ($auto_generate && empty($api_key)): ?>
                <div class="notice notice-warning">
                    <p><strong>Auto-generation is enabled but API key is missing.</strong> Please add your API key above.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($enable_shortcode && !$auto_generate): ?>
                <div class="notice notice-warning">
                    <p><strong>Copy shortcode is enabled but auto-generation is disabled.</strong> The shortcode requires auto-generation to be enabled to work properly.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" class="elnk-pro-form">
                <?php wp_nonce_field('elnk_pro_action', 'elnk_pro_nonce'); ?>
                
                <div class="form-section">
                    <h2>API Credentials</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key">API Key *</label>
                            </th>
                            <td>
                                <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" required autocomplete="off">
                                <p class="description">Your elnk.pro API key (required)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="domain_id">Domain ID</label>
                            </th>
                            <td>
                                <input type="text" id="domain_id" name="domain_id" value="<?php echo esc_attr($domain_id); ?>" class="regular-text">
                                <p class="description">Your domain ID from elnk.pro (optional)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="project_id">Project ID</label>
                            </th>
                            <td>
                                <input type="text" id="project_id" name="project_id" value="<?php echo esc_attr($project_id); ?>" class="regular-text">
                                <p class="description">Your project ID from elnk.pro (optional)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="form-section">
                    <h2>Auto Short URL Generation</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_generate">Enable Auto Generation</label>
                            </th>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="auto_generate" name="auto_generate" value="1" <?php checked($auto_generate, 1); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <p class="description">Automatically create short URLs when posts are published</p>
                            </td>
                        </tr>
                        <tr id="post-types-row" style="<?php echo $auto_generate ? '' : 'display: none;'; ?>">
                            <th scope="row">
                                <label>Post Types</label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text">Select post types for auto generation</legend>
                                    <?php foreach ($post_types as $post_type): ?>
                                        <label>
                                            <input type="checkbox" name="auto_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $auto_post_types)); ?>>
                                            <?php echo esc_html($post_type->label); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">Select which post types should automatically generate short URLs when published</p>
                            </td>
                        </tr>
                        <tr id="save-meta-row" style="<?php echo $auto_generate ? '' : 'display: none;'; ?>">
                            <th scope="row">
                                <label for="save_to_meta">Save to Post Meta</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="save_to_meta" name="save_to_meta" value="1" <?php checked($save_to_meta, 1); ?>>
                                    Save short URL to post meta fields
                                </label>
                                <p class="description">Store short URL in post meta for easy theme/plugin access (meta keys: _elnk_pro_short_url, _elnk_pro_link_id)</p>
                            </td>
                        </tr>
                        <tr id="shortcode-row" style="<?php echo $auto_generate ? '' : 'display: none;'; ?>">
                            <th scope="row">
                                <label for="enable_shortcode">Enable Copy Shortcode</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="enable_shortcode" name="enable_shortcode" value="1" <?php checked($enable_shortcode, 1); ?>>
                                    Enable [elnk_pro_copy] shortcode for copy-to-clipboard functionality
                                </label>
                                <p class="description">Allows users to add copy buttons anywhere using shortcodes like [elnk_pro_copy style="button"]. Requires auto-generation to be enabled.</p>
                            </td>
                        </tr>
                        <tr id="force-create-row" style="<?php echo $auto_generate ? '' : 'display: none;'; ?>">
                            <th scope="row">
                                <label for="force_create_on_visit">Force Create on Visit</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="force_create_on_visit" name="force_create_on_visit" value="1" <?php checked($force_create, 1); ?>>
                                    Automatically create short URLs when visitors view posts that don't have them yet
                                </label>
                                <p class="description">Creates short URLs in the background for posts without them when users visit the post. Useful for existing content that didn't have auto-generation enabled.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="elnk_pro_save_settings" class="button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#auto_generate').change(function() {
                if ($(this).is(':checked')) {
                    $('#post-types-row, #save-meta-row, #shortcode-row, #force-create-row').show();
                } else {
                    $('#post-types-row, #save-meta-row, #shortcode-row, #force-create-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display URL creation page
     */
    public function url_creation_page() {
        $api_key = get_option('elnk_pro_api_key');
        $saved_urls = $this->get_saved_urls();
        
        ?>
        <div class="wrap elnk-pro-admin">
            <h1>Create Short URLs</h1>
            
            <?php if (empty($api_key)): ?>
                <div class="notice notice-warning">
                    <p>Please <a href="<?php echo admin_url('admin.php?page=elnk-pro-settings'); ?>">configure your API settings</a> first.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" class="elnk-pro-form">
                <?php wp_nonce_field('elnk_pro_action', 'elnk_pro_nonce'); ?>
                
                <div class="form-section">
                    <h2>Create Short URL</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="is_bulk">Bulk Mode</label>
                            </th>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="is_bulk" name="is_bulk" value="1">
                                    <span class="toggle-slider"></span>
                                </label>
                                <p class="description">Enable to create multiple short URLs at once</p>
                            </td>
                        </tr>
                        <tr id="single_url_row">
                            <th scope="row">
                                <label for="destination_url">Destination URL *</label>
                            </th>
                            <td>
                                <input type="url" id="destination_url" name="destination_url" class="regular-text" placeholder="https://example.com" required>
                                <p class="description">The URL you want to shorten</p>
                            </td>
                        </tr>
                        <tr id="multiple_urls_row" style="display: none;">
                            <th scope="row">
                                <label for="multiple_urls">Multiple URLs *</label>
                            </th>
                            <td>
                                <textarea id="multiple_urls" name="multiple_urls" rows="5" class="large-text" placeholder="https://example1.com&#10;https://example2.com&#10;https://example3.com"></textarea>
                                <p class="description">Enter one URL per line for bulk creation</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="alias">Custom Alias (Optional)</label>
                            </th>
                            <td>
                                <input type="text" id="alias" name="alias" class="regular-text" placeholder="my-custom-url" minlength="6">
                                <p class="description">Custom short URL alias (minimum 6 characters, leave empty for auto-generated)</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="elnk_pro_create_url" class="button-primary" value="Create Short URL(s)" <?php echo empty($api_key) ? 'disabled' : ''; ?>>
                </p>
            </form>
            
            <?php if (!empty($saved_urls)): ?>
                <div class="form-section">
                    <h2>Your Short URLs</h2>
                    <div class="urls-table-container">
                        <table class="widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Original URL</th>
                                    <th style="width: 25%;">Short URL</th>
                                    <th style="width: 15%;">Alias</th>
                                    <th style="width: 15%;">Created</th>
                                    <th style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_urls as $url): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url($url->original_url); ?>" target="_blank" title="<?php echo esc_attr($url->original_url); ?>">
                                                <?php echo esc_html(wp_trim_words($url->original_url, 8, '...')); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="short-url-container">
                                                <input type="text" value="<?php echo esc_attr($url->short_url); ?>" readonly class="short-url-input" onclick="this.select()">
                                                <button type="button" class="button copy-btn" data-url="<?php echo esc_attr($url->short_url); ?>">Copy</button>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html($url->custom_alias ?: '-'); ?></td>
                                        <td><?php echo esc_html(date('M j, Y', strtotime($url->created_at))); ?></td>
                                        <td>
                                            <button type="button" class="button delete-url-btn" data-url-id="<?php echo esc_attr($url->id); ?>" title="Delete URL">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
