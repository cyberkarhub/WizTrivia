<?php
/**
 * WizTrivia Game Display
 * Version: 2.0.0
 * Date: 2025-05-23 11:39:22
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Extract shortcode attributes
$topic = $atts['topic'];
$difficulty = $atts['difficulty'];
$question_count = intval($atts['count']);

// Default CSS classes
$container_class = 'wiztrivia-container';
$question_class = 'wiztrivia-question';
$options_class = 'wiztrivia-options';
$option_class = 'wiztrivia-option';
$button_class = 'wiztrivia-button';
$feedback_class = 'wiztrivia-feedback';
$next_button_class = 'wiztrivia-next-btn';
$results_class = 'wiztrivia-results';
$progress_class = 'wiztrivia-progress';
$source_class = 'wiztrivia-source';

// Try to load questions
try {
    // Check if we need to load the question generator class
    if (!class_exists('WizTrivia_Question_Generator')) {
        require_once WIZTRIVIA_PLUGIN_DIR . 'admin/partials/class-wiztrivia-question-generator.php';
    }

    // Create instance of question generator
    $question_generator = new WizTrivia_Question_Generator();
    $questions = $question_generator->get_questions();
    
    if (empty($questions)) {
        throw new Exception('No questions available. Please generate questions in the WizTrivia admin panel first.');
    }
    
    // Filter questions by topic if specified
    if (!empty($topic)) {
        $filtered_questions = array_filter($questions, function($q) use ($topic) {
            return isset($q['topic']) && strtolower($q['topic']) === strtolower($topic);
        });
        
        if (!empty($filtered_questions)) {
            $questions = array_values($filtered_questions); // Re-index array
        } else {
            // If no questions match the topic, show a friendly message
            throw new Exception('No questions available for the topic "' . esc_html($topic) . '". Please try another topic or generate questions for this topic in the admin panel.');
        }
    }
    
    // Filter questions by difficulty if specified and not 'all'
    if (!empty($difficulty) && $difficulty !== 'all') {
        $filtered_questions = array_filter($questions, function($q) use ($difficulty) {
            return isset($q['difficulty']) && $q['difficulty'] === $difficulty;
        });
        
        if (!empty($filtered_questions)) {
            $questions = array_values($filtered_questions); // Re-index array
        } else {
            // If no questions match the difficulty, show a friendly message
            throw new Exception('No questions available for the ' . esc_html($difficulty) . ' difficulty level. Please try another difficulty or generate questions for this level in the admin panel.');
        }
    }
    
    // Limit number of questions
    $questions = array_slice($questions, 0, $question_count);
    
    if (count($questions) < 1) {
        throw new Exception('Not enough questions available. Please generate more questions in the WizTrivia admin panel.');
    }
    
    // Shuffle questions
    shuffle($questions);
    
    // Prepare questions for JavaScript
    $questions_for_js = array_map(function($q) {
        // Ensure correct structure
        if (!isset($q['question']) || !isset($q['correct_answer']) || !isset($q['incorrect_answers'])) {
            return null;
        }
        
        // Ensure incorrect_answers is an array
        if (!is_array($q['incorrect_answers'])) {
            $q['incorrect_answers'] = [$q['incorrect_answers']];
        }
        
        return [
            'id' => $q['id'] ?? uniqid(),
            'question' => $q['question'],
            'correct_answer' => $q['correct_answer'],
            'incorrect_answers' => $q['incorrect_answers'],
            'difficulty' => $q['difficulty'] ?? 'medium',
            'source' => $q['source'] ?? '',
        ];
    }, $questions);
    
    // Remove any null entries (invalid questions)
    $questions_for_js = array_filter($questions_for_js);
    
    if (count($questions_for_js) < 1) {
        throw new Exception('No valid questions found. Please check the question format in the admin panel.');
    }
    
    // Output the game container
    ?>
    <div class="<?php echo esc_attr($container_class); ?>" data-questions='<?php echo esc_attr(json_encode($questions_for_js)); ?>'>
        <div class="<?php echo esc_attr($progress_class); ?>">
            <span class="wiztrivia-question-number">Question 1</span> of <span class="wiztrivia-total-questions"><?php echo count($questions_for_js); ?></span>
        </div>
        
        <div class="<?php echo esc_attr($question_class); ?>"></div>
        
        <div class="<?php echo esc_attr($options_class); ?>"></div>
        
        <div class="<?php echo esc_attr($feedback_class); ?>"></div>
        
        <div class="<?php echo esc_attr($source_class); ?>"></div>
        
        <button class="<?php echo esc_attr($next_button_class); ?>" style="display: none;">Next Question</button>
        
        <div class="<?php echo esc_attr($results_class); ?>" style="display: none;">
            <h2>Quiz Results</h2>
            <p>You scored <span class="wiztrivia-score">0</span> out of <span class="wiztrivia-total">0</span>.</p>
            <button class="wiztrivia-restart-btn">Restart Quiz</button>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    // Display user-friendly error message
    ?>
    <div class="wiztrivia-error">
        <h3>Oops! Something went wrong</h3>
        <p><?php echo esc_html($e->getMessage()); ?></p>
        <?php if (current_user_can('manage_options')): ?>
            <p class="wiztrivia-admin-message">
                <strong>Admin Note:</strong> You can fix this by visiting the 
                <a href="<?php echo esc_url(admin_url('admin.php?page=wiztrivia')); ?>">WizTrivia admin panel</a> 
                and generating questions.
            </p>
        <?php endif; ?>
    </div>
    <style>
        .wiztrivia-error {
            border: 1px solid #ffcccc;
            background-color: #fff8f8;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .wiztrivia-error h3 {
            color: #cc0000;
            margin-top: 0;
        }
        .wiztrivia-admin-message {
            background-color: #f0f0f0;
            padding: 10px;
            border-left: 4px solid #0073aa;
        }
    </style>
    <?php
    
    // Log error for debugging
    if (function_exists('wiztrivia_log')) {
        wiztrivia_log("Game Display Error: " . $e->getMessage(), "error");
    }
}