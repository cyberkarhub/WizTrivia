<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

// [TIMESTAMP: 2025-05-22 23:18:57]
// [USER: cyberkarhub]

// Get questions
$questions = wiztrivia_get_questions();

// Get settings with complete defaults
$settings = get_option('wiztrivia_settings', [
    'plugin_name' => 'WizTrivia',
    'ai_api_key' => '',
    'ai_provider' => 'deepseek',
    'website_domain' => '',
    'include_ai_knowledge' => 1,
    'theme_bg_color' => '#ffffff',
    'theme_accent_color' => '#2271b1',
    'enable_levels' => 1,
    'levels' => [
        ['name' => 'Easy', 'required_score' => 50, 'article_url' => ''],
        ['name' => 'Medium', 'required_score' => 100, 'article_url' => ''],
        ['name' => 'Hard', 'required_score' => 150, 'article_url' => ''],
        ['name' => 'Advanced', 'required_score' => 200, 'article_url' => ''],
        ['name' => 'Expert', 'required_score' => 250, 'article_url' => '']
    ]
]);

// Process settings form ONLY when explicitly submitted
$settings_updated = false;
if (isset($_POST['wiztrivia_save_settings']) && check_admin_referer('wiztrivia_save_settings')) {
    $settings['plugin_name'] = sanitize_text_field($_POST['plugin_name'] ?? 'WizTrivia');
    $settings['ai_api_key'] = sanitize_text_field($_POST['ai_api_key'] ?? '');
    $settings['ai_provider'] = sanitize_text_field($_POST['ai_provider'] ?? 'deepseek');
    $settings['website_domain'] = sanitize_text_field($_POST['website_domain'] ?? '');
    $settings['include_ai_knowledge'] = isset($_POST['include_ai_knowledge']) ? 1 : 0;
    $settings['theme_bg_color'] = sanitize_hex_color($_POST['theme_bg_color'] ?? '#ffffff');
    $settings['theme_accent_color'] = sanitize_hex_color($_POST['theme_accent_color'] ?? '#2271b1');
    
    update_option('wiztrivia_settings', $settings);
    $settings_updated = true;
}

// Process levels form
if (isset($_POST['wiztrivia_save_levels']) && check_admin_referer('wiztrivia_save_levels')) {
    $settings['enable_levels'] = isset($_POST['enable_levels']) ? 1 : 0;
    
    // Process level data
    if (isset($_POST['level_name']) && is_array($_POST['level_name'])) {
        $level_names = $_POST['level_name'];
        $level_scores = $_POST['level_score'] ?? [];
        $level_articles = $_POST['level_article'] ?? [];
        
        $levels = [];
        for ($i = 0; $i < count($level_names); $i++) {
            if (!empty($level_names[$i])) {
                $levels[] = [
                    'name' => sanitize_text_field($level_names[$i]),
                    'required_score' => isset($level_scores[$i]) ? intval($level_scores[$i]) : 0,
                    'article_url' => isset($level_articles[$i]) ? esc_url_raw($level_articles[$i]) : ''
                ];
            }
        }
        
        $settings['levels'] = $levels;
    }
    
    update_option('wiztrivia_settings', $settings);
    $settings_updated = true;
}

// Process question deletion
$question_deleted = false;
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('delete_question')) {
    $id = sanitize_text_field($_GET['id']);
    if (wiztrivia_delete_question($id)) {
        $question_deleted = true;
        $questions = wiztrivia_get_questions(); // Refresh the questions
    }
}

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'setup';
?>

