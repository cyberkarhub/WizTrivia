<?php
/**
 * WizTrivia AJAX Handlers
 * Version: 2.0.0
 * Date: 2025-05-23 00:29:08
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
        
        // Get plugin settings
        $settings = get_option('wiztrivia_settings', [
            'ai_provider' => 'deepseek',
            'ai_api_key' => '',
            'website_domain' => '',
            'include_ai_knowledge' => 0, // Default to only using website content
        ]);
        
        // Check for API key
        if (empty($settings['ai_api_key'])) {
            throw new Exception('API key is required. Please add it in the plugin settings.');
        }
        
        // Define the difficulty levels
        $difficulty_levels = ['easy', 'medium', 'hard', 'advanced', 'expert'];
        
        // Get existing questions
        $existing_questions = wiztrivia_get_questions();
        
        // Generate questions for each level
        $all_new_questions = [];
        
        foreach ($difficulty_levels as $difficulty) {
            wiztrivia_log("Generating {$count_per_level} questions for {$topic} at {$difficulty} level", "info");
            
            // Generate questions based on provider
            switch ($settings['ai_provider']) {
                case 'deepseek':
                    $result = wiztrivia_generate_with_deepseek(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $settings['ai_api_key'],
                        $settings['website_domain'],
                        $source_links,
                        $settings['include_ai_knowledge']
                    );
                    break;
                    
                case 'openai':
                    $result = wiztrivia_generate_with_openai(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $settings['ai_api_key'],
                        $settings['website_domain'],
                        $source_links,
                        $settings['include_ai_knowledge']
                    );
                    break;
                    
                case 'gemini':
                    $result = wiztrivia_generate_with_gemini(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $settings['ai_api_key'],
                        $settings['website_domain'],
                        $source_links,
                        $settings['include_ai_knowledge']
                    );
                    break;
                    
                default:
                    // Default to local generation
                    $result = wiztrivia_generate_local_questions($topic, $count_per_level, $difficulty);
            }
            
            // Check for errors
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }
            
            // Add to all questions
            $all_new_questions = array_merge($all_new_questions, $result);
            wiztrivia_log("Generated " . count($result) . " questions for {$difficulty} level", "info");
        }
        
        wiztrivia_log("Total questions generated: " . count($all_new_questions), "info");
        
        // Merge with existing questions
        $all_questions = array_merge($existing_questions, $all_new_questions);
        
        // Save to file
        $data_dir = WIZTRIVIA_DATA_DIR;
        if (!file_exists($data_dir)) {
            if (!wp_mkdir_p($data_dir)) {
                throw new Exception("Failed to create data directory: {$data_dir}");
            }
        }
        
        if (!file_put_contents(WIZTRIVIA_DATA_DIR . 'questions.json', json_encode($all_questions))) {
            throw new Exception('Failed to save questions to file. Check file permissions.');
        }
        
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
 * Generate questions with DeepSeek AI - IMPROVED FOR DIGITRENDZ SPECIFIC CONTENT
 */
