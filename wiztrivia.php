<?php
/**
 * Plugin Name: WizTrivia
 * Plugin URI: https://github.com/cyberkarhub/WizTrivia
 * Description: AI-powered trivia game plugin for WordPress
 * Version: 2.0.0
 * Author: CyberKarHub
 * Author URI: https://github.com/cyberkarhub
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wiztrivia
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Define plugin constants
define('WIZTRIVIA_VERSION', '2.0.0');
define('WIZTRIVIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIZTRIVIA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WIZTRIVIA_DATA_DIR', WIZTRIVIA_PLUGIN_DIR . 'data/');
define('WIZTRIVIA_ADMIN_URL', admin_url('admin.php?page=wiztrivia'));

// Include required files
require_once WIZTRIVIA_PLUGIN_DIR . 'php/functions.php';
require_once WIZTRIVIA_PLUGIN_DIR . 'php/ajax-handlers.php';
require_once WIZTRIVIA_PLUGIN_DIR . 'admin/class-wiztrivia-admin.php';
require_once WIZTRIVIA_PLUGIN_DIR . 'admin/partials/class-wiztrivia-question-generator.php';

// Admin setup
function wiztrivia_admin_setup() {
    // Create admin page
    add_menu_page(
        'WizTrivia', 
        'WizTrivia', 
        'manage_options', 
        'wiztrivia', 
        'wiztrivia_admin_display', 
        'dashicons-games', 
        30
    );
}
add_action('admin_menu', 'wiztrivia_admin_setup');

// Admin display function
function wiztrivia_admin_display() {
    require_once WIZTRIVIA_PLUGIN_DIR . 'admin/partials/wiztrivia-admin-display.php';
}

// Register admin scripts
function wiztrivia_admin_scripts($hook) {
    if ('toplevel_page_wiztrivia' !== $hook) {
        return;
    }
    
    wp_enqueue_style('wiztrivia-admin-css', WIZTRIVIA_PLUGIN_URL . 'assets/css/admin.css', array(), WIZTRIVIA_VERSION);
    wp_enqueue_script('wiztrivia-admin-js', WIZTRIVIA_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WIZTRIVIA_VERSION, true);
    
    wp_localize_script('wiztrivia-admin-js', 'wiztrivia_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wiztrivia_ajax_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'wiztrivia_admin_scripts');

// Register frontend scripts
function wiztrivia_enqueue_scripts() {
    wp_enqueue_style('wiztrivia-css', WIZTRIVIA_PLUGIN_URL . 'assets/css/game.css', array(), WIZTRIVIA_VERSION);
    wp_enqueue_script('wiztrivia-js', WIZTRIVIA_PLUGIN_URL . 'assets/js/game.js', array('jquery'), WIZTRIVIA_VERSION, true);
    
    wp_localize_script('wiztrivia-js', 'wiztrivia_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wiztrivia_game_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'wiztrivia_enqueue_scripts');

// Register shortcode
function wiztrivia_shortcode($atts) {
    $atts = shortcode_atts(array(
        'topic' => '',
        'difficulty' => 'all', // 'all', 'easy', 'medium', 'hard', 'advanced', 'expert'
        'count' => 10,
    ), $atts);
    
    ob_start();
    require WIZTRIVIA_PLUGIN_DIR . 'php/game.php';
    return ob_get_clean();
}
add_shortcode('wiztrivia', 'wiztrivia_shortcode');

// Activation hook
register_activation_hook(__FILE__, 'wiztrivia_activate');
function wiztrivia_activate() {
    // Create necessary directories
    if (!file_exists(WIZTRIVIA_DATA_DIR)) {
        wp_mkdir_p(WIZTRIVIA_DATA_DIR);
        @chmod(WIZTRIVIA_DATA_DIR, 0755);
    }
    
    // Set default options if they don't exist
    if (!get_option('wiztrivia_settings')) {
        update_option('wiztrivia_settings', array(
            'ai_provider' => 'deepseek',
            'ai_api_key' => '',
            'website_domain' => get_site_url(),
            'include_ai_knowledge' => 0,
        ));
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wiztrivia_deactivate');
function wiztrivia_deactivate() {
    // Cleanup code here if needed
}

// Logging function
function wiztrivia_log($message, $level = 'info') {
    // Only log if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        // Format the message
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] WizTrivia: {$message}" . PHP_EOL;
        
        // Log to debug.log
        error_log($formatted_message);
    }
}