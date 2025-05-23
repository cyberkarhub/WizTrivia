<?php
/**
 * Game frontend display
 * Version: 2.2.0
 * Date: 2025-05-23 07:20:00
 * User: cyberkarhubnext
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// Get settings
$settings = wiztrivia_settings()->get_all_settings();
$game_settings = $settings['game'] ?? [];
$theme_settings = $settings['theme'] ?? [];

// Get attributes
$topic_filter = sanitize_text_field($atts['topic'] ?? '');
$level_filter = sanitize_text_field($atts['level'] ?? '');

// Get questions
$all_questions = wiztrivia_get_questions();

// Get list of topics
$topics = [];
foreach ($all_questions as $question) {
    if (!empty($question['topic']) && !in_array($question['topic'], $topics)) {
        $topics[] = $question['topic'];
    }
}
sort($topics);

// Filter questions if needed
$questions = [];
if (!empty($topic_filter)) {
    foreach ($all_questions as $question) {
        if (strtolower($question['topic']) === strtolower($topic_filter)) {
            $questions[] = $question;
        }
    }
} else {
    $questions = $all_questions;
}

// Sort questions by difficulty
$difficulty_order = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'advanced' => 3, 'expert' => 4];
usort($questions, function($a, $b) use ($difficulty_order) {
    $a_diff = strtolower($a['difficulty'] ?? 'medium');
    $b_diff = strtolower($b['difficulty'] ?? 'medium');
    return ($difficulty_order[$a_diff] ?? 999) - ($difficulty_order[$b_diff] ?? 999);
});

// Encode game data for JavaScript
$game_data = [
    'settings' => [
        'timer_duration' => intval($game_settings['timer_duration'] ?? 30),
        'questions_per_level' => intval($game_settings['questions_per_level'] ?? 5),
        'correct_answers_required' => intval($game_settings['correct_answers_required'] ?? 3),
        'enable_lifelines' => !empty($game_settings['enable_lifelines']),
        'game_title' => $game_settings['game_title'] ?? 'WizTrivia Quiz',
        'sponsor_logo' => $game_settings['sponsor_logo'] ?? '',
        'sponsor_url' => $game_settings['sponsor_url'] ?? '',
    ],
    'levels' => $settings['levels'] ?? [],
    'topics' => $topics,
];

$game_data_json = wp_json_encode($game_data);
if ($game_data_json === false) {
    $game_data_json = '{}';
    error_log('WizTrivia: Error encoding game data as JSON');
}

$questions_json = wp_json_encode($questions);
if ($questions_json === false) {
    $questions_json = '[]';
    error_log('WizTrivia: Error encoding questions as JSON');
}
?>

<div class="wiztrivia-container" 
     style="--wiztrivia-bg-color: <?php echo esc_attr($theme_settings['bg_color'] ?? '#000111'); ?>; 
            --wiztrivia-accent-color: <?php echo esc_attr($theme_settings['accent_color'] ?? '#2271b1'); ?>;
            --wiztrivia-text-color: <?php echo esc_attr($theme_settings['text_color'] ?? '#ffffff'); ?>;
            --wiztrivia-question-bg: <?php echo esc_attr($theme_settings['question_bg_color'] ?? '#ffffff'); ?>;
            --wiztrivia-question-text: <?php echo esc_attr($theme_settings['question_text_color'] ?? '#000000'); ?>;
            --wiztrivia-option-bg: <?php echo esc_attr($theme_settings['option_bg_color'] ?? '#ffffff'); ?>;
            --wiztrivia-option-text: <?php echo esc_attr($theme_settings['option_text_color'] ?? '#000000'); ?>;
            --wiztrivia-correct-bg: <?php echo esc_attr($theme_settings['correct_bg_color'] ?? '#4caf50'); ?>;
            --wiztrivia-incorrect-bg: <?php echo esc_attr($theme_settings['incorrect_bg_color'] ?? '#f44336'); ?>;
            --wiztrivia-button-bg: <?php echo esc_attr($theme_settings['button_bg_color'] ?? '#2271b1'); ?>;
            --wiztrivia-button-text: <?php echo esc_attr($theme_settings['button_text_color'] ?? '#ffffff'); ?>;
            --wiztrivia-progress-bar: <?php echo esc_attr($theme_settings['progress_bar_color'] ?? '#2271b1'); ?>;
            --wiztrivia-timer-bar: <?php echo esc_attr($theme_settings['timer_bar_color'] ?? '#4caf50'); ?>;
            --wiztrivia-timer-warning: <?php echo esc_attr($theme_settings['timer_warning_color'] ?? '#ff5722'); ?>;">
    
    <!-- Welcome Screen -->
    <div id="wiztrivia-welcome-screen" class="wiztrivia-screen">
        <div class="wiztrivia-welcome-content">
            <?php if (!empty($game_settings['sponsor_logo'])): ?>
                <div class="wiztrivia-sponsor">
                    <?php if (!empty($game_settings['sponsor_url'])): ?>
                        <a href="<?php echo esc_url($game_settings['sponsor_url']); ?>" target="_blank">
                            <img src="<?php echo esc_url($game_settings['sponsor_logo']); ?>" alt="Sponsor Logo" class="wiztrivia-sponsor-logo" />
                        </a>
                    <?php else: ?>
                        <img src="<?php echo esc_url($game_settings['sponsor_logo']); ?>" alt="Sponsor Logo" class="wiztrivia-sponsor-logo" />
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h1 class="wiztrivia-title"><?php echo esc_html($game_settings['game_title'] ?? 'WizTrivia Quiz'); ?></h1>
            
            <div class="wiztrivia-welcome-message">
                <?php echo wp_kses_post($game_settings['welcome_message'] ?? 'Welcome to WizTrivia! Test your knowledge on various topics.'); ?>
            </div>
            
            <button id="wiztrivia-start-button" class="wiztrivia-button">Start Quiz</button>
        </div>
    </div>
    
    <!-- Topic Selection Screen -->
    <div id="wiztrivia-topic-screen" class="wiztrivia-screen" style="display: none;">
        <div class="wiztrivia-topic-content">
            <h2>Choose a Topic</h2>
            
            <div class="wiztrivia-topics-grid">
                <?php foreach ($topics as $topic): ?>
                    <button class="wiztrivia-topic-button" data-topic="<?php echo esc_attr($topic); ?>">
                        <?php echo esc_html($topic); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <button id="wiztrivia-back-to-welcome" class="wiztrivia-button wiztrivia-secondary-button">Back</button>
        </div>
    </div>
    
    <!-- Game Screen -->
    <div id="wiztrivia-game-screen" class="wiztrivia-screen" style="display: none;">
        <div class="wiztrivia-header">
            <h2 id="wiztrivia-game-title">WizTrivia Quiz</h2>
            <div id="wiztrivia-level" class="wiztrivia-level">Level: Easy</div>
        </div>
        
        <!-- Timer display -->
        <div class="wiztrivia-timer-container">
            <div class="wiztrivia-timer-label">Time:</div>
            <div id="wiztrivia-timer">30</div>
            <div class="wiztrivia-timer-bar-container">
                <div id="wiztrivia-timer-bar"></div>
            </div>
        </div>
        
        <!-- Question progress display -->
        <div class="wiztrivia-question-progress">
            <span id="question-progress">1/5</span>
        </div>
        <div class="wiztrivia-progress-container">
            <div id="level-progress-bar"></div>
        </div>
        
        <!-- Lifelines -->
        <?php if (!empty($game_settings['enable_lifelines'])): ?>
            <div class="wiztrivia-lifelines">
                <button id="wiztrivia-fifty-fifty" class="wiztrivia-lifeline-button">50:50</button>
            </div>
        <?php endif; ?>
        
        <div id="wiztrivia-question" class="wiztrivia-question">
            Loading question...
        </div>
        
        <div id="wiztrivia-options" class="wiztrivia-options">
            <!-- Options will be loaded via JavaScript -->
        </div>
        
        <!-- Feedback area (initially hidden) -->
        <div class="wiztrivia-feedback hidden"></div>
        
        <div class="wiztrivia-footer">
            <div id="wiztrivia-score" class="wiztrivia-score">Score: 0</div>
            <button id="wiztrivia-next" class="wiztrivia-next wiztrivia-button hidden">
                Next Question
            </button>
        </div>
    </div>
    
    <!-- Results Screen -->
    <div id="wiztrivia-results-screen" class="wiztrivia-screen" style="display: none;">
        <div class="wiztrivia-results-content">
            <h2>Quiz Completed!</h2>
            <div id="wiztrivia-final-score" class="wiztrivia-final-score"></div>
            <div id="wiztrivia-achievement" class="wiztrivia-achievement"></div>
            <div id="wiztrivia-article-recommendation" class="wiztrivia-article-recommendation"></div>
            <button id="wiztrivia-play-again" class="wiztrivia-button">Play Again</button>
            <button id="wiztrivia-home" class="wiztrivia-button wiztrivia-secondary-button">Back to Home</button>
        </div>
    </div>
</div>

<!-- Pass data via JavaScript variables -->
<script>
    // Game data and questions
    var wizTriviaGameData = <?php echo $game_data_json; ?>;
    var wizTriviaQuestions = <?php echo $questions_json; ?>;
</script>