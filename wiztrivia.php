<?php
/**
 * Plugin Name: WizTrivia
 * Plugin URI: https://wizconsults.com
 * Description: AI-powered trivia game with automatically generated questions
 * Version: 1.0.0
 * Author: Wizconsults.com
 * Author URI: https://wizconsults.com
 * Text Domain: wiztrivia
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Define plugin constants
define('WIZTRIVIA_VERSION', '2.0.0');
define('WIZTRIVIA_PATH', plugin_dir_path(__FILE__));
define('WIZTRIVIA_URL', plugin_dir_url(__FILE__));
define('WIZTRIVIA_BASENAME', plugin_basename(__FILE__));
define('WIZTRIVIA_DATA_DIR', WIZTRIVIA_PATH . 'data/');

// Create data directory if it doesn't exist
function wiztrivia_create_data_directory() {
    if (!file_exists(WIZTRIVIA_DATA_DIR)) {
        wp_mkdir_p(WIZTRIVIA_DATA_DIR);
        
        // Create a .htaccess file to prevent direct access
        $htaccess_content = "# Prevent directory listing\nOptions -Indexes\n\n# Prevent direct access to files\n<FilesMatch \".*\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>";
        file_put_contents(WIZTRIVIA_DATA_DIR . '.htaccess', $htaccess_content);
        
        // Create an index.php file as an additional security measure
        file_put_contents(WIZTRIVIA_DATA_DIR . 'index.php', '<?php // Silence is golden');
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'wiztrivia_activate');

function wiztrivia_activate() {
    // Create data directory
    wiztrivia_create_data_directory();
    
    // Create questions file if it doesn't exist
    if (!file_exists(WIZTRIVIA_DATA_DIR . 'questions.json')) {
        file_put_contents(WIZTRIVIA_DATA_DIR . 'questions.json', json_encode([]));
    }
    
    // IMPORTANT: No flush_rewrite_rules here - that was causing issues
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'wiztrivia_deactivate');

function wiztrivia_deactivate() {
    // Keep this empty to avoid issues
}

// Include necessary files
require_once WIZTRIVIA_PATH . 'php/functions.php';

// Add admin menu
function wiztrivia_add_admin_menu() {
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
add_action('admin_menu', 'wiztrivia_add_admin_menu');

// Admin page function
function wiztrivia_admin_page() {
    require_once WIZTRIVIA_PATH . 'php/admin.php';
}

// Enqueue scripts and styles
function wiztrivia_enqueue_scripts($hook) {
    // Only load admin scripts on plugin page
    if (is_admin() && strpos($hook, 'wiztrivia') !== false) {
        wp_enqueue_style('wiztrivia-admin-style', 
            WIZTRIVIA_URL . 'assets/css/admin.css', 
            [], WIZTRIVIA_VERSION);
        
        wp_enqueue_script('wiztrivia-admin-script', 
            WIZTRIVIA_URL . 'assets/js/admin.js', 
            ['jquery'], WIZTRIVIA_VERSION, true);
            
        wp_localize_script('wiztrivia-admin-script', 'wiztriviaAdminData', [
            'nonce' => wp_create_nonce('wiztrivia_ajax_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
}
add_action('admin_enqueue_scripts', 'wiztrivia_enqueue_scripts');

// Register shortcode
function wiztrivia_game_shortcode($atts) {
    // Enqueue game styles and scripts
    wp_enqueue_style('wiztrivia-game-style', 
        WIZTRIVIA_URL . 'assets/css/game.css', 
        [], WIZTRIVIA_VERSION);
    
    wp_enqueue_script('wiztrivia-game-script', 
        WIZTRIVIA_URL . 'assets/js/game.js', 
        ['jquery'], WIZTRIVIA_VERSION, true);
    
    // Get attributes
    $atts = shortcode_atts([
        'limit' => 10,
        'category' => '',
    ], $atts, 'wiztrivia');
    
    // Include game file and return its content
    ob_start();
    include WIZTRIVIA_PATH . 'php/game.php';
    return ob_get_clean();
}
add_shortcode('wiztrivia', 'wiztrivia_game_shortcode');

// Helper functions

/**
 * Get questions from data store
 */
function wiztrivia_get_questions() {
    $questions_file = WIZTRIVIA_DATA_DIR . 'questions.json';
    
    if (file_exists($questions_file)) {
        $questions = json_decode(file_get_contents($questions_file), true);
        return is_array($questions) ? $questions : [];
    }
    
    return [];
}

/**
 * Delete a question by ID
 */
function wiztrivia_delete_question($id) {
    $questions = wiztrivia_get_questions();
    
    foreach ($questions as $key => $question) {
        if (isset($question['id']) && $question['id'] == $id) {
            unset($questions[$key]);
            file_put_contents(WIZTRIVIA_DATA_DIR . 'questions.json', json_encode(array_values($questions)));
            return true;
        }
    }
    
    return false;
}

// AJAX handlers
require_once WIZTRIVIA_PATH . 'php/ajax-handlers.php';