<?php
/**
 * WizTrivia AJAX Handlers
 * Version: 2.0.0
 * Date: 2025-05-23 10:00:00
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

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

// AJAX handler for generating questions
function wiztrivia_ajax_generate_questions() {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    try {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            throw new Exception('Security check failed');
        }
        
        // Get parameters
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $source_links = sanitize_textarea_field($_POST['source_links'] ?? '');
        $count_per_level = intval($_POST['count'] ?? 5); // 5 questions per level
        
        if (empty($topic)) {
            throw new Exception('Topic is required');
        }
        
        // Load the question generator class if it hasn't been loaded yet
        if (!function_exists('wiztrivia_question_generator')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wiztrivia-question-generator.php';
        }
        
        // Get the question generator instance
        $question_generator = wiztrivia_question_generator();
        
        // Generate questions for all difficulty levels
        $all_new_questions = $question_generator->generate_questions_all_levels(
            $topic, 
            $source_links, 
            $count_per_level
        );
        
        // Clear any unexpected output
        ob_end_clean();
        
        // Return success
        wp_send_json_success('Generated ' . count($all_new_questions) . ' questions for topic: ' . $topic);
        
    } catch (Exception $e) {
        // Capture any unexpected output
        $output = ob_get_clean();
        
        // Log the error for debugging
        wiztrivia_log("Error: " . $e->getMessage(), "error");
        if (!empty($output)) {
            wiztrivia_log("Unexpected output: " . $output, "error");
        }
        
        // Return error
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_wiztrivia_generate_questions', 'wiztrivia_ajax_generate_questions');

// Function to retrieve questions
function wiztrivia_get_questions() {
    // Load the question generator class if it hasn't been loaded yet
    if (!function_exists('wiztrivia_question_generator')) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wiztrivia-question-generator.php';
    }
    
    // Get the question generator instance and return questions
    return wiztrivia_question_generator()->get_questions();
}