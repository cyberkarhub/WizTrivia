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

// Logging function - MOVED TO TOP TO AVOID CONFLICTS
if (!function_exists('wiztrivia_log')) {
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
}

// SAFE ACTIVATION HOOK - Load files only when needed
register_activation_hook(__FILE__, 'wiztrivia_activate');
function wiztrivia_activate() {
    try {
        // Create necessary directories with error handling
        if (!file_exists(WIZTRIVIA_DATA_DIR)) {
            if (!wp_mkdir_p(WIZTRIVIA_DATA_DIR)) {
                throw new Exception('Failed to create data directory: ' . WIZTRIVIA_DATA_DIR);
            }
            @chmod(WIZTRIVIA_DATA_DIR, 0755);
        }
        
        // Check if directory is writable
        if (!is_writable(WIZTRIVIA_DATA_DIR)) {
            throw new Exception('Data directory is not writable: ' . WIZTRIVIA_DATA_DIR);
        }
        
        // Set default options if they don't exist - with validation
        $default_settings = array(
            'ai_provider' => 'deepseek',
            'ai_api_key' => '',
            'website_domain' => get_site_url(),
            'include_ai_knowledge' => 0,
        );
        
        if (!get_option('wiztrivia_settings')) {
            update_option('wiztrivia_settings', $default_settings);
        }
        
        // Create initial empty questions file if it doesn't exist
        $questions_file = WIZTRIVIA_DATA_DIR . 'questions.json';
        if (!file_exists($questions_file)) {
            $initial_questions = array();
            $json_data = json_encode($initial_questions, JSON_PRETTY_PRINT);
            if (file_put_contents($questions_file, $json_data) === false) {
                throw new Exception('Failed to create initial questions file');
            }
        }
        
        wiztrivia_log('Plugin activated successfully', 'info');
        
    } catch (Exception $e) {
        wiztrivia_log('Activation error: ' . $e->getMessage(), 'error');
        
        // Deactivate the plugin and show error
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'WizTrivia Plugin Activation Error: ' . $e->getMessage() . 
            '<br><br>Please check file permissions and try again.' . 
            '<br><a href="' . admin_url('plugins.php') . '">&laquo; Return to Plugins</a>'
        );
    }
}

// DEFERRED FILE LOADING - Only load files after WordPress is fully initialized
add_action('init', 'wiztrivia_load_required_files');
function wiztrivia_load_required_files() {
    // Include required files with error checking - ONLY AFTER INIT
    $required_files = [
        'includes/class-wiztrivia-settings.php',
        'php/functions.php',
        'php/ajax-handlers.php',
        'admin/class-wiztrivia-admin.php',
        'admin/partials/class-wiztrivia-question-generator.php'
    ];

    foreach ($required_files as $file) {
        $file_path = WIZTRIVIA_PLUGIN_DIR . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            // Log missing file but don't fatal error
            wiztrivia_log("Required file missing: {$file}", 'warning');
        }
    }
}

/**
 * Register admin menu - SINGLE REGISTRATION
 */
function wiztrivia_register_admin_menu() {
    add_menu_page(
        'WizTrivia',
        'WizTrivia',
        'manage_options',
        'wiztrivia',
        'wiztrivia_admin_page',
        'dashicons-games',
        30
    );
}
add_action('admin_menu', 'wiztrivia_register_admin_menu');

/**
 * Display admin page
 */
function wiztrivia_admin_page() {
    $file_path = WIZTRIVIA_PLUGIN_DIR . 'admin/partials/wiztrivia-admin-display.php';
    if (file_exists($file_path)) {
        include_once $file_path;
    } else {
        echo '<div class="wrap"><h1>WizTrivia</h1><p>Admin display file missing: ' . esc_html($file_path) . '</p></div>';
    }
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
        'difficulty' => 'all',
        'count' => 10,
    ), $atts);
    
    ob_start();
    $game_file = WIZTRIVIA_PLUGIN_DIR . 'php/game.php';
    if (file_exists($game_file)) {
        require $game_file;
    } else {
        echo '<p>WizTrivia game file not found.</p>';
    }
    return ob_get_clean();
}
add_shortcode('wiztrivia', 'wiztrivia_shortcode');

// Deactivation hook
register_deactivation_hook(__FILE__, 'wiztrivia_deactivate');
function wiztrivia_deactivate() {
    // Cleanup code here if needed
    wiztrivia_log('Plugin deactivated', 'info');
}