<div class="wrap">
    <h1><?php echo esc_html($settings['plugin_name']); ?> - AI-Powered Trivia Game</h1>
    
    <?php if ($settings_updated): ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings updated successfully!</p>
        </div>
    <?php endif; ?>
    
    <?php if ($question_deleted): ?>
        <div class="notice notice-success is-dismissible">
            <p>Question deleted successfully!</p>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=wiztrivia&tab=setup" class="nav-tab <?php echo $active_tab === 'setup' ? 'nav-tab-active' : ''; ?>">Setup</a>
        <a href="?page=wiztrivia&tab=questions" class="nav-tab <?php echo $active_tab === 'questions' ? 'nav-tab-active' : ''; ?>">Questions</a>
        <a href="?page=wiztrivia&tab=levels" class="nav-tab <?php echo $active_tab === 'levels' ? 'nav-tab-active' : ''; ?>">Level Unlocking</a>
        <a href="?page=wiztrivia&tab=theme" class="nav-tab <?php echo $active_tab === 'theme' ? 'nav-tab-active' : ''; ?>">Theme</a>
        <a href="?page=wiztrivia&tab=help" class="nav-tab <?php echo $active_tab === 'help' ? 'nav-tab-active' : ''; ?>">Help</a>
    </h2>
    
    <div class="tab-content">
        <?php if ($active_tab === 'setup'): ?>
            <!-- Setup Tab -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_nonce_field('wiztrivia_save_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="plugin_name">Plugin Name</label></th>
                                <td>
                                    <input type="text" id="plugin_name" name="plugin_name" value="<?php echo esc_attr($settings['plugin_name']); ?>" class="regular-text">
                                    <p class="description">This name will appear in the admin menu and game interface.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="website_domain">Website Domain</label></th>
                                <td>
                                    <input type="text" id="website_domain" name="website_domain" value="<?php echo esc_attr($settings['website_domain']); ?>" class="regular-text" placeholder="e.g., example.com">
                                    <p class="description">Enter your website domain to generate relevant questions (no https:// needed).</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">AI Knowledge</th>
                                <td>
                                    <label for="include_ai_knowledge">
                                        <input type="checkbox" id="include_ai_knowledge" name="include_ai_knowledge" value="1" <?php checked($settings['include_ai_knowledge'], 1); ?>>
                                        Include general AI knowledge in addition to website-specific questions
                                    </label>
                                    <p class="description">When checked, the AI will mix general knowledge with website-specific content.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="ai_provider">AI Provider</label></th>
                                <td>
                                    <select id="ai_provider" name="ai_provider" class="regular-text">
                                        <option value="deepseek" <?php selected($settings['ai_provider'], 'deepseek'); ?>>DeepSeek AI</option>
                                        <option value="openai" <?php selected($settings['ai_provider'], 'openai'); ?>>OpenAI</option>
                                        <option value="gemini" <?php selected($settings['ai_provider'], 'gemini'); ?>>Google Gemini</option>
                                    </select>
                                    <p class="description">Select which AI service to use for generating questions.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="ai_api_key">API Key</label></th>
                                <td>
                                    <input type="password" id="ai_api_key" name="ai_api_key" value="<?php echo esc_attr($settings['ai_api_key']); ?>" class="regular-text">
                                    <p class="description">Enter your <span id="api-provider-name">DeepSeek AI</span> API key for generating questions.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="wiztrivia_save_settings" class="button button-primary" value="Save Settings">
                        </p>
                    </form>
                </div>
            </div>
            
        <?php elseif ($active_tab === 'questions'): ?>
            <!-- Questions Tab -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <h3>Generate Questions</h3>
                    
                    <div class="wiztrivia-admin-form-container">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="topic">Topic:</label></th>
                                <td>
                                    <input type="text" id="topic" class="regular-text" placeholder="e.g., History, Science, Sports">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="source_links">Source Links/Content:</label></th>
                                <td>
                                    <textarea id="source_links" rows="5" class="large-text" placeholder="Paste URLs or content to use as sources for questions (one per line)"></textarea>
                                    <p class="description">Optional: Paste links or text content to use as sources for questions.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="question_count">Questions per Level:</label></th>
                                <td>
                                    <input type="number" id="question_count" class="small-text" min="5" max="10" value="5" readonly>
                                    <p class="description">Each topic has 5 levels (Easy, Medium, Hard, Advanced, Expert) with 5 questions each.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="button" id="generate-btn" class="button button-primary">Generate Questions</button>
                        </p>
                    </div>
                    
                    <!-- Generation Status (hidden by default) -->
                    <div id="status-container" style="display: none; margin-top: 20px;">
                        <h3>Generation Status</h3>
                        
                        <div style="height: 20px; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 10px;">
                            <div id="progress-bar" style="width: 0%; height: 100%; background-color: #2271b1;"></div>
                        </div>
                        
                        <div id="status-message" style="margin-bottom: 10px; font-weight: 500;">
                            Preparing to generate questions...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manage Questions -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <h3>Manage Questions</h3>
                    
                    <p>
                        You currently have <strong><?php echo count($questions); ?></strong> questions in your database.
                        <?php if (!empty($questions)): ?>
                            Use the shortcode <code>[wiztrivia]</code> to display the game on your site.
                        <?php endif; ?>
                    </p>
                    
                    <?php if (empty($questions)): ?>
                        <div class="notice notice-info">
                            <p>No questions available. Use the form above to generate some questions!</p>
                        </div>
                    <?php else: ?>
                        <h4>Filter Questions:</h4>
                        <div class="filter-controls" style="margin-bottom: 15px;">
                            <select id="filter-topic">
                                <option value="">All Topics</option>
                                <?php 
                                $topics = [];
                                foreach ($questions as $q) {
                                    if (!empty($q['topic']) && !in_array($q['topic'], $topics)) {
                                        $topics[] = $q['topic'];
                                        echo '<option value="' . esc_attr($q['topic']) . '">' . esc_html($q['topic']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            
                            <select id="filter-level">
                                <option value="">All Levels</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                            </select>
                            
                            <button type="button" id="apply-filter" class="button">Apply Filter</button>
                            <button type="button" id="reset-filter" class="button">Reset</button>
                        </div>
                    
                        <table class="wp-list-table widefat fixed striped" id="questions-table">
                            <thead>
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="55%">Question</th>
                                    <th width="15%">Topic</th>
                                    <th width="15%">Difficulty</th>
                                    <th width="10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $index => $question): ?>
                                    <tr data-topic="<?php echo esc_attr($question['topic'] ?? ''); ?>" data-level="<?php echo esc_attr(strtolower($question['difficulty'] ?? '')); ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo esc_html($question['question']); ?></td>
                                        <td><?php echo esc_html($question['topic'] ?? 'General'); ?></td>
                                        <td><?php echo esc_html(ucfirst($question['difficulty'] ?? 'Medium')); ?></td>
                                        <td>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wiztrivia&tab=questions&action=delete&id=' . $question['id']), 'delete_question'); ?>" 
                                               onclick="return confirm('Are you sure you want to delete this question?');">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($active_tab === 'levels'): ?>
            <!-- Levels Tab -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <h3>Level Unlocking & Article Links</h3>
                    <p>Configure the level system with article links for each level. Each topic has 5 difficulty levels.</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('wiztrivia_save_levels'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Level System</th>
                                <td>
                                    <label for="enable_levels">
                                        <input type="checkbox" id="enable_levels" name="enable_levels" value="1" <?php checked($settings['enable_levels'], 1); ?>>
                                        Enable level unlocking based on score
                                    </label>
                                    <p class="description">When enabled, users will unlock levels as they achieve certain scores.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="levels-container" style="<?php echo $settings['enable_levels'] ? '' : 'display:none;'; ?>">
                            <h3>Configure Levels</h3>
                            <p>Each level consists of 5 questions. Set a required score and article link for each level.</p>
                            
                            <table class="widefat" id="levels-table">
                                <thead>
                                    <tr>
                                        <th width="20%">Level Name</th>
                                        <th width="20%">Required Score</th>
                                        <th width="60%">Article URL (shown after level completion)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $default_levels = ['Easy', 'Medium', 'Hard', 'Advanced', 'Expert'];
                                    $saved_levels = $settings['levels'] ?? [];
                                    
                                    // Make sure we have exactly 5 levels
                                    while (count($saved_levels) < 5) {
                                        $index = count($saved_levels);
                                        $saved_levels[] = [
                                            'name' => $default_levels[$index] ?? 'Level ' . ($index + 1),
                                            'required_score' => ($index + 1) * 50,
                                            'article_url' => ''
                                        ];
                                    }
                                    
                                    foreach ($saved_levels as $index => $level) {
                                        if ($index < 5) { // Only show first 5 levels
                                    ?>
                                        <tr class="level-row">
                                            <td>
                                                <input type="text" name="level_name[]" value="<?php echo esc_attr($level['name']); ?>" class="regular-text" readonly>
                                            </td>
                                            <td>
                                                <input type="number" name="level_score[]" value="<?php echo intval($level['required_score']); ?>" class="small-text" min="0">
                                            </td>
                                            <td>
                                                <input type="url" name="level_article[]" value="<?php echo esc_url($level['article_url']); ?>" class="regular-text" placeholder="https://">
                                                <p class="description">Link to article shown when this level is completed</p>
                                            </td>
                                        </tr>
                                    <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                            
                            <p class="description" style="margin-top: 10px;">
                                <strong>Note:</strong> Level names are fixed (Easy, Medium, Hard, Advanced, Expert). Each level consists of 5 questions.
                            </p>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" name="wiztrivia_save_levels" class="button button-primary" value="Save Level Settings">
                        </p>
                    </form>
                </div>
            </div>

        <?php elseif ($active_tab === 'theme'): ?>
            <!-- Theme Tab -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <h3>Theme Settings</h3>
                    <p>Customize the appearance of your trivia game with custom colors.</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('wiztrivia_save_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="theme_bg_color">Background Color</label></th>
                                <td>
                                    <input type="color" id="theme_bg_color" name="theme_bg_color" value="<?php echo esc_attr($settings['theme_bg_color']); ?>">
                                    <code><?php echo esc_html($settings['theme_bg_color']); ?></code>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><label for="theme_accent_color">Accent Color</label></th>
                                <td>
                                    <input type="color" id="theme_accent_color" name="theme_accent_color" value="<?php echo esc_attr($settings['theme_accent_color']); ?>">
                                    <code><?php echo esc_html($settings['theme_accent_color']); ?></code>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Preview</th>
                                <td>
                                    <div id="theme-preview" style="background-color: <?php echo esc_attr($settings['theme_bg_color']); ?>; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); width: 300px;">
                                        <h3 style="color: <?php echo esc_attr($settings['theme_accent_color']); ?>; margin-top: 0;">Game Preview</h3>
                                        <div style="border: 1px solid <?php echo esc_attr($settings['theme_accent_color']); ?>; padding: 10px; margin-bottom: 10px; background: white;">
                                            Answer Option
                                        </div>
                                        <button style="background-color: <?php echo esc_attr($settings['theme_accent_color']); ?>; color: #fff; border: none; padding: 8px 15px; border-radius: 4px;">
                                            Next Question
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="wiztrivia_save_settings" class="button button-primary" value="Save Theme Settings">
                        </p>
                    </form>
                </div>
            </div>
            
        <?php elseif ($active_tab === 'help'): ?>
            <!-- Help Tab -->
            <div class="postbox" style="margin-top: 20px;">
                <div class="inside">
                    <h3>Using the Shortcode</h3>
                    <p>To display the game on your site, use the <code>[wiztrivia]</code> shortcode.</p>
                    
                    <h3>Optional Attributes</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><code>topic</code> - Filter questions by topic name (e.g., "Science")</li>
                        <li><code>level</code> - Show questions from a specific level (easy, medium, hard, advanced, expert)</li>
                    </ul>
                    
                    <h3>Examples</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><code>[wiztrivia topic="science"]</code> - Show science questions across all levels</li>
                        <li><code>[wiztrivia level="easy"]</code> - Show only easy questions from all topics</li>
                        <li><code>[wiztrivia topic="history" level="expert"]</code> - Show expert-level history questions</li>
                    </ul>
                    
                    <h3>Level System</h3>
                    <p>WizTrivia organizes questions into 5 difficulty levels for each topic:</p>
                    <ol style="margin-left: 20px;">
                        <li><strong>Easy</strong> - Beginner-friendly questions</li>
                        <li><strong>Medium</strong> - Moderate difficulty</li>
                        <li><strong>Hard</strong> - Challenging questions</li>
                        <li><strong>Advanced</strong> - Very challenging questions</li>
                        <li><strong>Expert</strong> - Expert-level questions</li>
                    </ol>
                    <p>Each level has 5 questions. When a user completes a level, they will see a popup with a link to an article you specify.</p>
                    
                    <h3>API Integration</h3>
                    <p>WizTrivia supports the following AI services for generating questions:</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong>DeepSeek AI</strong> - Get an API key from <a href="https://platform.deepseek.com/" target="_blank">DeepSeek Platform</a></li>
                        <li><strong>OpenAI</strong> - Get an API key from <a href="https://platform.openai.com/" target="_blank">OpenAI Platform</a></li>
                        <li><strong>Google Gemini</strong> - Get an API key from <a href="https://ai.google.dev/" target="_blank">Google AI Studio</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Update API provider name based on selection
    $('#ai_provider').on('change', function() {
        const provider = $(this).val();
        let providerName = 'DeepSeek AI';
        
        if (provider === 'openai') {
            providerName = 'OpenAI';
        } else if (provider === 'gemini') {
            providerName = 'Google Gemini';
        }
        
        $('#api-provider-name').text(providerName);
    });
    
    // Toggle levels container visibility
    $('#enable_levels').on('change', function() {
        if ($(this).is(':checked')) {
            $('#levels-container').show();
        } else {
            $('#levels-container').hide();
        }
    });
    
    // Color picker functionality
    $('#theme_bg_color, #theme_accent_color').on('input', function() {
        const bgColor = $('#theme_bg_color').val();
        const accentColor = $('#theme_accent_color').val();
        
        $('#theme-preview').css('background-color', bgColor);
        $('#theme-preview h3').css('color', accentColor);
        $('#theme-preview div').css('border-color', accentColor);
        $('#theme-preview button').css('background-color', accentColor);
    });
    
    // Generate questions button handler
    $('#generate-btn').on('click', function() {
        const topic = $('#topic').val();
        const sourceLinks = $('#source_links').val();
        
        if (!topic) {
            alert('Please enter a topic');
            return;
        }
        
        // Show status container
        $('#status-container').show();
        $('#status-message').text('Starting question generation...');
        $('#progress-bar').css('width', '5%');
        
        // AJAX call with proper error handling
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wiztrivia_generate_questions',
                security: wiztriviaAdminData.nonce,
                topic: topic,
                source_links: sourceLinks,
                count: 5 // 5 questions per level, 5 levels
            },
            success: function(response) {
                if (response.success) {
                    $('#progress-bar').css('width', '100%');
                    $('#status-message').text('Successfully generated 25 questions across 5 difficulty levels!');
                    
                    // Reload the page after 2 seconds
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    $('#status-message').text('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                $('#status-message').text('Error: Could not connect to server');
            }
        });
    });
    
    // Question filtering
    $('#apply-filter').on('click', function() {
        const topicFilter = $('#filter-topic').val();
        const levelFilter = $('#filter-level').val();
        
        $('#questions-table tbody tr').each(function() {
            const $row = $(this);
            const rowTopic = $row.data('topic');
            const rowLevel = $row.data('level');
            
            let showRow = true;
            
            if (topicFilter && rowTopic !== topicFilter) {
                showRow = false;
            }
            
            if (levelFilter && rowLevel !== levelFilter) {
                showRow = false;
            }
            
            $row.toggle(showRow);
        });
    });
    
    $('#reset-filter').on('click', function() {
        $('#filter-topic, #filter-level').val('');
        $('#questions-table tbody tr').show();
    });
    
    // Initialize API provider name
    $('#ai_provider').trigger('change');
});
</script>