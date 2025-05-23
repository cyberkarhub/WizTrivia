<?php
/**
 * WizTrivia Admin Class
 * Version: 2.2.0
 * Date: 2025-05-23 08:07:46
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

class WizTrivia_Admin {
    
    private $plugin_name;
    private $version;
    private $settings;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Load settings
        $this->settings = wiztrivia_settings();
    }
    
    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name . '-admin', plugin_dir_url(__FILE__) . '../assets/css/admin.css', array(), $this->version, 'all');
        wp_enqueue_style('wp-color-picker');
    }
    
    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name . '-admin', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery', 'wp-color-picker', 'jquery-ui-sortable'), $this->version, false);
        
        // Enable WordPress media uploader
        wp_enqueue_media();
        
        wp_localize_script($this->plugin_name . '-admin', 'wiztriviaParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wiztrivia_ajax_nonce'),
            'i18n' => array(
                'error' => __('Error', 'wiztrivia'),
                'success' => __('Success', 'wiztrivia'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wiztrivia'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected items?', 'wiztrivia'),
                'confirm_bulk_regenerate' => __('Are you sure you want to regenerate the selected items?', 'wiztrivia'),
            )
        ));
    }
    
    /**
     * Register the administration menu for this plugin.
     */
    public function add_admin_menu() {
        add_menu_page(
            'WizTrivia', 
            'WizTrivia', 
            'manage_options', 
            'wiztrivia', 
            array($this, 'display_admin_page'), 
            'dashicons-games', 
            30
        );
    }
    
    /**
     * Render the settings page for this plugin.
     */
    public function display_admin_page() {
        include_once 'partials/wiztrivia-admin-display.php';
    }
    
    /**
     * Register settings fields
     */
    public function register_settings() {
        // Registration is handled by the settings class
    }
    
    /**
     * AJAX handler for getting questions
     */
    public function ajax_get_questions() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        $questions = wiztrivia_get_questions();
        
        wp_send_json_success($questions);
    }
    
    /**
     * AJAX handler for deleting a question
     */
    public function ajax_delete_question() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        $id = sanitize_text_field($_POST['id'] ?? '');
        
        if (empty($id)) {
            wp_send_json_error('Question ID is required');
            exit;
        }
        
        $questions = wiztrivia_get_questions();
        
        // Find and remove the question
        $found = false;
        foreach ($questions as $key => $question) {
            if (isset($question['id']) && $question['id'] === $id) {
                unset($questions[$key]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            wp_send_json_error('Question not found');
            exit;
        }
        
        // Re-index the array
        $questions = array_values($questions);
        
        // Save to file
        if (!$this->save_questions($questions)) {
            wp_send_json_error('Failed to save questions to file');
            exit;
        }
        
        wp_send_json_success('Question deleted successfully');
    }
    
    /**
     * AJAX handler for bulk deleting questions
     */
    public function ajax_bulk_delete_questions() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
        
        if (empty($ids)) {
            wp_send_json_error('No questions selected');
            exit;
        }
        
        $questions = wiztrivia_get_questions();
        
        // Filter out the questions to be deleted
        $filtered_questions = [];
        foreach ($questions as $question) {
            if (!isset($question['id']) || !in_array($question['id'], $ids)) {
                $filtered_questions[] = $question;
            }
        }
        
        // Save to file
        if (!$this->save_questions($filtered_questions)) {
            wp_send_json_error('Failed to save questions to file');
            exit;
        }
        
        wp_send_json_success('Questions deleted successfully');
    }
    
    /**
     * AJAX handler for bulk regenerating questions
     */
    public function ajax_bulk_regenerate_questions() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
        
        if (empty($ids)) {
            wp_send_json_error('No questions selected');
            exit;
        }
        
        // Get all questions
        $all_questions = wiztrivia_get_questions();
        
        // Extract selected questions for regeneration
        $selected_questions = [];
        $other_questions = [];
        $regenerate_topics = [];
        
        foreach ($all_questions as $question) {
            if (isset($question['id']) && in_array($question['id'], $ids)) {
                $selected_questions[] = $question;
                // Track topics for regeneration
                if (!empty($question['topic']) && !in_array($question['topic'], $regenerate_topics)) {
                    $regenerate_topics[] = $question['topic'];
                }
            } else {
                $other_questions[] = $question;
            }
        }
        
        // Get plugin settings
        $ai_settings = $this->settings->get_setting('ai', null, []);
        
        $api_key = $ai_settings['api_key'] ?? '';
        $provider = $ai_settings['provider'] ?? 'deepseek';
        $website_domain = $ai_settings['website_domain'] ?? '';
        $include_ai_knowledge = !empty($ai_settings['include_ai_knowledge']);
        
        // Check for API key
        if (empty($api_key)) {
            wp_send_json_error('API key is required. Please add it in the plugin settings.');
            exit;
        }
        
        $new_questions = [];
        
        // Regenerate questions for each topic and difficulty
        foreach ($regenerate_topics as $topic) {
            // Group selected questions by difficulty
            $questions_by_difficulty = [];
            
            foreach ($selected_questions as $question) {
                if ($question['topic'] === $topic) {
                    $difficulty = $question['difficulty'] ?? 'medium';
                    if (!isset($questions_by_difficulty[$difficulty])) {
                        $questions_by_difficulty[$difficulty] = [];
                    }
                    $questions_by_difficulty[$difficulty][] = $question;
                }
            }
            
            // Regenerate for each difficulty
            foreach ($questions_by_difficulty as $difficulty => $questions) {
                $count = count($questions);
                
                // Get source links from the existing questions
                $source_links = '';
                foreach ($questions as $q) {
                    if (!empty($q['source'])) {
                        $source_links .= $q['source'] . "\n";
                    }
                }
                
                // Generate new questions
                $result = $this->generate_questions_with_ai(
                    $topic, 
                    $count, 
                    $difficulty, 
                    $provider,
                    $api_key,
                    $website_domain,
                    $source_links,
                    $include_ai_knowledge
                );
                
                // Check for errors
                if (isset($result['error'])) {
                    wp_send_json_error($result['error']);
                    exit;
                }
                
                // Add to new questions
                $new_questions = array_merge($new_questions, $result);
            }
        }
        
        // Merge with questions that weren't selected for regeneration
        $all_questions = array_merge($other_questions, $new_questions);
        
        // Save to file
        if (!$this->save_questions($all_questions)) {
            wp_send_json_error('Failed to save questions to file');
            exit;
        }
        
        wp_send_json_success('Questions regenerated successfully');
    }
    
    /**
     * AJAX handler for generating questions
     */
    public function ajax_generate_questions() {
        // Check nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wiztrivia_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $source_links = sanitize_textarea_field($_POST['source_links'] ?? '');
        $count = max(1, min(20, intval($_POST['count'] ?? 5)));
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            exit;
        }
        
        // Get plugin settings
        $ai_settings = $this->settings->get_setting('ai', null, []);
        
        $api_key = $ai_settings['api_key'] ?? '';
        $provider = $ai_settings['provider'] ?? 'deepseek';
        $website_domain = $ai_settings['website_domain'] ?? '';
        $include_ai_knowledge = !empty($ai_settings['include_ai_knowledge']);
        
        // Check for API key
        if (empty($api_key)) {
            wp_send_json_error('API key is required. Please add it in the plugin settings.');
            exit;
        }
        
        // Generate for each difficulty (easy, medium, hard)
        $all_new_questions = [];
        
        $difficulties = array('easy', 'medium', 'hard');
        foreach ($difficulties as $difficulty) {
            $result = $this->generate_questions_with_ai(
                $topic, 
                $count, 
                $difficulty, 
                $provider,
                $api_key,
                $website_domain,
                $source_links,
                $include_ai_knowledge
            );
            
            // Check for errors
            if (isset($result['error'])) {
                wp_send_json_error($result['error']);
                exit;
            }
            
            // Add to all questions
            $all_new_questions = array_merge($all_new_questions, $result);
        }
        
        // Get existing questions
        $existing_questions = wiztrivia_get_questions();
        
        // Merge with existing questions
        $all_questions = array_merge($existing_questions, $all_new_questions);
        
        // Save to file
        if (!$this->save_questions($all_questions)) {
            wp_send_json_error('Failed to save questions to file');
            exit;
        }
        
        wp_send_json_success('Generated ' . count($all_new_questions) . ' new questions');
    }
    
    /**
     * Generate questions with AI
     */
    private function generate_questions_with_ai($topic, $count, $difficulty, $provider, $api_key, $website_domain, $source_links, $include_ai_knowledge) {
        switch ($provider) {
            case 'deepseek':
                $result = $this->generate_with_deepseek(
                    $topic, 
                    $count, 
                    $difficulty, 
                    $api_key,
                    $website_domain,
                    $source_links,
                    $include_ai_knowledge
                );
                break;
            
            case 'openai':
                $result = $this->generate_with_openai(
                    $topic, 
                    $count, 
                    $difficulty, 
                    $api_key,
                    $website_domain,
                    $source_links,
                    $include_ai_knowledge
                );
                break;
            
            case 'gemini':
                $result = $this->generate_with_gemini(
                    $topic, 
                    $count, 
                    $difficulty, 
                    $api_key,
                    $website_domain,
                    $source_links,
                    $include_ai_knowledge
                );
                break;
            
            default:
                $result = $this->generate_local_questions($topic, $count, $difficulty);
        }
        
        return $result;
    }
    
    /**
     * Generate questions with DeepSeek API
     */
    private function generate_with_deepseek($topic, $count, $difficulty, $api_key, $website_domain, $source_links, $include_ai_knowledge) {
        // API endpoint
        $api_url = 'https://api.deepseek.com/v1/chat/completions';
        
        // Prepare the content-focused prompt
        $prompt = "Please create {$count} trivia questions about {$topic} with a {$difficulty} difficulty level.";
        
        if (!empty($source_links)) {
            $prompt .= " Use ONLY the information from these specific article URLs (one per line):\n{$source_links}\n";
            $prompt .= "Each question MUST be directly based on content from these articles, and include the source URL it came from.";
        } else if (!empty($website_domain)) {
            $prompt .= " The questions should be highly relevant to content found on {$website_domain} website.";
        }
        
        if (!$include_ai_knowledge && empty($source_links)) {
            $prompt .= " Do not use information that wouldn't be found on {$website_domain}.";
        }
        
        $prompt .= "\n\nFor each question:
1. Make sure it's at a {$difficulty} level - easy questions should be basic knowledge, medium should require some specific understanding, and hard should challenge even knowledgeable people.
2. Provide exactly 4 answer options with only one being correct.
3. Each question must include a source link to the relevant article when possible.

Format your response as a valid JSON array with the following structure for each question: id, question, correct_answer, incorrect_answers array, difficulty, topic, and source fields.

Do not include any explanations, just the JSON array. The source field should contain the URL of the exact article the question came from.";
        
        // Request data
        $request_data = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 2048
        );
        
        // API request headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        );
        
        // Make the API request
        $response = wp_remote_post($api_url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return array('error' => 'Invalid API response');
        }
        
        // Parse the response to extract JSON
        $content = $data['choices'][0]['message']['content'];
        
        // Extract JSON from the response (handling possible markdown code blocks)
        preg_match('/```(?:json)?(.*?)```/s', $content, $matches);
        $json_string = isset($matches[1]) ? trim($matches[1]) : trim($content);
        
        // Remove any non-JSON content that might be before or after
        $json_string = preg_replace('/^[^[{]*/s', '', $json_string);
        $json_string = preg_replace('/[^}\]]*$/s', '', $json_string);
        
        // Decode JSON to get questions
        $questions = json_decode($json_string, true);
        
        if (!$questions || !is_array($questions)) {
            return array('error' => 'Failed to parse questions from API response');
        }
        
        // Ensure each question has a unique ID
        foreach ($questions as &$question) {
            $question['id'] = uniqid('q_');
        }
        
        return $questions;
    }
    
    /**
     * Generate questions with OpenAI API
     */
    private function generate_with_openai($topic, $count, $difficulty, $api_key, $website_domain, $source_links, $include_ai_knowledge) {
        // API endpoint
        $api_url = 'https://api.openai.com/v1/chat/completions';
        
        // Prepare the content-focused prompt
        $prompt = "Please create {$count} trivia questions about {$topic} with a {$difficulty} difficulty level.";
        
        if (!empty($source_links)) {
            $prompt .= " Use ONLY the information from these specific article URLs (one per line):\n{$source_links}\n";
            $prompt .= "Each question MUST be directly based on content from these articles, and include the source URL it came from.";
        } else if (!empty($website_domain)) {
            $prompt .= " The questions should be highly relevant to content found on {$website_domain} website.";
        }
        
        if (!$include_ai_knowledge && empty($source_links)) {
            $prompt .= " Do not use information that wouldn't be found on {$website_domain}.";
        }
        
        $prompt .= "\n\nFor each question:
1. Make sure it's at a {$difficulty} level - easy questions should be basic knowledge, medium should require some specific understanding, and hard should challenge even knowledgeable people.
2. Provide exactly 4 answer options with only one being correct.
3. Each question must include a source link to the relevant article when possible.

Format your response as a valid JSON array with the following structure for each question: id, question, correct_answer, incorrect_answers array, difficulty, topic, and source fields.

Do not include any explanations, just the JSON array. The source field should contain the URL of the exact article the question came from.";
        
        // Request data
        $request_data = array(
            'model' => 'gpt-4',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 2048
        );
        
        // API request headers
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        );
        
        // Make the API request
        $response = wp_remote_post($api_url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['choices'][0]['message']['content'])) {
            return array('error' => 'Invalid API response');
        }
        
        // Parse the response to extract JSON
        $content = $data['choices'][0]['message']['content'];
        
        // Extract JSON from the response (handling possible markdown code blocks)
        preg_match('/```(?:json)?(.*?)```/s', $content, $matches);
        $json_string = isset($matches[1]) ? trim($matches[1]) : trim($content);
        
        // Remove any non-JSON content that might be before or after
        $json_string = preg_replace('/^[^[{]*/s', '', $json_string);
        $json_string = preg_replace('/[^}\]]*$/s', '', $json_string);
        
        // Decode JSON to get questions
        $questions = json_decode($json_string, true);
        
        if (!$questions || !is_array($questions)) {
            return array('error' => 'Failed to parse questions from API response');
        }
        
        // Ensure each question has a unique ID
        foreach ($questions as &$question) {
            $question['id'] = uniqid('q_');
        }
        
        return $questions;
    }
    
    /**
     * Generate questions with Google Gemini API
     */
    private function generate_with_gemini($topic, $count, $difficulty, $api_key, $website_domain, $source_links, $include_ai_knowledge) {
        // API endpoint
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
        
        // Prepare the content-focused prompt
        $prompt = "Please create {$count} trivia questions about {$topic} with a {$difficulty} difficulty level.";
        
        if (!empty($source_links)) {
            $prompt .= " Use ONLY the information from these specific article URLs (one per line):\n{$source_links}\n";
            $prompt .= "Each question MUST be directly based on content from these articles, and include the source URL it came from.";
        } else if (!empty($website_domain)) {
            $prompt .= " The questions should be highly relevant to content found on {$website_domain} website.";
        }
        
        if (!$include_ai_knowledge && empty($source_links)) {
            $prompt .= " Do not use information that wouldn't be found on {$website_domain}.";
        }
        
        $prompt .= "\n\nFor each question:
1. Make sure it's at a {$difficulty} level - easy questions should be basic knowledge, medium should require some specific understanding, and hard should challenge even knowledgeable people.
2. Provide exactly 4 answer options with only one being correct.
3. Each question must include a source link to the relevant article when possible.

Format your response as a valid JSON array with the following structure for each question: id, question, correct_answer, incorrect_answers array, difficulty, topic, and source fields.

Do not include any explanations, just the JSON array. The source field should contain the URL of the exact article the question came from.";
        
        // Request data
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 2048
            )
        );
        
        // API request headers
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        // Make the API request
        $response = wp_remote_post($api_url, array(
            'headers' => $headers,
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return array('error' => 'Invalid API response');
        }
        
        // Parse the response to extract JSON
        $content = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Extract JSON from the response (handling possible markdown code blocks)
        preg_match('/```(?:json)?(.*?)```/s', $content, $matches);
        $json_string = isset($matches[1]) ? trim($matches[1]) : trim($content);
        
        // Remove any non-JSON content that might be before or after
        $json_string = preg_replace('/^[^[{]*/s', '', $json_string);
        $json_string = preg_replace('/[^}\]]*$/s', '', $json_string);
        
        // Decode JSON to get questions
        $questions = json_decode($json_string, true);
        
        if (!$questions || !is_array($questions)) {
            return array('error' => 'Failed to parse questions from API response');
        }
        
        // Ensure each question has a unique ID
        foreach ($questions as &$question) {
            $question['id'] = uniqid('q_');
        }
        
        return $questions;
    }
    
    /**
     * Generate local questions (fallback method)
     */
    private function generate_local_questions($topic, $count, $difficulty) {
        $questions = [];
        
        for ($i = 0; $i < $count; $i++) {
            $questions[] = array(
                'id' => uniqid('q_'),
                'question' => "Sample {$difficulty} question about {$topic} #" . ($i + 1),
                'correct_answer' => "Correct Answer",
                'incorrect_answers' => ["Wrong Answer 1", "Wrong Answer 2", "Wrong Answer 3"],
                'difficulty' => $difficulty,
                'topic' => $topic,
                'source' => ""
            );
        }
        
        return $questions;
    }
    
    /**
     * Save questions to file
     */
    private function save_questions($questions) {
        // Ensure data directory exists
        if (!file_exists(WIZTRIVIA_DATA_DIR)) {
            wp_mkdir_p(WIZTRIVIA_DATA_DIR);
        }
        
        // Save to file
        $file_path = WIZTRIVIA_DATA_DIR . 'questions.json';
        $result = file_put_contents($file_path, json_encode($questions, JSON_PRETTY_PRINT));
        
        return $result !== false;
    }
}