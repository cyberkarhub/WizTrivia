<?php
/**
 * WizTrivia Settings Class
 * Version: 2.2.0
 * Date: 2025-05-23 07:08:15
 * User: cyberkarhubok
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

class WizTrivia_Settings {
    
    /**
     * The single instance of this class
     */
    private static $instance = null;
    
    /**
     * Settings options array
     */
    private $options = [];
    
    /**
     * Option name in wp_options table
     */
    private $option_name = 'wiztrivia_settings';
    
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
        // Load settings from database
        $this->load_settings();
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Load settings from database
     */
    private function load_settings() {
        $defaults = [
            'theme' => [
                'bg_color' => '#000111',
                'accent_color' => '#2271b1',
                'text_color' => '#ffffff',
                'question_bg_color' => '#ffffff',
                'question_text_color' => '#000000',
                'option_bg_color' => '#ffffff',
                'option_text_color' => '#000000',
                'correct_bg_color' => '#4caf50',
                'incorrect_bg_color' => '#f44336',
                'button_bg_color' => '#2271b1',
                'button_text_color' => '#ffffff',
                'progress_bar_color' => '#2271b1',
                'timer_bar_color' => '#4caf50',
                'timer_warning_color' => '#ff5722',
            ],
            'game' => [
                'timer_duration' => 30,
                'questions_per_level' => 5,
                'correct_answers_required' => 3,
                'enable_lifelines' => true,
                'sponsor_logo' => '',
                'sponsor_url' => '',
                'game_title' => 'WizTrivia Quiz',
                'welcome_message' => 'Welcome to WizTrivia! Test your knowledge on various topics.',
            ],
            'levels' => [
                [
                    'name' => 'Easy',
                    'required_score' => 50,
                    'article_url' => '',
                    'article_title' => '',
                ],
                [
                    'name' => 'Medium',
                    'required_score' => 100,
                    'article_url' => '',
                    'article_title' => '',
                ],
                [
                    'name' => 'Hard',
                    'required_score' => 150,
                    'article_url' => '',
                    'article_title' => '',
                ],
                [
                    'name' => 'Advanced',
                    'required_score' => 200,
                    'article_url' => '',
                    'article_title' => '',
                ],
                [
                    'name' => 'Expert',
                    'required_score' => 250,
                    'article_url' => '',
                    'article_title' => '',
                ],
            ],
            'ai' => [
                'api_key' => '',
                'provider' => 'deepseek',
                'website_domain' => 'digitrendz.blog',
                'include_ai_knowledge' => false,
                'quality_preference' => 'specific', // general, balanced, specific
            ],
        ];
        
        // Get options from database
        $saved_options = get_option($this->option_name, []);
        
        // Merge with defaults
        $this->options = $this->array_merge_recursive_distinct($defaults, $saved_options);
    }
    
    /**
     * Register settings fields
     */
    public function register_settings() {
        register_setting(
            'wiztrivia_settings_group',
            $this->option_name,
            [$this, 'validate_settings']
        );
    }
    
    /**
     * Validate settings before saving
     */
    public function validate_settings($input) {
        // If input is empty, return current settings
        if (!is_array($input)) {
            return $this->options;
        }
        
        // Merge with current settings to avoid losing tabs not being saved
        $new_input = $this->array_merge_recursive_distinct($this->options, $input);
        
        // Validate specific fields
        if (isset($new_input['theme']['bg_color'])) {
            $new_input['theme']['bg_color'] = sanitize_hex_color($new_input['theme']['bg_color']);
        }
        
        if (isset($new_input['theme']['accent_color'])) {
            $new_input['theme']['accent_color'] = sanitize_hex_color($new_input['theme']['accent_color']);
        }
        
        if (isset($new_input['game']['timer_duration'])) {
            $new_input['game']['timer_duration'] = max(10, min(120, intval($new_input['game']['timer_duration'])));
        }
        
        if (isset($new_input['game']['sponsor_logo'])) {
            $new_input['game']['sponsor_logo'] = esc_url_raw($new_input['game']['sponsor_logo']);
        }
        
        if (isset($new_input['game']['sponsor_url'])) {
            $new_input['game']['sponsor_url'] = esc_url_raw($new_input['game']['sponsor_url']);
        }
        
        if (isset($new_input['game']['game_title'])) {
            $new_input['game']['game_title'] = sanitize_text_field($new_input['game']['game_title']);
        }
        
        if (isset($new_input['game']['welcome_message'])) {
            $new_input['game']['welcome_message'] = wp_kses_post($new_input['game']['welcome_message']);
        }
        
        // Save to ensure persistence across page loads
        update_option($this->option_name, $new_input);
        
        return $new_input;
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        return $this->options;
    }
    
    /**
     * Get setting by key
     */
    public function get_setting($section, $key = null, $default = null) {
        if (isset($this->options[$section])) {
            if ($key === null) {
                return $this->options[$section];
            } elseif (isset($this->options[$section][$key])) {
                return $this->options[$section][$key];
            }
        }
        return $default;
    }
    
    /**
     * Update setting
     */
    public function update_setting($section, $key, $value) {
        if (!isset($this->options[$section])) {
            $this->options[$section] = [];
        }
        
        $this->options[$section][$key] = $value;
        update_option($this->option_name, $this->options);
        
        return true;
    }
    
    /**
     * Better array merge recursive that doesn't convert values to arrays
     */
    private function array_merge_recursive_distinct(array $array1, array $array2) {
        $merged = $array1;
        
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }
}

// Initialize settings
function wiztrivia_settings() {
    return WizTrivia_Settings::get_instance();
}