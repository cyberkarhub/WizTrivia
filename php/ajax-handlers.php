<?php
/**
 * WizTrivia AJAX Handlers - Updated to use Question Generator class
 * Version: 2.0.0
 * Date: 2025-05-23
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// REMOVE THE DUPLICATE wiztrivia_log FUNCTION FROM HERE (lines 14-24)
// It's already defined in wiztrivia.php

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
        
        // Load the question generator class if it hasn't been loaded
        if (!class_exists('WizTrivia_Question_Generator')) {
            require_once WIZTRIVIA_PLUGIN_DIR . 'admin/partials/class-wiztrivia-question-generator.php';
        }
        
        // Create an instance of our question generator
        $question_generator = new WizTrivia_Question_Generator();
        
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

/**
 * Get existing questions from file
 */
function wiztrivia_get_questions() {
    // Load the question generator class if it hasn't been loaded
    if (!class_exists('WizTrivia_Question_Generator')) {
        require_once WIZTRIVIA_PLUGIN_DIR . 'admin/partials/class-wiztrivia-question-generator.php';
    }
    
    // Create an instance and get questions
    $question_generator = new WizTrivia_Question_Generator();
    return $question_generator->get_questions();
}