<?php
/**
 * WizTrivia Question Generator Class
 * Version: 2.0.0
 * Date: 2025-05-23 10:00:00
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

class WizTrivia_Question_Generator {
    
    /**
     * The single instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     */
    private $settings = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('wiztrivia_settings', [
            'ai_provider' => 'deepseek',
            'ai_api_key' => '',
            'website_domain' => '',
            'include_ai_knowledge' => 0,
        ]);
    }
    
    /**
     * Log information to the debug log
     */
    public function log($message, $level = 'info') {
        wiztrivia_log($message, $level);
    }
    
    /**
     * Generate questions for all difficulty levels
     */
    public function generate_questions_all_levels($topic, $source_links = '', $count_per_level = 5) {
        $this->log("Starting question generation for topic: {$topic}");
        
        // Check for API key
        if (empty($this->settings['ai_api_key'])) {
            throw new Exception('API key is required. Please add it in the plugin settings.');
        }
        
        // Define the difficulty levels
        $difficulty_levels = ['easy', 'medium', 'hard', 'advanced', 'expert'];
        
        // Get existing questions
        $existing_questions = $this->get_questions();
        
        // Generate questions for each level
        $all_new_questions = [];
        
        foreach ($difficulty_levels as $difficulty) {
            $this->log("Generating {$count_per_level} questions for {$topic} at {$difficulty} level");
            
            // Generate questions based on provider
            switch ($this->settings['ai_provider']) {
                case 'deepseek':
                    $result = $this->generate_with_deepseek(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $source_links
                    );
                    break;
                    
                case 'openai':
                    $result = $this->generate_with_openai(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $source_links
                    );
                    break;
                    
                case 'gemini':
                    $result = $this->generate_with_gemini(
                        $topic, 
                        $count_per_level, 
                        $difficulty, 
                        $source_links
                    );
                    break;
                    
                default:
                    // Default to local generation
                    $result = $this->generate_local_questions($topic, $count_per_level, $difficulty);
            }
            
            // Check for errors
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }
            
            // Add to all questions
            $all_new_questions = array_merge($all_new_questions, $result);
            $this->log("Generated " . count($result) . " questions for {$difficulty} level");
        }
        
        $this->log("Total questions generated: " . count($all_new_questions));
        
        // Save the questions
        $this->save_questions_to_file($all_new_questions);
        
        return $all_new_questions;
    }
    
    /**
     * Generate questions with DeepSeek AI
     */
    public function generate_with_deepseek($topic, $count, $difficulty, $source_links = '') {
        $this->log("Preparing DeepSeek API request for {$difficulty} level {$topic} questions");
        
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
        if (!empty($this->settings['website_domain'])) {
            $prompt .= " These questions MUST be specifically about content from the website {$this->settings['website_domain']}.";
            
            if ($this->settings['website_domain'] == 'digitrendz.blog' || strpos($source_links, 'digitrendz') !== false) {
                $prompt .= " DigitrendZ.blog is focused on digital marketing, AI technologies, growth hacking, and emerging tech trends. Questions should reflect this focus area and test readers' understanding of specific article content.";
            }
        }
        
        // Add whether to include general knowledge
        if (!$this->settings['include_ai_knowledge']) {
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
        
        $this->log("DeepSeek prompt prepared, length: " . strlen($prompt));
        
        // Make API request to DeepSeek
        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->settings['ai_api_key'],
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
            $this->log("API Error: " . $error_message, "error");
            return ['error' => 'API Error: ' . $error_message];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $this->log("API Response code: " . $response_code);
        
        if ($response_code !== 200) {
            $this->log("Non-200 response: " . wp_remote_retrieve_body($response), "error");
            return ['error' => 'DeepSeek API returned status code ' . $response_code];
        }
        
        // Parse the response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check for API errors
        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            $this->log("DeepSeek API Error: " . $error_message, "error");
            return ['error' => 'DeepSeek API Error: ' . $error_message];
        }
        
        // Try to extract questions from response
        try {
            // Get content from the response
            $content = $body['choices'][0]['message']['content'] ?? '';
            $this->log("Received content from API, length: " . strlen($content));
            
            if (empty($content)) {
                $this->log("Empty content received from API", "error");
                return ['error' => 'Empty response from DeepSeek API'];
            }
            
            // Try to find and parse the JSON in the response
            preg_match('/\[\s*{.*}\s*\]/s', $content, $matches);
            
            if (!empty($matches[0])) {
                $questions_json = $matches[0];
                $this->log("Found JSON array in response");
            } else {
                // No JSON array found, try to extract individual objects
                $this->log("No JSON array found, trying to extract individual objects");
                preg_match_all('/{.*?}/s', $content, $matches);
                if (!empty($matches[0])) {
                    $questions_json = '[' . implode(',', $matches[0]) . ']';
                    $this->log("Extracted " . count($matches[0]) . " individual JSON objects");
                } else {
                    $this->log("Failed to extract JSON from response", "error");
                    $this->log("Response content: " . $content, "debug");
                    return ['error' => 'Could not parse questions from API response.'];
                }
            }
            
            // Decode the questions
            $parsed_questions = json_decode($questions_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("JSON decode error: " . json_last_error_msg(), "error");
                return ['error' => 'Invalid JSON format: ' . json_last_error_msg()];
            }
            
            if (empty($parsed_questions) || !is_array($parsed_questions)) {
                $this->log("Parsed questions empty or not an array", "error");
                return ['error' => 'Invalid question format received from API.'];
            }
            
            $this->log("Successfully parsed " . count($parsed_questions) . " questions");
            
            // Process each question and add metadata
            $formatted_questions = [];
            foreach ($parsed_questions as $q) {
                // Skip if missing required fields
                if (empty($q['question']) || empty($q['correct_answer']) || !isset($q['incorrect_answers'])) {
                    $this->log("Skipping question with missing fields", "warning");
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
            
            $this->log("Formatted " . count($formatted_questions) . " questions");
            
            // If we couldn't parse anything, use local generation
            if (empty($formatted_questions)) {
                $this->log("No valid questions found, falling back to local generation", "warning");
                return $this->generate_local_questions($topic, $count, $difficulty);
            }
            
            return $formatted_questions;
            
        } catch (Exception $e) {
            $this->log("Exception processing API response: " . $e->getMessage(), "error");
            return ['error' => 'Failed to process questions: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate questions with OpenAI
     */
    public function generate_with_openai($topic, $count, $difficulty, $source_links = '') {
        $this->log("OpenAI generation not yet implemented, falling back to local generation");
        return $this->generate_local_questions($topic, $count, $difficulty);
    }
    
    /**
     * Generate questions with Gemini
     */
    public function generate_with_gemini($topic, $count, $difficulty, $source_links = '') {
        $this->log("Gemini generation not yet implemented, falling back to local generation");
        return $this->generate_local_questions($topic, $count, $difficulty);
    }
    
    /**
     * Generate local questions (fallback method)
     */
    public function generate_local_questions($topic, $count, $difficulty) {
        $this->log("Using local question generation for {$topic}");
        
        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $questions[] = [
                'id' => uniqid(),
                'question' => "Sample question about {$topic} ({$i + 1})?",
                'correct_answer' => "Correct answer for question {$i + 1}",
                'incorrect_answers' => [
                    "Wrong answer A for question {$i + 1}",
                    "Wrong answer B for question {$i + 1}",
                    "Wrong answer C for question {$i + 1}"
                ],
                'topic' => $topic,
                'difficulty' => $difficulty,
                'source' => 'Local generation',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $questions;
    }
    
    /**
     * Get existing questions from file
     */
    public function get_questions() {
        $questions_file = WIZTRIVIA_DATA_DIR . 'questions.json';
        
        if (file_exists($questions_file)) {
            $json_data = file_get_contents($questions_file);
            $questions = json_decode($json_data, true);
            
            if (is_array($questions)) {
                $this->log("Loaded " . count($questions) . " questions from file");
                return $questions;
            }
        }
        
        $this->log("No existing questions found or invalid format, returning empty array");
        return [];
    }
    
    /**
     * Write questions to JSON file
     * 
     * @param array $new_questions The questions to write to file
     * @return bool Whether the operation was successful
     */
    public function save_questions_to_file($new_questions) {
        $this->log("Attempting to save " . count($new_questions) . " questions to file");
        
        if (empty($new_questions)) {
            $this->log("No questions to save, skipping file write");
            return false;
        }
        
        // Get existing questions
        $existing_questions = $this->get_questions();
        
        // Merge with new questions
        $all_questions = array_merge($existing_questions, $new_questions);
        
        // Ensure the data directory exists
        if (!file_exists(WIZTRIVIA_DATA_DIR)) {
            if (!wp_mkdir_p(WIZTRIVIA_DATA_DIR)) {
                $this->log("Failed to create data directory: " . WIZTRIVIA_DATA_DIR, "error");
                return false;
            }
        }
        
        // Write to file
        $result = file_put_contents(
            WIZTRIVIA_DATA_DIR . 'questions.json', 
            json_encode($all_questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        if ($result === false) {
            $this->log("Failed to write questions to file", "error");
            return false;
        }
        
        $this->log("Successfully saved " . count($all_questions) . " questions to file");
        return true;
    }
}

/**
 * Initialize the question generator
 */
function wiztrivia_question_generator() {
    return WizTrivia_Question_Generator::get_instance();
}