function wiztrivia_generate_with_deepseek($topic, $count, $difficulty, $api_key, $website_domain, $source_links, $include_ai_knowledge) {
    wiztrivia_log("Preparing DeepSeek API request for {$difficulty} level {$topic} questions", "info");
    
    // Define difficulty-specific instructions
    $difficulty_guidance = [
        'easy' => 'Create basic recall questions about fundamental concepts, definitions, and simple facts from the articles. These should test basic recognition of key terms and main ideas.',
        'medium' => 'Create application questions that require understanding concepts and applying them. Ask about relationships between concepts and how ideas connect within articles.',
        'hard' => 'Create analytical questions requiring deeper understanding. Ask about implications, analyses of viewpoints, and comprehensive understanding of complex topics covered in the articles.',
        'advanced' => 'Create evaluation questions requiring critical thinking. Ask about comparing different perspectives, evaluating strengths/weaknesses of approaches, and judging effectiveness of solutions discussed in articles.',
        'expert' => 'Create synthesis questions requiring combining multiple concepts. Ask about creating new frameworks, predicting future trends based on article content, and proposing innovative solutions to problems discussed.'
    ];
    
    // Set up the improved prompt for DeepSeek
    $prompt = "Generate {$count} specific trivia questions about {$topic} at {$difficulty} difficulty level. ";
    
    // Add difficulty-specific guidance
    $prompt .= $difficulty_guidance[$difficulty] ?? "Create appropriate questions for {$difficulty} level.";
    
    // Add website domain context
    if (!empty($website_domain)) {
        $prompt .= " These questions MUST be specifically about content from the website {$website_domain}.";
        
        if ($website_domain == 'digitrendz.blog' || strpos($source_links, 'digitrendz') !== false) {
            $prompt .= " DigitrendZ.blog is focused on digital marketing, AI technologies, growth hacking, and emerging tech trends. Questions should reflect this focus area and test readers' understanding of specific article content.";
        }
    }
    
    // Add whether to include general knowledge
    if (!$include_ai_knowledge) {
        $prompt .= " IMPORTANT: Only use the provided website and source links for information. DO NOT generate generic questions or use general knowledge. Every question must be verifiable from the provided sources.";
    } else {
        $prompt .= " While focusing on the website content, you may include some general knowledge questions related to the topic.";
    }
    
    // Add source links if provided
    if (!empty($source_links)) {
        $prompt .= " Use the following sources to create highly specific questions that test actual comprehension of these materials:\n\n{$source_links}\n\n";
        $prompt .= " Ensure questions require having read these specific articles to answer correctly.";
    }
    
    $prompt .= " For each question, provide one correct answer and three specific incorrect but plausible answers.";
    
    // Add format instructions
    $prompt .= "\n\nFormat each question as JSON like this:\n{\n  \"question\": \"Specific question from article content?\",\n  \"correct_answer\": \"Correct option\",\n  \"incorrect_answers\": [\"Wrong but plausible option 1\", \"Wrong but plausible option 2\", \"Wrong but plausible option 3\"],\n  \"source\": \"Brief mention of which article/source this came from\"\n}\n\nReturn your response ONLY as a JSON array of these question objects.";
    
    wiztrivia_log("DeepSeek prompt prepared, length: " . strlen($prompt), "info");
    
    // Make API request to DeepSeek
    $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a specialist at creating trivia questions about specific website content. You focus on making questions that test actual knowledge of the article content rather than general knowledge. Output format must be valid JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
        ]),
        'timeout' => 60,
    ]);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wiztrivia_log("API Error: " . $error_message, "error");
        return ['error' => 'API Error: ' . $error_message];
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    wiztrivia_log("API Response code: " . $response_code, "info");
    
    if ($response_code !== 200) {
        wiztrivia_log("Non-200 response: " . wp_remote_retrieve_body($response), "error");
        return ['error' => 'DeepSeek API returned status code ' . $response_code];
    }
    
    // Parse the response
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for API errors
    if (isset($body['error'])) {
        $error_message = $body['error']['message'] ?? 'Unknown API error';
        wiztrivia_log("DeepSeek API Error: " . $error_message, "error");
        return ['error' => 'DeepSeek API Error: ' . $error_message];
    }
    
    // Try to extract questions from response
    try {
        // Get content from the response
        $content = $body['choices'][0]['message']['content'] ?? '';
        wiztrivia_log("Received content from API, length: " . strlen($content), "info");
        
        if (empty($content)) {
            wiztrivia_log("Empty content received from API", "error");
            return ['error' => 'Empty response from DeepSeek API'];
        }
        
        // Try to find and parse the JSON in the response
        preg_match('/\[\s*{.*}\s*\]/s', $content, $matches);
        
        if (!empty($matches[0])) {
            $questions_json = $matches[0];
            wiztrivia_log("Found JSON array in response", "info");
        } else {
            // No JSON array found, try to extract individual objects
            wiztrivia_log("No JSON array found, trying to extract individual objects", "info");
            preg_match_all('/{.*?}/s', $content, $matches);
            if (!empty($matches[0])) {
                $questions_json = '[' . implode(',', $matches[0]) . ']';
                wiztrivia_log("Extracted " . count($matches[0]) . " individual JSON objects", "info");
            } else {
                wiztrivia_log("Failed to extract JSON from response", "error");
                wiztrivia_log("Response content: " . $content, "debug");
                return ['error' => 'Could not parse questions from API response.'];
            }
        }
        
        // Decode the questions
        $parsed_questions = json_decode($questions_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wiztrivia_log("JSON decode error: " . json_last_error_msg(), "error");
            return ['error' => 'Invalid JSON format: ' . json_last_error_msg()];
        }
        
        if (empty($parsed_questions) || !is_array($parsed_questions)) {
            wiztrivia_log("Parsed questions empty or not an array", "error");
            return ['error' => 'Invalid question format received from API.'];
        }
        
        wiztrivia_log("Successfully parsed " . count($parsed_questions) . " questions", "info");
        
        // Process each question and add metadata
        $formatted_questions = [];
        foreach ($parsed_questions as $q) {
            // Skip if missing required fields
            if (empty($q['question']) || empty($q['correct_answer']) || !isset($q['incorrect_answers'])) {
                wiztrivia_log("Skipping question with missing fields", "warning");
                continue;
            }
            
            $formatted_questions[] = [
                'id' => uniqid(),
                'question' => sanitize_text_field($q['question']),
                'correct_answer' => sanitize_text_field($q['correct_answer']),
                'incorrect_answers' => array_map('sanitize_text_field', (array)$q['incorrect_answers']),
                'topic' => sanitize_text_field($topic),
                'difficulty' => sanitize_text_field($difficulty),
                'source' => isset($q['source']) ? sanitize_text_field($q['source']) : '',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        wiztrivia_log("Formatted " . count($formatted_questions) . " questions", "info");
        
        // If we couldn't parse anything, use local generation
        if (empty($formatted_questions)) {
            wiztrivia_log("No valid questions found, falling back to local generation", "warning");
            return wiztrivia_generate_local_questions($topic, $count, $difficulty);
        }
        
        return $formatted_questions;
        
    } catch (Exception $e) {
        wiztrivia_log("Exception processing API response: " . $e->getMessage(), "error");
        return ['error' => 'Failed to process questions: ' . $e->getMessage()];
    }
}

// Other functions (openai, gemini, local generation) remain the same