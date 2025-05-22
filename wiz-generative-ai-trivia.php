<?php
/**
 * Plugin Name:       Wiz Generative AI Trivia
 * Plugin URI:        https://www.wizconsults.com/
 * Description:       A trivia game allowing users to generate questions using various AI providers via background processing.
 * Version:           1.2.7
 * Author:            Wiz Consults
 * Author URI:        https://www.wizconsults.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wiz-trivia
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --- DEFINE CONSTANTS ---
define( 'WIZ_TRIVIA_VERSION', '1.2.7' ); // Incremented version
define( 'WIZ_TRIVIA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WIZ_TRIVIA_URL', plugin_dir_url( __FILE__ ) );
define( 'WIZ_TRIVIA_DATA_DIR', WIZ_TRIVIA_PATH . 'assets/data/' );
define( 'WIZ_TRIVIA_DATA_FILE', WIZ_TRIVIA_DATA_DIR . 'triviaData.json' );
define( 'WIZ_TRIVIA_DATA_URL', WIZ_TRIVIA_URL . 'assets/data/triviaData.json' );
define( 'WIZ_TRIVIA_SETTINGS_OPTION_NAME', 'wiz_trivia_game_display_settings' );
define( 'WIZ_TRIVIA_AI_API_KEY_OPTION_NAME', 'wiz_trivia_ai_api_key' );
define( 'WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME', 'wiz_trivia_selected_ai_provider' );
define( 'WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME', 'wiz_trivia_primary_blog_domain' );
define( 'WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME', 'wiz_trivia_allow_external_sources' );
define( 'WIZ_TRIVIA_JOB_QUEUE_TRANSIENT', 'wiz_trivia_job_queue' );
define( 'WIZ_TRIVIA_PROGRESS_TRANSIENT', 'wiztrivia_question_gen_progress'); // New constant for progress transient
define( 'WIZ_TRIVIA_CRON_HOOK', 'wiz_trivia_process_generation_queue' );
define( 'WIZ_TRIVIA_CRON_INTERVAL_NAME', 'wiz_trivia_five_minutes' );


// --- FRONTEND GAME SHORTCODE ---
function wiz_trivia_game_shortcode_handler( $atts ) {
    $fallback_logo_url = 'https://digitrendz.blog/wp-content/uploads/2025/05/digitrendz-New-Logo-4a0538.svg';
    $game_display_settings = get_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME, array( 'game_title' => 'Generative AI Trivia', 'game_logo_url' => $fallback_logo_url ));
    $display_game_title = !empty($game_display_settings['game_title']) ? $game_display_settings['game_title'] : 'Generative AI Trivia';
    $display_logo_url = !empty($game_display_settings['game_logo_url']) ? $game_display_settings['game_logo_url'] : $fallback_logo_url;
    $title_parts = explode(' ', $display_game_title, 2);
    $game_title_orange_part = $title_parts[0];
    $game_title_rest_part = isset($title_parts[1]) ? $title_parts[1] : '';
    if (str_word_count($display_game_title) <= 1 && !empty($display_game_title)) {
        $game_title_orange_part = $display_game_title; $game_title_rest_part = '';
    } elseif (empty($display_game_title)) {
        $game_title_orange_part = 'Generative AI'; $game_title_rest_part = 'Trivia';
    }
    wp_enqueue_style( 'wiz-trivia-google-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Courier+New&display=swap', array(), null );
    wp_enqueue_style( 'wiz-trivia-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), null );
    wp_enqueue_style( 'wiz-trivia-game-styles', WIZ_TRIVIA_URL . 'assets/css/game-styles.css', array(), WIZ_TRIVIA_VERSION );
    wp_enqueue_script( 'wiz-trivia-game-js', WIZ_TRIVIA_URL . 'assets/js/game.js', array('jquery'), WIZ_TRIVIA_VERSION, true );
    wp_localize_script( 'wiz-trivia-game-js', 'triviaGameSettings', array(
        'jsonDataUrl' => WIZ_TRIVIA_DATA_URL . '?v=' . time(), // Cache buster for JSON
        'gameLogoUrl' => esc_url($display_logo_url)
    ) );
    ob_start(); ?>
    <div class="trivia-container-wrapper">
        <div class="trivia-container">
             <div class="game-header hidden" id="game-header">
                <div class="header-top-row">
                    <div class="game-title-area">
                        <div class="game-title">
                           <?php if (!empty($game_title_orange_part)): ?><span class="orange-text"><?php echo esc_html($game_title_orange_part); ?></span><?php endif; ?>
                           <?php if (!empty($game_title_rest_part)): ?> <?php echo esc_html($game_title_rest_part); ?><?php endif; ?>
                           <?php if (empty($game_title_orange_part) && empty($game_title_rest_part)) echo "Trivia Game"; // Fallback if title completely empty ?>
                        </div>
                        <div class="signature">By <a href="https://www.wizconsults.com" target="_blank">Wiz Consults</a></div>
                    </div>
                    <div class="header-buttons">
                        <button id="home-button"><i class="fas fa-home"></i> Home</button>
                        <button id="exit-button"><i class="fas fa-sign-out-alt"></i> Exit</button>
                    </div>
                </div>
                <div class="header-info-bar">
                    <div class="header-info-top">
                        <div class="level-display"><span class="level-label">Level:</span> <span class="level-number" id="level-number">1</span></div>
                        <div class="score-display"><span class="score-label">Score:</span> <span class="score-number" id="score-number">0/0</span></div>
                    </div>
                    <div class="header-info-bottom">
                        <div class="question-progress-display" id="question-progress-display">Q: <span class="question-progress-number">0/0</span></div>
                        <button id="lifeline-5050">50/50 <i class="fas fa-adjust"></i></button>
                    </div>
                    <div id="level-progress-bar-container"><div id="level-progress-bar"></div></div>
                </div>
                <div id="timer-display">00:20</div>
            </div>

            <div class="main-content" id="preload-screen">
                <div class="preload-content-wrapper">
                    <img src="<?php echo esc_url( $display_logo_url ); ?>" alt="Game Logo" id="topic-selection-logo" class="hidden">
                    <h1 class="game-title">
                         <?php if (!empty($game_title_orange_part)): ?><span class="orange-text"><?php echo esc_html($game_title_orange_part); ?></span><?php endif; ?>
                         <?php if (!empty($game_title_rest_part)): ?> <?php echo esc_html($game_title_rest_part); ?><?php endif; ?>
                         <?php if (empty($game_title_orange_part) && empty($game_title_rest_part)) echo "Trivia Game"; ?>
                    </h1>
                    <p id="welcome-paragraph">Test your knowledge on key topics in Generative AI and Digital Marketing, with questions inspired by the articles on the <a href="https://www.digitrendz.blog" target="_blank" class="digitrendz-link-style">DigitrendZ blog</a>.</p>
                    <button id="start-game-button">Start Game</button>
                    <h3 id="choose-topic-heading" class="hidden">Choose a Topic:</h3>
                    <div class="options-container hidden" id="topic-options-preload">
                        <!-- Topic buttons will be loaded by JS -->
                    </div>
                </div>
                <div class="signature">By <a href="https://www.wizconsults.com" target="_blank">Wiz Consults</a></div>
            </div>

            <div class="main-content hidden" id="game-area">
                <div id="question-area">
                    <div class="question-text" id="question"></div>
                    <div class="options-container" id="options">
                        <!-- Option buttons will be loaded by JS -->
                    </div>
                </div>
            </div>

            <div class="feedback-footer hidden" id="feedback-footer">
                 <div class="footer-content-limiter">
                    <div class="feedback-text" id="feedback"></div>
                    <button class="next-button hidden" id="main-next-button">Next Question</button>
                </div>
            </div>

            <div class="game-footer hidden" id="game-footer">
                <div class="footer-content-limiter">
                    <div id="end-game-area" class="end-game-area">
                        <div class="question-text">Trivia Complete!</div>
                        <div class="score-display" id="final-score"></div>
                        <button class="next-button" id="restart-button">Play Again</button>
                    </div>
                </div>
            </div>
        </div> <!-- end .trivia-container -->

        <div id="message-box" class="message-box hidden">
            <!-- Load Message -->
            <div class="load-message-content modal-pane">
                <p id="load-message-text"></p>
                <div class="button-container">
                    <button id="close-load-message-button">OK</button>
                </div>
            </div>
            <!-- Exit Confirmation -->
            <div class="exit-message-content modal-pane">
                <p id="exit-message-text"></p>
                <div class="button-container">
                    <button id="save-exit-button">Save and Exit</button>
                    <button id="exit-without-save-button">Exit Without Saving</button>
                    <button id="cancel-exit-button">Cancel</button>
                </div>
            </div>
            <!-- Level Complete -->
            <div class="level-complete-message-content modal-pane">
                <h3 id="level-complete-title"></h3>
                <p id="level-complete-text"></p>
                <div class="button-container">
                    <button id="continue-button">Continue</button>
                </div>
            </div>
            <!-- Home Confirmation -->
            <div class="home-message-content modal-pane">
                <p id="home-message-text"></p>
                <div class="button-container">
                    <button id="confirm-home-button">Yes, Go Home</button>
                    <button id="cancel-home-button">Cancel</button>
                </div>
            </div>
            <!-- New Level Unlocked -->
            <div class="new-level-message-content modal-pane">
                <h3 id="new-level-title">New Level Unlocked!</h3>
                <p id="new-level-main-text">Prepare for the next set of challenges!</p>
                <p id="new-level-article-suggestion" class="article-suggestion"></p>
                <a href="#" id="new-level-article-link" target="_blank" class="source-link hidden">Read Article</a>
                <div class="button-container">
                    <button id="new-level-continue-button">Continue</button>
                </div>
            </div>
             <!-- Retry Level -->
            <div class="retry-level-message-content modal-pane">
                <h3 id="retry-level-title">Not Quite There!</h3>
                <p id="retry-level-text"></p>
                <div class="button-container">
                    <button id="retry-level-button">Retry Level</button>
                    <button id="retry-menu-button">Main Menu</button>
                </div>
            </div>
            <!-- Generic Info -->
            <div class="generic-info-content modal-pane">
                 <p id="generic-info-text"></p>
                 <div class="button-container">
                    <button id="generic-info-ok-button">OK</button>
                </div>
            </div>
        </div> <!-- end .message-box -->
    </div> <!-- end .trivia-container-wrapper -->
    <?php
    return ob_get_clean();
}
add_shortcode( 'wiz_trivia_game', 'wiz_trivia_game_shortcode_handler' );


// --- ADMIN PAGE REGISTRATION AND SCRIPTS ---
function wiz_trivia_admin_menu() {
    add_menu_page(
        __( 'Trivia Game Admin', 'wiz-trivia' ),
        __( 'Trivia Admin', 'wiz-trivia' ),
        'manage_options',
        'wiz-trivia-admin',
        'wiz_trivia_admin_page_html_callback',
        'dashicons-editor-help',
        80
    );
}
add_action( 'admin_menu', 'wiz_trivia_admin_menu' );

function wiz_trivia_admin_enqueue_assets($hook_suffix) {
    $plugin_admin_page_hook = 'toplevel_page_wiz-trivia-admin';
    if ($hook_suffix != $plugin_admin_page_hook) {
        return;
    }
    wp_enqueue_style( 'wiz-trivia-google-font-admin', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap', array(), null );
    wp_enqueue_style( 'wiz-trivia-fontawesome-admin', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), null );
    wp_enqueue_style( 'wiz-trivia-admin-styles', WIZ_TRIVIA_URL . 'assets/css/admin-styles.css', array(), WIZ_TRIVIA_VERSION );
    wp_enqueue_media();
    wp_enqueue_script( 'wiz-trivia-admin-js', WIZ_TRIVIA_URL . 'assets/js/admin.js', array('jquery', 'media-editor'), WIZ_TRIVIA_VERSION, true );

    $decoded_trivia_data = new stdClass();
    if ( file_exists( WIZ_TRIVIA_DATA_FILE ) ) {
        $file_content = file_get_contents( WIZ_TRIVIA_DATA_FILE );
        if (!empty($file_content) && is_string($file_content)) {
            $decoded_check = json_decode($file_content);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded_trivia_data = $decoded_check;
            } else {
                error_log("Wiz Trivia Admin: Invalid JSON in " . WIZ_TRIVIA_DATA_FILE . ". Error: " . json_last_error_msg());
            }
        }
    }
    wp_localize_script( 'wiz-trivia-admin-js', 'triviaAdminSettings', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'wiz_trivia_admin_nonce' ),
        'generateNonce' => wp_create_nonce( 'wiz_trivia_generate_nonce'),
        'initialData' => $decoded_trivia_data,
        'restApiUrl' => esc_url_raw( rest_url() ), // For robust JS API calls if needed later
    ));
}
add_action( 'admin_enqueue_scripts', 'wiz_trivia_admin_enqueue_assets' );

// --- ADMIN PAGE HTML Callback Function ---
function wiz_trivia_admin_page_html_callback() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    if ( isset( $_POST['wiz_trivia_save_display_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['wiz_trivia_save_display_settings_nonce'])), 'wiz_trivia_save_display_settings_action' ) ) {
        $game_title_setting = isset( $_POST['wiz_trivia_game_title'] ) ? sanitize_text_field( wp_unslash($_POST['wiz_trivia_game_title']) ) : 'Generative AI Trivia';
        $game_logo_url_setting = isset( $_POST['wiz_trivia_game_logo_url'] ) ? esc_url_raw( trim(wp_unslash($_POST['wiz_trivia_game_logo_url'])) ) : '';
        update_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME, array( 'game_title' => $game_title_setting, 'game_logo_url' => $game_logo_url_setting ) );
        add_settings_error('wiz_trivia_messages', 'wiz_trivia_display_settings_saved', __('Game display settings saved!', 'wiz-trivia'), 'updated');
    }
    settings_errors('wiz_trivia_messages');
    $fallback_logo_url_admin = 'https://digitrendz.blog/wp-content/uploads/2025/05/digitrendz-New-Logo-4a0538.svg';
    $current_display_settings = get_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME, array( 'game_title' => 'Generative AI Trivia', 'game_logo_url' => $fallback_logo_url_admin ) );
    $current_game_title = !empty($current_display_settings['game_title']) ? $current_display_settings['game_title'] : 'Generative AI Trivia';
    $current_game_logo_url = !empty($current_display_settings['game_logo_url']) ? $current_display_settings['game_logo_url'] : $fallback_logo_url_admin;
    $current_ai_api_key = get_option( WIZ_TRIVIA_AI_API_KEY_OPTION_NAME, '' );
    $current_selected_ai_provider = get_option( WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME, 'openai' );
    $current_primary_blog_domain = get_option( WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME, 'digitrendz.blog' );
    $current_allow_external_sources = get_option( WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME, false );
    echo '<link rel="stylesheet" href="https://cdn.tailwindcss.com" />';
    ?>
    <div class="wrap">
        <div class="admin-container max-w-6xl mx-auto" style="margin-top: 20px;">
            <div class="admin-header text-2xl md:text-3xl">
                <?php echo esc_html( $current_game_title ); ?> - <span class="orange-text">Admin Panel</span>
            </div>
            <div class="my-8 p-6 bg-gray-800 rounded-lg shadow-md border border-purple-700">
                <h2 class="text-xl font-semibold text-white mb-4">Game Display Settings</h2>
                <form method="POST" action="">
                    <?php wp_nonce_field( 'wiz_trivia_save_display_settings_action', 'wiz_trivia_save_display_settings_nonce' ); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_game_title">Game Title</label></th>
                            <td><input type="text" id="wiz_trivia_game_title" name="wiz_trivia_game_title" value="<?php echo esc_attr( $current_game_title ); ?>" class="regular-text" />
                                <p class="description">This title appears on the game screen...</p></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_game_logo_url">Game Logo</label></th>
                            <td>
                                <input type="text" id="wiz_trivia_game_logo_url" name="wiz_trivia_game_logo_url" value="<?php echo esc_attr( $current_game_logo_url ); ?>" class="regular-text wiz-trivia-logo-url-field" placeholder="Image URL" />
                                <button type="button" class="button wiz-trivia-upload-logo-button">Upload/Select Logo</button>
                                <button type="button" class="button wiz-trivia-remove-logo-button" style="<?php echo (empty($current_game_logo_url) || $current_game_logo_url === $fallback_logo_url_admin) ? 'display:none;' : 'display:inline-block;'; ?>">Remove Logo</button>
                                <p class="description">Upload or select a logo...</p>
                                <div class="wiz-trivia-logo-preview" style="margin-top:10px; background: #555; padding:10px; border-radius:4px; display:inline-block; min-height:50px; vertical-align: top;">
                                    <?php if ( !empty($current_game_logo_url) && filter_var($current_game_logo_url, FILTER_VALIDATE_URL)): ?>
                                        <img src="<?php echo esc_url($current_game_logo_url); ?>" alt="Current Game Logo" style="max-width: 250px; max-height: 100px; display:block;">
                                    <?php else: ?>
                                        <span class="no-logo-text" style="color:#ccc;">No custom logo selected...</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save Display Settings' ); ?>
                </form>
            </div>
            <div class="my-8 p-6 bg-gray-800 rounded-lg shadow-md border border-green-700">
                <h2 class="text-xl font-semibold text-white mb-4">AI-Powered Question Generation & Settings</h2>
                <form id="aiGenerationForm">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_ai_provider">AI Provider</label></th>
                            <td>
                                <select id="wiz_trivia_ai_provider" name="wiz_trivia_ai_provider">
                                    <option value="openai" <?php selected($current_selected_ai_provider, 'openai'); ?>>OpenAI</option>
                                    <option value="gemini" <?php selected($current_selected_ai_provider, 'gemini'); ?>>Google Gemini</option>
                                    <option value="deepseek" <?php selected($current_selected_ai_provider, 'deepseek'); ?>>DeepSeek</option>
                                </select>
                                <p class="description">Select your AI provider.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_ai_api_key">AI API Key</label></th>
                            <td>
                                <input type="password" id="wiz_trivia_ai_api_key" name="wiz_trivia_ai_api_key" value="<?php echo esc_attr( $current_ai_api_key ); ?>" class="regular-text" placeholder="Enter your AI API Key" />
                                <p class="description">Your API Key for the selected provider.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_primary_blog_domain">Primary Blog Domain (for Source Links)</label></th>
                            <td>
                                <input type="text" id="wiz_trivia_primary_blog_domain" name="wiz_trivia_primary_blog_domain" value="<?php echo esc_attr( $current_primary_blog_domain ); ?>" class="regular-text" placeholder="e.g., digitrendz.blog" />
                                <p class="description">If external links are disallowed, AI will try to use this domain if no specific user URLs are provided for a topic. Leave blank if not applicable.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_allow_external_sources">Source Link Preference</label></th>
                            <td>
                                <label><input type="checkbox" id="wiz_trivia_allow_external_sources" name="wiz_trivia_allow_external_sources" value="1" <?php checked( $current_allow_external_sources, true ); ?> /> Allow AI to suggest source links from outside my provided URLs or the Primary Blog Domain?</label>
                                <p class="description">If unchecked, AI will be restricted to your provided URLs or the Primary Blog Domain for source links.</p>
                            </td>
                        </tr>
                        <tr><td colspan="2"><hr class="my-4 border-gray-700"></td></tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_1">Topic 1 for Generation</label></th>
                            <td><input type="text" id="wiz_trivia_topic_1" name="wiz_trivia_topic_1" class="regular-text" placeholder="e.g., Generative AI History" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_1_source_urls">Source URLs for Topic 1 (Optional)</label></th>
                            <td>
                                <textarea id="wiz_trivia_topic_1_source_urls" name="wiz_trivia_topic_1_source_urls" rows="3" class="large-text code" placeholder="Enter URLs, one per line..."></textarea>
                                <p class="description">Web pages for AI knowledge base for Topic 1.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_2">Topic 2 for Generation (Optional)</label></th>
                            <td><input type="text" id="wiz_trivia_topic_2" name="wiz_trivia_topic_2" class="regular-text" placeholder="e.g., AI Ethics" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_2_source_urls">Source URLs for Topic 2 (Optional)</label></th>
                            <td>
                                <textarea id="wiz_trivia_topic_2_source_urls" name="wiz_trivia_topic_2_source_urls" rows="3" class="large-text code" placeholder="Enter URLs, one per line"></textarea>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_3">Topic 3 for Generation (Optional)</label></th>
                            <td><input type="text" id="wiz_trivia_topic_3" name="wiz_trivia_topic_3" class="regular-text" placeholder="e.g., Future of LLMs" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="wiz_trivia_topic_3_source_urls">Source URLs for Topic 3 (Optional)</label></th>
                            <td>
                                <textarea id="wiz_trivia_topic_3_source_urls" name="wiz_trivia_topic_3_source_urls" rows="3" class="large-text code" placeholder="Enter URLs, one per line"></textarea>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Knowledge Source Preference for Generation</th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="wiz_trivia_knowledge_source" value="ai_only" checked="checked"> AI's General Knowledge Only</label><br>
                                    <label><input type="radio" name="wiz_trivia_knowledge_source" value="user_sources_preferred"> Prefer User-Provided URLs (then Primary Domain, then AI knowledge)</label><br>
                                    <label><input type="radio" name="wiz_trivia_knowledge_source" value="user_sources_exclusive"> Use ONLY User-Provided URLs (then Primary Domain, no general AI knowledge for sources)</label>
                                </fieldset>
                                <p class="description">How AI should use URLs for question content.</p>
                            </td>
                        </tr>
                    </table>
                    <div class="mt-6 flex flex-wrap justify-start gap-3">
                        <button type="button" id="saveAISettingsBtn" class="btn"><i class="fas fa-cog"></i> Save AI & Source Settings</button>
                        <button type="button" id="generateAIBtn" class="btn"><i class="fas fa-cogs"></i> Generate Questions with AI</button>
                    </div>
                    <div id="aiGenerationStatus" class="mt-4 p-3 rounded-md text-sm hidden" style="background-color: #1a202c; border: 1px solid #4a5568;"></div>
                    
                    <!-- Progress Panel for Question Generation -->
                    <div id="wiztrivia-progress-panel" style="display:none; border:1px solid #4a5568; background:#1a202c; color: #fff; padding:16px; margin:16px 0; border-radius: 4px;">
                      <div id="wiztrivia-progress-message" style="margin-bottom:8px; font-size: 0.9em;"></div>
                      <div style="background:#4a5568; width:100%; height:20px; border-radius:3px;">
                        <div id="wiztrivia-progress-bar" style="background:#38a169; height:100%; width:0%; border-radius:3px; transition: width 0.5s ease-in-out;"></div>
                      </div>
                    </div>
                    <script>
                    function updateWizTriviaProgress() {
                      fetch('<?php echo esc_url_raw(rest_url('wiztrivia/v1/progress')); ?>') // Use rest_url for robustness
                        .then(res => {
                            if (!res.ok) {
                                throw new Error(`HTTP error! status: ${res.status}`);
                            }
                            return res.json();
                        })
                        .then(data => {
                          const progressPanel = document.getElementById('wiztrivia-progress-panel');
                          const progressMessage = document.getElementById('wiztrivia-progress-message');
                          const progressBar = document.getElementById('wiztrivia-progress-bar');

                          if (!progressPanel || !progressMessage || !progressBar) return;

                          if (data.in_progress) {
                            progressPanel.style.display = 'block';
                            progressMessage.innerText =
                              `Generating questions: ${data.generated} / ${data.total} complete. ${data.message || ''}`;
                            progressBar.style.width = 
                              `${data.total ? (data.generated / data.total * 100) : 0}%`;
                          } else if (data.complete) {
                            progressPanel.style.display = 'block';
                            progressMessage.innerText = data.message || "Question generation complete!";
                            progressBar.style.width = '100%';
                            // Optional: hide after a few seconds of completion
                            // setTimeout(() => { progressPanel.style.display = 'none'; }, 5000);
                          } else {
                            progressPanel.style.display = 'none';
                          }
                        })
                        .catch(error => {
                            console.error("Error fetching WizTrivia progress:", error);
                            // Optionally hide or show an error in the progress panel
                            // document.getElementById('wiztrivia-progress-panel').style.display = 'none';
                        });
                    }
                    // Set interval to poll for progress
                    const wizTriviaProgressInterval = setInterval(updateWizTriviaProgress, 3000); // Poll every 3 seconds
                    // Initial call
                    updateWizTriviaProgress(); 
                    // Consider clearing interval if page navigates away or if generation is definitively complete and not restarting soon.
                    // For now, it will poll as long as the admin page is open.
                    </script>

                    <p class="text-xs text-yellow-400 mt-2"> AI will attempt to generate questions...</p>
                </form>
            </div>
            <div class="my-6 p-4 bg-blue-900 bg-opacity-30 border border-blue-700 rounded-md text-right">
                <button id="saveAllDataToServerBtn" class="btn">
                    <i class="fas fa-server"></i> Save All Trivia Data to Server
                </button>
                <p class="text-xs text-gray-400 mt-1 wiz-admin-save-description">Saves all question/level changes...</p>
            </div>
            <div class="mb-8 p-6 bg-gray-700 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold text-white mb-4" id="mainFormAreaTitle">Manage Trivia Data (Questions & Levels)</h2>
                <div class="p-4 mb-6 border border-gray-600 rounded-md bg-gray-750">
                    <h3 class="form-section-title !text-lg !text-white">Manage Existing Level's Suggested Article</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mt-3">
                        <div>
                            <label for="selectArticleTopic" class="block text-sm font-medium">Select Topic:</label>
                            <select id="selectArticleTopic" name="selectArticleTopic"><option value="">-- Select Topic --</option></select>
                        </div>
                        <div>
                            <label for="selectArticleLevel" class="block text-sm font-medium">Select Level:</label>
                            <select id="selectArticleLevel" name="selectArticleLevel"><option value="">-- Select Level --</option></select>
                        </div>
                        <div>
                            <button type="button" id="loadArticleForSelectedLevelBtn" class="btn w-full"><i class="fas fa-download"></i>Load Article Info</button>
                        </div>
                    </div>
                </div>
                <form id="questionForm">
                    <input type="hidden" id="editMode" value="false">
                    <input type="hidden" id="editTopicName"><input type="hidden" id="editLevelIndex"><input type="hidden" id="editQuestionIndex">
                    <div id="currentActionTitleContainer" class="sub-action-title hidden" style="margin-top: 1rem; margin-bottom: 1.5rem;"></div>
                    <div class="form-section-title">Topic & Level Identification</div>
                    <p class="text-xs text-gray-400 mb-2">Define the topic and level...</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label for="topic" class="block text-sm font-medium">Topic Name:</label><input type="text" id="topic" name="topic" required placeholder="e.g., Generative AI & Tech"></div>
                        <div><label for="level" class="block text-sm font-medium">Level Number (1-based):</label><input type="number" id="level" name="level" min="1" required placeholder="e.g., 1"></div>
                    </div>
                    <div class="form-section-title">Level-Specific Article Suggestion...</div>
                    <p class="text-xs text-gray-400 mb-2">This article information is linked...</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label for="levelArticleUrl" class="block text-sm font-medium">Level Article URL:</label><input type="url" id="levelArticleUrl" name="levelArticleUrl" placeholder="https://example.com/level-article"></div>
                        <div><label for="levelArticleTitle" class="block text-sm font-medium">Level Article Title:</label><input type="text" id="levelArticleTitle" name="levelArticleTitle" placeholder="Title for Level Article Suggestion"></div>
                    </div>
                    <div class="mt-4 mb-6 text-right">
                        <button type="button" id="saveLevelArticleBtn" class="btn"><i class="fas fa-save"></i>Save Level Article Info Only</button>
                        <p class="text-xs text-yellow-400 mt-1">This button saves only the article info...</p>
                    </div>
                    <div class="form-section-title">Question Details</div>
                    <p class="text-xs text-gray-400 mb-2">Provide the question text...</p>
                    <div><label for="questionText" class="block text-sm font-medium mt-4">Question Text:</label><textarea id="questionText" name="questionText" rows="3" required placeholder="Enter the question"></textarea></div>
                    <div><label for="options" class="block text-sm font-medium mt-4">Options (comma-separated, min 2):</label><input type="text" id="options" name="options" required placeholder="e.g., Option A,Option B,Option C,Option D"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label for="correctAnswer" class="block text-sm font-medium mt-4">Correct Answer:</label><input type="text" id="correctAnswer" name="correctAnswer" required placeholder="Must match one of the options above"></div>
                        <div><label for="sourceUrl" class="block text-sm font-medium mt-4">Question Source URL (Optional):</label><input type="url" id="sourceUrl" name="sourceUrl" placeholder="https://example.com/question-source"></div>
                    </div>
                    <div><label for="sourceTitle" class="block text-sm font-medium mt-4">Question Source Title (Optional):</label><input type="text" id="sourceTitle" name="sourceTitle" placeholder="Title of the question source article"></div>
                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <button type="button" id="clearFormBtn" class="btn"><i class="fas fa-eraser"></i>Clear Form / New Question</button>
                        <button type="submit" class="btn"><i class="fas fa-save"></i>Save Question Only</button>
                        <p class="text-xs text-yellow-400 mt-1 w-full text-right">Changes are in-memory...</p>
                    </div>
                </form>
            </div>
            <div class="overflow-x-auto">
                <h2 class="text-xl font-semibold text-white mb-4">Manage Questions</h2>
                <table id="questionsTable" class="min-w-full">
                    <thead><tr><th>Topic</th><th>Level</th><th>Question</th><th>Correct Answer</th><th>Question Source</th><th class="actions-cell">Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
                <p class="text-xs text-yellow-400 mt-2">Edits/Deletions here are in-memory...</p>
            </div>
        </div>
        <div id="deleteConfirmModal" class="modal hidden">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header"><span class="close-button" onclick="closeModal('deleteConfirmModal')">&times;</span>Confirm Deletion</div>
                <p class="my-4">Are you sure you want to delete this question?</p>
                <div id="deleteModalText" class="text-sm text-gray-400 mb-4"></div>
                <p class="text-xs text-yellow-400 my-2">Note: If this is the last question...</p>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('deleteConfirmModal')">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn">Delete Question (In Memory)</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// --- New Progress Update Function (user-provided, slightly adapted) ---
function wiz_trivia_plugin_update_progress_transient( $generated, $total, $in_progress = true, $complete = false ) {
    $message = '';
    $generated = (int)$generated;
    $total = (int)$total;

    if ($complete) {
        $message = 'Generation complete!';
    } elseif ($in_progress) {
        if ($total > 0) {
             $message = "{$generated} / {$total} processed."; // Kept concise for potential direct use
        } else {
             $message = "Generation pending initialization...";
        }
    } else {
        // Not in progress and not complete - e.g. before starting or after a full reset
        $message = 'No generation process active.';
    }

    set_transient( WIZ_TRIVIA_PROGRESS_TRANSIENT, array(
        'in_progress' => (bool)$in_progress,
        'complete'    => (bool)$complete,
        'generated'   => $generated,
        'total'       => $total,
        'message'     => $message, 
    ), 60 * 10 ); // 10 minutes
}

// --- Helper function to calculate and update overall progress based on job_queue ---
function wiz_trivia_calculate_and_set_overall_progress() {
    $job_queue = get_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT);
    
    $overall_total_target = 0;
    $overall_generated_count = 0;
    $any_job_actively_processing = false; // 'pending' or 'processing'
    $all_jobs_in_queue_count = 0; 
    $completed_or_failed_jobs_count = 0;

    if (is_array($job_queue) && !empty($job_queue)) {
        $all_jobs_in_queue_count = count($job_queue);
        foreach ($job_queue as $job_details) {
            $overall_total_target += (int)($job_details['questions_to_generate'] ?? 0);
            $overall_generated_count += count($job_details['questions_generated_so_far'] ?? []);

            if (isset($job_details['status'])) {
                if (in_array($job_details['status'], ['pending', 'processing'])) {
                    $any_job_actively_processing = true;
                } elseif (in_array($job_details['status'], ['completed', 'failed'])) {
                    $completed_or_failed_jobs_count++;
                }
            }
        }
    }

    if ($any_job_actively_processing) {
        wiz_trivia_plugin_update_progress_transient($overall_generated_count, $overall_total_target, true, false);
    } else {
        // No jobs are 'pending' or 'processing'.
        if ($all_jobs_in_queue_count > 0 && $completed_or_failed_jobs_count === $all_jobs_in_queue_count) {
            // All jobs that were in the queue are now done (completed or failed)
            wiz_trivia_plugin_update_progress_transient($overall_generated_count, $overall_total_target, false, true);
        } else {
            // Queue is empty, or in an inconsistent state. Reset progress display.
            wiz_trivia_plugin_update_progress_transient(0, 0, false, false); 
        }
    }
}


// --- REST API Endpoint for Progress ---
add_action( 'rest_api_init', function () {
    register_rest_route( 'wiztrivia/v1', '/progress', array(
        'methods' => 'GET',
        'callback' => 'wiz_trivia_get_generation_progress_callback',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        }
    ) );
} );

function wiz_trivia_get_generation_progress_callback() {
    $progress = get_transient( WIZ_TRIVIA_PROGRESS_TRANSIENT );
    if ( false === $progress ) {
        // Default state if transient is not set (e.g., first load, or after expiry and no new updates)
        return new WP_REST_Response( array(
            'in_progress' => false,
            'complete'    => false,
            'generated'   => 0,
            'total'       => 0,
            'message'     => 'No generation process active or recently completed.',
        ), 200 );
    }
    return new WP_REST_Response( $progress, 200 );
}


// --- AJAX Handlers ---
function wiz_trivia_save_data_ajax_handler() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'])), 'wiz_trivia_admin_nonce' ) ) {
        wp_send_json_error( array('message' => __( 'Security check failed (nonce).', 'wiz-trivia' )), 403 );
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array('message' => __( 'You do not have permission to perform this action.', 'wiz-trivia' )), 403 );
        return;
    }
    if ( ! isset( $_POST['trivia_data'] ) ) {
        wp_send_json_error( array('message' => __( 'No trivia data provided to save.', 'wiz-trivia' )), 400 );
        return;
    }
    $data_string = stripslashes( $_POST['trivia_data'] );
    $data_object = json_decode( $data_string );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( array('message' => __( 'Invalid JSON provided for trivia data.', 'wiz-trivia' ) . ' Error: ' . json_last_error_msg()), 400 );
        return;
    }
    if ( ! is_dir( WIZ_TRIVIA_DATA_DIR ) ) {
        if ( ! wp_mkdir_p( WIZ_TRIVIA_DATA_DIR ) ) {
            wp_send_json_error( array('message' => __( 'Failed to create data directory. Please check permissions.', 'wiz-trivia' )), 500 );
            return;
        }
    }
    $result = file_put_contents( WIZ_TRIVIA_DATA_FILE, json_encode( $data_object, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) );
    if ( $result === false ) {
        wp_send_json_error( array('message' => __( 'Failed to write trivia data to file. Check directory permissions and file writability.', 'wiz-trivia' )), 500 );
    } else {
        wp_send_json_success( array('message' => __( 'Trivia data saved successfully!', 'wiz-trivia' )) );
    }
}
add_action( 'wp_ajax_wiz_save_trivia_data', 'wiz_trivia_save_data_ajax_handler' );

add_action('wp_ajax_wiz_get_ai_job_status', 'wiz_trivia_get_ai_job_status_handler');
function wiz_trivia_get_ai_job_status_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wiz_trivia_admin_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed (nonce).'), 403);
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'), 403);
        return;
    }

    $job_queue = get_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT);
    $active_jobs_data = array();
    $all_jobs_truly_done = true;

    if (is_array($job_queue) && !empty($job_queue)) {
        foreach ($job_queue as $job_id => $details) {
            $generated_count = 0;
            if (isset($details['questions_generated_so_far']) && is_array($details['questions_generated_so_far'])) {
                $generated_count = count($details['questions_generated_so_far']);
            } elseif (isset($details['status']) && $details['status'] === 'completed' && isset($details['questions_to_generate'])) {
                $generated_count = $details['questions_to_generate'];
            }

            $active_jobs_data[] = array(
                'job_id' => $job_id,
                'topic_name' => isset($details['topic_name']) ? $details['topic_name'] : 'N/A',
                'status' => isset($details['status']) ? $details['status'] : 'unknown',
                'generated_count' => $generated_count,
                'target_count' => isset($details['questions_to_generate']) ? $details['questions_to_generate'] : 'N/A',
                'last_error' => isset($details['last_error']) ? $details['last_error'] : '',
            );

            if (isset($details['status']) && ($details['status'] === 'pending' || $details['status'] === 'processing')) {
                $all_jobs_truly_done = false;
            }
        }
         if (empty($active_jobs_data) && !empty($job_queue)) {
             $all_jobs_truly_done = true; // If job_queue has items but active_jobs_data is empty (e.g. all filtered out), means all are done or failed.
        }
    } else { // job_queue is false or empty array
        $all_jobs_truly_done = true;
        if (empty($job_queue)) { // Specifically for empty array
             $active_jobs_data[] = array('message' => 'No generation jobs currently in the queue.');
        }
    }
    
    // If job_queue itself is empty (false or empty array), then all_jobs_truly_done should be true
    if (empty($job_queue)) {
        $all_jobs_truly_done = true;
    }


    wp_send_json_success(array('jobs' => $active_jobs_data, 'all_done' => $all_jobs_truly_done));
}


add_action('wp_ajax_wiz_save_ai_settings', 'wiz_trivia_save_ai_settings_handler');
function wiz_trivia_save_ai_settings_handler() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wiz_trivia_admin_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'wiz-trivia')), 403); return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('No permission.', 'wiz-trivia')), 403); return;
    }
    $settings_changed = false;
    $messages = [];
    if (isset($_POST['api_key'])) {
        update_option(WIZ_TRIVIA_AI_API_KEY_OPTION_NAME, sanitize_text_field(wp_unslash($_POST['api_key'])));
        $messages[] = 'API Key updated.';
        $settings_changed = true;
    }
    if (isset($_POST['ai_provider'])) {
        $ai_provider = sanitize_text_field(wp_unslash($_POST['ai_provider']));
        $allowed_providers = ['openai', 'gemini', 'deepseek'];
        if (in_array($ai_provider, $allowed_providers)) {
            update_option(WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME, $ai_provider);
            $messages[] = 'AI Provider preference updated.';
            $settings_changed = true;
        } else {
            wp_send_json_error(array('message' => __('Invalid AI provider selected.', 'wiz-trivia')), 400); return;
        }
    }
    if (isset($_POST['primary_blog_domain'])) {
        update_option(WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME, sanitize_text_field(wp_unslash($_POST['primary_blog_domain'])));
        $messages[] = 'Primary Blog Domain updated.';
        $settings_changed = true;
    }
    $allow_external = isset($_POST['allow_external_sources']) && ($_POST['allow_external_sources'] === '1' || $_POST['allow_external_sources'] === true || $_POST['allow_external_sources'] === 'true');
    update_option(WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME, $allow_external);
    $messages[] = 'Allow External Sources preference updated.';
    $settings_changed = true;
    if ($settings_changed) {
        wp_send_json_success(array('message' => implode(' ', $messages) . ' AI & Source Settings saved!'));
    } else {
        wp_send_json_error(array('message' => __('No AI settings data provided to save or no changes made.', 'wiz-trivia')), 400);
    }
}

add_action('wp_ajax_wiz_initiate_ai_question_generation', 'wiz_initiate_ai_question_generation_handler');
function wiz_initiate_ai_question_generation_handler() {
    if (!isset($_POST['generate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['generate_nonce'])), 'wiz_trivia_generate_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed (nonce).'), 403); return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'), 403); return;
    }
    $ai_api_key_check = get_option(WIZ_TRIVIA_AI_API_KEY_OPTION_NAME, '');
    if (empty($ai_api_key_check)) {
        wp_send_json_error(array('message' => 'AI API Key is not set. Please save it via "Save AI & Source Settings" before generating.'), 400); return;
    }
    $selected_ai_provider = isset($_POST['ai_provider']) ? sanitize_text_field(wp_unslash($_POST['ai_provider'])) : get_option(WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME, 'openai');
    $primary_blog_domain = isset($_POST['primary_blog_domain_override']) ? sanitize_text_field(wp_unslash($_POST['primary_blog_domain_override'])) : get_option(WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME, '');
    $allow_external_sources = isset($_POST['allow_external_sources_override']) ? ($_POST['allow_external_sources_override'] === '1' || $_POST['allow_external_sources_override'] === true || $_POST['allow_external_sources_override'] === 'true') : get_option(WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME, false);
    $topics_data_json = isset($_POST['topics_data']) ? stripslashes($_POST['topics_data']) : '[]';
    $user_topics_data_array = json_decode($topics_data_json, true);
    $knowledge_preference = isset($_POST['knowledge_preference']) ? sanitize_text_field(wp_unslash($_POST['knowledge_preference'])) : 'ai_only'; 
    if (empty($user_topics_data_array)) {
        wp_send_json_error(array('message' => 'No topics provided for generation.'), 400); return;
    }
    $job_queue = get_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT);
    if (false === $job_queue || !is_array($job_queue)) {
        $job_queue = array();
    }
    $jobs_added_count = 0;
    foreach ($user_topics_data_array as $topic_data) {
        if (empty($topic_data['name'])) continue;

        $job_exists = false;
        foreach ($job_queue as $existing_job) {
            if (isset($existing_job['topic_name']) && $existing_job['topic_name'] === $topic_data['name'] &&
                isset($existing_job['status']) && in_array($existing_job['status'], ['pending', 'processing'])) {
                $job_exists = true;
                error_log("Wiz Trivia: Job for topic '{$topic_data['name']}' already exists and is active. Skipping new job creation.");
                break;
            }
        }
        if ($job_exists) {
            continue; 
        }

        $job_id = 'wiz_job_' . sanitize_key($topic_data['name']) . '_' . time() . '_' . wp_generate_password(4, false);
        $job_details = array(
            'job_id' => $job_id,
            'status' => 'pending',
            'topic_name' => sanitize_text_field($topic_data['name']),
            'sources' => array_map('sanitize_url', $topic_data['sources']),
            'ai_provider' => $selected_ai_provider,
            'primary_blog_domain' => $primary_blog_domain,
            'allow_external_sources' => $allow_external_sources, 
            'questions_to_generate' => 25, 
            'questions_generated_so_far' => array(),
            'last_error' => '',
            'added_timestamp' => time()
        );
        $job_queue[$job_id] = $job_details;
        $jobs_added_count++;
    }
    if ($jobs_added_count > 0) {
        set_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT, $job_queue, DAY_IN_SECONDS);
        if (!wp_next_scheduled(WIZ_TRIVIA_CRON_HOOK)) {
            wp_schedule_event(time(), WIZ_TRIVIA_CRON_INTERVAL_NAME, WIZ_TRIVIA_CRON_HOOK);
        }
        wiz_trivia_calculate_and_set_overall_progress(); // Update progress transient
        wp_send_json_success(array('message' => $jobs_added_count . ' topic(s) added to the generation queue. Questions will be generated in the background. You can refresh the page later to see updates.'));
    } else {
        wiz_trivia_calculate_and_set_overall_progress(); // Still update, in case existing jobs' progress needs recalculating
        wp_send_json_error(array('message' => 'No new valid topics found to add to the queue (they might already be processing).'));
    }
}

// --- Build AI Prompt Function ---
function build_trivia_ai_prompt($topic, $num_questions_to_request_this_call, $urls, $primary_domain, $leveling_for_this_call, $allow_external_sources) {
    $prompt = "You are an expert trivia question writer.\n";
    $prompt .= "Generate exactly $num_questions_to_request_this_call unique trivia question for the topic \"$topic\".\n\n";

    $url_count = count($urls);
    if ($url_count > 0) {
        $prompt .= "For question content and source linking, prioritize information from the following URLs if relevant:\n";
        foreach ($urls as $url) {
            $prompt .= "  - " . esc_url($url) . "\n";
        }
        if ($primary_domain) {
            $prompt .= "If the provided URLs are not relevant for a specific question, or for additional context, you may also use information from the domain: " . esc_attr($primary_domain) . "\n";
        }
        $prompt .= "If neither the specific URLs nor the primary domain yield relevant information, you may use your general knowledge for the question content.\n\n";
    } elseif ($primary_domain) {
        $prompt .= "For question content and source linking, primarily use information from the following domain: " . esc_attr($primary_domain) . "\n";
        $prompt .= "If the primary domain does not yield relevant information, you may use your general knowledge.\n\n";
    } else {
        $prompt .= "Use your general AI knowledge for all questions.\n\n";
    }

    $difficulty_instruction_parts = [];
    if (isset($leveling_for_this_call['easy']) && $leveling_for_this_call['easy'] > 0) $difficulty_instruction_parts[] = $leveling_for_this_call['easy'] . " easy";
    if (isset($leveling_for_this_call['medium']) && $leveling_for_this_call['medium'] > 0) $difficulty_instruction_parts[] = $leveling_for_this_call['medium'] . " medium";
    if (isset($leveling_for_this_call['hard']) && $leveling_for_this_call['hard'] > 0) $difficulty_instruction_parts[] = $leveling_for_this_call['hard'] . " hard";
    
    if (!empty($difficulty_instruction_parts)) {
        $prompt .= "The question should be of " . implode(", ", $difficulty_instruction_parts) . " difficulty. ";
    } else {
        $prompt .= "The question should be of medium difficulty. "; 
    }
    $prompt .= "The response for each question must include a \"difficulty\" property set to \"easy\", \"medium\", or \"hard\".\n\n";

    if (!$allow_external_sources) {
        $allowed_sources_list_for_prompt = [];
        if (!empty($primary_domain)) {
            $clean_primary_domain = preg_replace('/^https?:\/\/(www\.)?/i', '', rtrim($primary_domain, '/'));
            $allowed_sources_list_for_prompt[] = "from the domain '" . esc_attr($clean_primary_domain) . "'";
        }
        if (!empty($urls)) {
            foreach($urls as $up_url) {
                 $allowed_sources_list_for_prompt[] = "be exactly '" . esc_url_raw($up_url) . "'";
            }
        }
        if (!empty($allowed_sources_list_for_prompt)) {
            $prompt .= "If you include a 'source_url', it MUST " . implode(' OR ', array_unique($allowed_sources_list_for_prompt)) . ". ";
            $prompt .= "If a relevant source from this list cannot be found for the question, 'source_url' and 'source_title' MUST be empty strings (\"\").\n\n";
        } else {
            $prompt .= "For this question, 'source_url' and 'source_title' MUST be empty strings (\"\"), as no specific domains or URLs are permitted for sourcing.\n\n";
        }
    } else {
        $prompt .= "The 'source_url' can be any relevant public URL. If the question is based on DigitrendZ blog content (e.g., if 'digitrendz.blog' is the primary domain or among the provided URLs), please prioritize using the relevant DigitrendZ article URL for 'source_url'.\n\n";
    }
    
    $prompt .= "If the question is specifically derived from content related to 'DigitrendZ' (either from the provided URLs, primary domain, or your general knowledge), you may subtly reference 'DigitrendZ' in the question_text if it feels natural and accurate (e.g., 'DigitrendZ highlights which...' or 'As discussed on DigitrendZ...').\n\n";

    $prompt .= "Craft the question_text in a natural, direct style suitable for a trivia game. Avoid phrases like 'According to the provided text' or 'Based on the information given' unless it's a direct quote or essential for context.\n";
    $prompt .= "Ensure the question_text is clear and concise.\n";
    $prompt .= "Provide exactly 4 multiple-choice options in the 'options' array.\n";
    $prompt .= "One of these options must be the 'correct_answer'.\n";
    $prompt .= "Ensure no two questions are about the exact same fact and avoid rephrasing the same fact if generating multiple questions in a broader session (this specific call is for one question, but keep this in mind for overall uniqueness).\n\n";

    if ($num_questions_to_request_this_call === 1) {
        $prompt .= "Return your response ONLY as a single, valid JSON object adhering to the following precise structure. Do not include any other text, explanations, apologies, or conversational filler before the opening '{' or after the closing '}':\n";
        $prompt .= "{\n  \"question_text\": \"The naturally phrased trivia question\",\n  \"options\": [\"Option A text\", \"Option B text\", \"Option C text\", \"Option D text\"],\n  \"correct_answer\": \"The exact text of the correct option from the 'options' list\",\n  \"difficulty\": \"easy/medium/hard\",\n  \"source_url\": \"A relevant URL (or empty string, following source instructions)\",\n  \"source_title\": \"A short, relevant title for the source URL (or empty string, following source instructions)\"\n}";
    } else {
        $prompt .= "Return all questions as a single JSON array, where each element of the array is an object structured as follows. Do not include any other text, explanations, apologies, or conversational filler before the opening '[' or after the closing ']':\n";
        $prompt .= "[\n  {\n    \"question_text\": \"...\",\n    \"options\": [\"...\", \"...\", \"...\", \"...\"],\n    \"correct_answer\": \"...\",\n    \"difficulty\": \"easy/medium/hard\",\n    \"source_url\": \"...\",\n    \"source_title\": \"...\"\n  },\n  {...next question...}\n]";
    }
    return $prompt;
}


// --- WP Cron Setup and Worker Function ---
add_filter('cron_schedules', 'wiz_trivia_add_cron_interval');
function wiz_trivia_add_cron_interval($schedules) {
    $schedules[WIZ_TRIVIA_CRON_INTERVAL_NAME] = array(
        'interval' => 300, // 5 minutes = 300 seconds
        'display'  => esc_html__('Every Five Minutes (Wiz Trivia)')
    );
    return $schedules;
}

add_action(WIZ_TRIVIA_CRON_HOOK, 'wiz_trivia_do_process_generation_queue');
function wiz_trivia_do_process_generation_queue() {
    error_log("Wiz Trivia Cron: === Starting New Cron Run ===");
    $job_queue = get_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT);
    if (false === $job_queue || !is_array($job_queue) || empty($job_queue)) {
        error_log("Wiz Trivia Cron: Job queue is empty or not found. Unscheduling cron.");
        wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
        wiz_trivia_calculate_and_set_overall_progress(); // Update progress to reflect empty queue
        return;
    }
    $job_to_process_id = null;
    $current_job_details = null;
    foreach ($job_queue as $job_id => $details) {
        if (isset($details['status']) && $details['status'] === 'pending') {
            $job_to_process_id = $job_id;
            $current_job_details = $details;
            error_log("Wiz Trivia Cron: Found PENDING job to process: ID '{$job_to_process_id}' for topic '{$details['topic_name']}'");
            break;
        }
    }
    if (!$job_to_process_id) {
        foreach ($job_queue as $job_id => $details) {
            if (isset($details['status']) && $details['status'] === 'processing') { // Re-attempt processing job if stuck
                $job_to_process_id = $job_id;
                $current_job_details = $details;
                error_log("Wiz Trivia Cron: Found existing PROCESSING job (re-attempting): ID '{$job_to_process_id}' for topic '{$details['topic_name']}'");
                break;
            }
        }
    }
    if (!$job_to_process_id || !$current_job_details) {
        error_log("Wiz Trivia Cron: No actionable (pending or processing) jobs found in queue. Current queue state: " . print_r($job_queue, true));
        $has_any_active_jobs = false;
        foreach ($job_queue as $details_check) {
            if (isset($details_check['status']) && in_array($details_check['status'], ['pending', 'processing'])) {
                $has_any_active_jobs = true;
                break;
            }
        }
        if (!$has_any_active_jobs) {
            wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
            error_log("Wiz Trivia Cron: All jobs seem completed or failed. Unscheduling cron.");
        }
        wiz_trivia_calculate_and_set_overall_progress(); // Update progress based on current queue state
        return;
    }

    error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Setting status to 'processing'.");
    $job_queue[$job_to_process_id]['status'] = 'processing';
    $job_queue[$job_to_process_id]['last_error'] = ''; // Clear last error for this attempt
    set_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT, $job_queue, DAY_IN_SECONDS);
    wiz_trivia_calculate_and_set_overall_progress(); // Reflect status change

    $ai_api_key = get_option(WIZ_TRIVIA_AI_API_KEY_OPTION_NAME, '');
    if (empty($ai_api_key)) {
        $job_queue[$job_to_process_id]['status'] = 'failed';
        $job_queue[$job_to_process_id]['last_error'] = 'AI API Key not found during cron processing.';
        set_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT, $job_queue, DAY_IN_SECONDS);
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Failed - AI API Key missing.");
        wiz_trivia_calculate_and_set_overall_progress();
        return;
    }

    $current_topic_name = $current_job_details['topic_name'];
    $user_provided_urls = isset($current_job_details['sources']) && is_array($current_job_details['sources']) ? $current_job_details['sources'] : array();
    $selected_ai_provider = $current_job_details['ai_provider'];
    $primary_blog_domain = $current_job_details['primary_blog_domain'];
    $allow_external_sources = $current_job_details['allow_external_sources'];
    $questions_target_count = (int) $current_job_details['questions_to_generate'];
    $questions_generated_so_far = isset($current_job_details['questions_generated_so_far']) && is_array($current_job_details['questions_generated_so_far']) ? $current_job_details['questions_generated_so_far'] : array();
    
    $questions_to_generate_this_run = 5; 
    $newly_generated_this_run_count = 0;

    error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Processing for topic '{$current_topic_name}'. Target: {$questions_target_count}, Already generated: " . count($questions_generated_so_far) . ", Attempting this run: {$questions_to_generate_this_run}");

    for ($i = 0; $i < $questions_to_generate_this_run; $i++) {
        if (count($questions_generated_so_far) >= $questions_target_count) {
            error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Reached target count ({$questions_target_count}) for '{$current_topic_name}' within batch loop. Breaking.");
            break;
        }
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Attempting question #" . (count($questions_generated_so_far) + 1) . " for topic '{$current_topic_name}'.");

        $leveling_for_this_call = ['easy' => 0, 'medium' => 1, 'hard' => 0]; // Example, can be made dynamic
        
        $prompt_text = build_trivia_ai_prompt(
            $current_topic_name,
            1, 
            $user_provided_urls,
            $primary_blog_domain,
            $leveling_for_this_call,
            $allow_external_sources
        );
        
        $api_response_data = null;
        if ($selected_ai_provider === 'openai') $api_response_data = wiz_trivia_call_openai_api($ai_api_key, $prompt_text, $current_topic_name);
        elseif ($selected_ai_provider === 'gemini') $api_response_data = wiz_trivia_call_gemini_api($ai_api_key, $prompt_text, $current_topic_name);
        elseif ($selected_ai_provider === 'deepseek') $api_response_data = wiz_trivia_call_deepseek_api($ai_api_key, $prompt_text, $current_topic_name);
        
        if (isset($api_response_data['error'])) {
            $job_queue[$job_to_process_id]['last_error'] = "API error (call " . ($newly_generated_this_run_count + 1) . "/" . $questions_to_generate_this_run . "): " . $api_response_data['error'];
            error_log("Wiz Trivia Cron Job '{$job_to_process_id}': API Error (call " . ($newly_generated_this_run_count + 1) . ") for '{$current_topic_name}': " . $api_response_data['error']);
            break; 
        }
        
        $question_data = $api_response_data; 

        if ($question_data && 
            isset($question_data['question_text']) && 
            isset($question_data['options']) && is_array($question_data['options']) && count($question_data['options']) >= 2 && 
            isset($question_data['correct_answer']) &&
            isset($question_data['difficulty'])) {

            if (in_array($question_data['correct_answer'], $question_data['options'])) {
                $final_options = array_slice($question_data['options'], 0, 4);
                while(count($final_options) < 2) { $final_options[] = "N/A Option ".(count($final_options)+1); }
                if (!in_array($question_data['correct_answer'], $final_options) && count($final_options) > 0) {
                    $found_correct = false;
                    foreach($final_options as $fo_idx => $fo) { // Ensure case-insensitive match and use the exact option casing
                        if (strcasecmp(trim($fo), trim($question_data['correct_answer'])) == 0) {
                            $question_data['correct_answer'] = $fo; // Use the casing from the options array
                            $found_correct = true;
                            break;
                        }
                    }
                    if (!$found_correct) $question_data['correct_answer'] = $final_options[0];
                }

                $questions_generated_so_far[] = array(
                    'question' => sanitize_text_field($question_data['question_text']),
                    'options' => array_map('sanitize_text_field', $final_options),
                    'answer' => sanitize_text_field($question_data['correct_answer']),
                    'difficulty' => sanitize_text_field($question_data['difficulty']), 
                    'source_url' => isset($question_data['source_url']) ? esc_url_raw(trim($question_data['source_url'])) : '',
                    'source_title' => isset($question_data['source_title']) ? sanitize_text_field(trim($question_data['source_title'])) : ''
                );
                $newly_generated_this_run_count++;
                error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Successfully generated and parsed question. newly_generated_this_run_count: " . $newly_generated_this_run_count . ". Total for topic: " . count($questions_generated_so_far));
            } else {
                $current_q_num_attempt = count($questions_generated_so_far) + 1;
                error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Validation error - correct answer '{$question_data['correct_answer']}' not in options for '{$current_topic_name}' for question attempt {$current_q_num_attempt}. Options: " . json_encode($question_data['options']));
                $job_queue[$job_to_process_id]['last_error'] = "Validation error for question " . $current_q_num_attempt . ": Correct answer mismatch.";
            }
        } else {
            $current_q_num_attempt = count($questions_generated_so_far) + 1;
            $missing_fields = [];
            if (!isset($question_data['question_text'])) $missing_fields[] = 'question_text';
            if (!isset($question_data['options'])) $missing_fields[] = 'options';
            if (!isset($question_data['correct_answer'])) $missing_fields[] = 'correct_answer';
            if (!isset($question_data['difficulty'])) $missing_fields[] = 'difficulty';
            error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Invalid structure from AI for '{$current_topic_name}' for question attempt {$current_q_num_attempt}. Missing: " . implode(', ', $missing_fields) . ". Response snippet: " . substr(json_encode($api_response_data), 0, 200));
            $job_queue[$job_to_process_id]['last_error'] = "Invalid AI structure for question " . $current_q_num_attempt . ". Missing fields: " . implode(', ', $missing_fields);
        }
    } // End for loop for batch generation

    $job_queue[$job_to_process_id]['questions_generated_so_far'] = $questions_generated_so_far;
    error_log("Wiz Trivia Cron Job '{$job_to_process_id}': After batch loop, questions_generated_so_far count for '{$current_topic_name}': " . count($questions_generated_so_far));

    if ($newly_generated_this_run_count > 0) {
        // We only integrate if all questions for THIS TOPIC are done, or if explicitly told to integrate partially.
        // For now, let's integrate once the topic's target is met or if it failed but has some questions.
        // The current wiz_trivia_integrate_job_data_to_file replaces the entire topic data.
        // So it should only be called when a job is truly finished (completed or failed with some data).
        // However, the existing logic calls it if $newly_generated_this_run_count > 0.
        // This means partial data for a topic could be written. Let's stick to that for now to minimize changes.
        wiz_trivia_integrate_job_data_to_file( $current_topic_name, $questions_generated_so_far ); // This updates the main JSON file
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Called integrate_job_data for " . count($questions_generated_so_far) . " questions for '{$current_topic_name}' after processing batch of {$newly_generated_this_run_count}.");
    } else if (empty($job_queue[$job_to_process_id]['last_error'])) {
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': No new questions were successfully generated in this run and no new API error recorded in this batch for '{$current_topic_name}'.");
    } else {
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': No new questions generated in this run for '{$current_topic_name}', and an error was recorded: " . $job_queue[$job_to_process_id]['last_error']);
    }

    // Update job status
    if (count($questions_generated_so_far) >= $questions_target_count) {
        $job_queue[$job_to_process_id]['status'] = 'completed';
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Topic '{$current_topic_name}' marked COMPLETED. Total generated: " . count($questions_generated_so_far) . ".");
        // If integrating only on completion, this is where wiz_trivia_integrate_job_data_to_file would be called for completed jobs.
    } elseif (!empty($job_queue[$job_to_process_id]['last_error'])) {
        $job_queue[$job_to_process_id]['status'] = 'failed'; // Mark as failed if an error occurred in this batch
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Topic '{$current_topic_name}' marked FAILED due to error: " . $job_queue[$job_to_process_id]['last_error']);
        // If integrating only on completion/failure, this is where wiz_trivia_integrate_job_data_to_file would be called for failed jobs if they have some data.
    } else {
        // Not completed yet, and no error in this batch, so it's still pending for more questions
        $job_queue[$job_to_process_id]['status'] = 'pending'; 
        error_log("Wiz Trivia Cron Job '{$job_to_process_id}': Topic '{$current_topic_name}' status set back to PENDING. Total so far: " . count($questions_generated_so_far) . "/" . $questions_target_count . ". Will continue.");
    }
    
    set_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT, $job_queue, DAY_IN_SECONDS);
    wiz_trivia_calculate_and_set_overall_progress(); // Update overall progress after this job's iteration

    // Check if cron should continue
    $has_any_pending_jobs = false;
    foreach($job_queue as $job_check) {
        if (isset($job_check['status']) && $job_check['status'] === 'pending') {
            $has_any_pending_jobs = true;
            break;
        }
    }

    if (!$has_any_pending_jobs && !empty($job_queue)) {
        $all_remaining_are_terminal = true; // terminal means 'failed' or 'completed'
        foreach($job_queue as $job_check_final) {
            if (isset($job_check_final['status']) && !in_array($job_check_final['status'], ['failed', 'completed'])) {
                $all_remaining_are_terminal = false;
                break;
            }
        }
        if ($all_remaining_are_terminal) { // Check !empty($job_queue) explicitly if needed
            error_log("Wiz Trivia Cron: All jobs in queue are marked 'failed' or 'completed'. Unscheduling cron.");
            wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
            // Final progress update already handled by wiz_trivia_calculate_and_set_overall_progress()
        }
    } else if (empty($job_queue)) { // If queue became empty (e.g., completed jobs are removed, though current logic doesn't remove)
        error_log("Wiz Trivia Cron: Job queue is now empty. Unscheduling cron.");
        wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
        // Final progress update already handled by wiz_trivia_calculate_and_set_overall_progress()
    }

    error_log("Wiz Trivia Cron: === Finished Cron Run Iteration ===");
}


function wiz_trivia_integrate_job_data_to_file($topic_name, $all_questions_for_topic) {
    error_log("Wiz Trivia Integrate: Called for topic '{$topic_name}'. Received " . count($all_questions_for_topic) . " questions.");
    if (empty($all_questions_for_topic) || !is_array($all_questions_for_topic)) {
        error_log("Wiz Trivia Integrate: No questions (or invalid format) to integrate for '{$topic_name}'. Bailing out.");
        return;
    }
    $topic_level_data = array();
    $questions_per_level = 5; // Standard questions per level
    $num_levels = ceil(count($all_questions_for_topic) / $questions_per_level);

    if ($num_levels == 0 && count($all_questions_for_topic) > 0) { // Ensure at least one level if there are questions
        $num_levels = 1;
    }

    for ($l = 0; $l < $num_levels; $l++) {
        $level_q_slice = array_slice($all_questions_for_topic, $l * $questions_per_level, $questions_per_level);
        if (empty($level_q_slice)) continue;

        $sanitized_level_q_slice = [];
        foreach ($level_q_slice as $q_data) {
            $sanitized_level_q_slice[] = [
                'question'      => isset($q_data['question']) ? $q_data['question'] : 'Missing question text',
                'options'       => isset($q_data['options']) && is_array($q_data['options']) ? $q_data['options'] : ['N/A', 'N/A'],
                'answer'        => isset($q_data['answer']) ? $q_data['answer'] : (isset($q_data['options'][0]) ? $q_data['options'][0] : 'N/A'),
                'difficulty'    => isset($q_data['difficulty']) ? $q_data['difficulty'] : 'medium', 
                'source_url'    => isset($q_data['source_url']) ? $q_data['source_url'] : '',       
                'source_title'  => isset($q_data['source_title']) ? $q_data['source_title'] : ''  
            ];
        }
        $topic_level_data[] = array(
            'levelArticleUrl' => '', // Default, can be set later via admin UI
            'levelArticleTitle' => "Level " . ($l + 1) . " - {$topic_name}", // Default title
            'questions' => $sanitized_level_q_slice
        );
    }

    if (!empty($topic_level_data)) {
        $current_trivia_data = array();
        if (file_exists(WIZ_TRIVIA_DATA_FILE)) {
            $file_content = file_get_contents(WIZ_TRIVIA_DATA_FILE);
            if (!empty($file_content)) {
                $decoded = json_decode($file_content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $current_trivia_data = $decoded;
                } else {
                    error_log("Wiz Trivia Integrate: Error decoding existing triviaData.json. Starting fresh for this save. Error: " . json_last_error_msg());
                }
            }
        }
        // This replaces all levels for the given topic_name with the newly generated ones
        $current_trivia_data[$topic_name] = $topic_level_data;

        if (!is_dir(WIZ_TRIVIA_DATA_DIR)) {
            wp_mkdir_p(WIZ_TRIVIA_DATA_DIR);
        }
        $result = file_put_contents(WIZ_TRIVIA_DATA_FILE, json_encode($current_trivia_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        
        if ($result === false) {
            error_log("Wiz Trivia Integrate: Failed to write updated trivia data to file for topic '{$topic_name}'.");
        } else {
            error_log("Wiz Trivia Integrate: Successfully integrated and saved data for topic '{$topic_name}' with " . count($all_questions_for_topic) . " questions across " . count($topic_level_data) . " levels.");
        }
    } else {
        error_log("Wiz Trivia Integrate: Not enough questions or could not form levels for topic '{$topic_name}' (" . count($all_questions_for_topic) . " questions provided). No data written.");
    }
}

function wiz_trivia_call_openai_api($api_key, $prompt_text, $topic_name_for_log = "Unknown Topic") {
    $openai_api_url = 'https://api.openai.com/v1/chat/completions';
    $request_body = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array('role' => 'system', 'content' => 'You are an expert trivia question writer. Output only valid JSON as per user instructions.'),
            array('role' => 'user', 'content' => $prompt_text)
        ),
        'temperature' => 1.0,
        'max_tokens' => 2048, // Increased to allow for more comprehensive JSON, though prompt asks for 1 question
        'response_format' => array('type' => 'json_object')
    );
    $response = wp_remote_post($openai_api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_body),
        'timeout' => 90, // Increased timeout
    ));
    if (is_wp_error($response)) {
        error_log("Wiz Trivia - OpenAI API Connection Error for Topic '{$topic_name_for_log}': " . $response->get_error_message());
        return array('error' => "OpenAI API connection error: " . $response->get_error_message());
    }
    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);
    error_log("Wiz Trivia - Full Decoded OpenAI Response for Topic '{$topic_name_for_log}' (HTTP " . wp_remote_retrieve_response_code($response) . "): " . print_r($decoded_response, true));

    if (wp_remote_retrieve_response_code($response) !== 200 || !$decoded_response || !isset($decoded_response['choices'][0]['message']['content'])) {
        $error_msg = isset($decoded_response['error']['message']) ? $decoded_response['error']['message'] : 'Unknown OpenAI API error or malformed response structure.';
        if (isset($decoded_response['choices'][0]['finish_reason']) && $decoded_response['choices'][0]['finish_reason'] === 'length') {
            $error_msg .= ' (Finish Reason: length - max_tokens might be too low or prompt too restrictive for JSON output)';
        }
        error_log("Wiz Trivia - OpenAI API Error (before parsing content) for Topic '{$topic_name_for_log}': " . $error_msg . " (HTTP Code: " . wp_remote_retrieve_response_code($response) . ")");
        return array('error' => "OpenAI API Error: " . esc_html($error_msg) . " (Code: " . wp_remote_retrieve_response_code($response) . ")");
    }
    $ai_response_text = $decoded_response['choices'][0]['message']['content'];
    error_log("Wiz Trivia - Raw OpenAI Response Text for Topic '{$topic_name_for_log}': [" . $ai_response_text . "]");
    
    // JSON is expected directly due to 'response_format' => array('type' => 'json_object')
    $json_string_to_parse = trim($ai_response_text);

    // It's possible that even with json_object mode, it might sometimes wrap in markdown if the model misbehaves.
    // Defensive check:
    if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/is', $json_string_to_parse, $matches)) {
        $json_string_to_parse = $matches[1];
        error_log("Wiz Trivia - Extracted JSON from Markdown (unexpectedly) for Topic '{$topic_name_for_log}'.");
    } else {
         // Attempt to find the first '{' and last '}' if it's not clean JSON.
        $first_brace = strpos($json_string_to_parse, '{');
        $last_brace = strrpos($json_string_to_parse, '}');
        if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
            $potential_json = substr($json_string_to_parse, $first_brace, $last_brace - $first_brace + 1);
            // Quick check if this substring is likely the intended JSON
            if (json_decode($potential_json) !== null || $json_string_to_parse !== $potential_json) { // if it changed or is valid
                 $json_string_to_parse = $potential_json;
                 error_log("Wiz Trivia - Extracted JSON by finding first/last braces (OpenAI) for Topic '{$topic_name_for_log}'. Original length: ".strlen($ai_response_text).", Extracted length: ".strlen($json_string_to_parse));
            }
        }
    }
    
    $cleaned_string = preg_replace('/[\x00-\x1F\x7F]/u', '', $json_string_to_parse); // Remove control characters
    error_log("Wiz Trivia - Cleaned OpenAI Response for JSON Parsing for Topic '{$topic_name_for_log}': [" . $cleaned_string . "]");

    if (empty(trim($cleaned_string))) {
        error_log("Wiz Trivia - String became empty after cleaning for OpenAI Topic '{$topic_name_for_log}'. Original content was: [" . $ai_response_text . "]");
        return array('error' => "OpenAI response content became empty after cleaning for '{$topic_name_for_log}'.");
    }

    $parsed_data = json_decode($cleaned_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Wiz Trivia - OpenAI Invalid JSON (after cleaning) for Topic '{$topic_name_for_log}': [" . $cleaned_string . "] | PHP JSON Error: " . json_last_error_msg());
        return array('error' => "OpenAI API returned invalid JSON (after cleaning). Error: " . json_last_error_msg() . ". Snippet: " . substr($cleaned_string,0,100));
    }

    // The prompt asks for a single JSON object.
    $question_object = null;
    if (is_array($parsed_data) && isset($parsed_data['question_text'])) { // Direct object
        $question_object = $parsed_data;
    } elseif (is_array($parsed_data) && count($parsed_data) === 1 && isset($parsed_data[0]) && is_array($parsed_data[0]) && isset($parsed_data[0]['question_text'])) {
        // This handles if it unexpectedly returns an array containing one question object.
        error_log("Wiz Trivia - OpenAI response was an array with one element, unexpectedly. Using the first element for Topic '{$topic_name_for_log}'.");
        $question_object = $parsed_data[0];
    } else {
        error_log("Wiz Trivia - OpenAI response was not the expected single question object for Topic '{$topic_name_for_log}'. Parsed data structure: " . print_r(array_keys($parsed_data), true));
        return array('error' => "OpenAI API returned an unexpected JSON structure (not a single question object).");
    }
    
    error_log("Wiz Trivia - Successfully Parsed JSON Object from OpenAI for Topic '{$topic_name_for_log}'");
    return $question_object;
}

function wiz_trivia_call_gemini_api($api_key, $prompt_text, $topic_name_for_log = "Unknown Topic") {
    $model_to_use = 'gemini-1.5-flash-latest'; // Ensure this model supports JSON output mode if available or is good at following instructions
    $gemini_api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model_to_use . ':generateContent?key=' . $api_key;
    
    $request_body = array(
        'contents' => array(array('parts' => array(array('text' => $prompt_text)))),
        'generationConfig' => array(
            'temperature' => 1.0,
            'maxOutputTokens' => 4096, // Sufficient for a single JSON question object
            'responseMimeType' => 'application/json' // Requesting JSON output
        )
    );

    $response = wp_remote_post($gemini_api_url, array(
        'method' => 'POST',
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($request_body),
        'timeout' => 90, // Increased timeout
    ));

    if (is_wp_error($response)) {
        error_log("Wiz Trivia - Gemini API Connection Error for Topic '{$topic_name_for_log}': " . $response->get_error_message());
        return array('error' => "Gemini API connection error: " . $response->get_error_message());
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);
    $http_code = wp_remote_retrieve_response_code($response);
    error_log("Wiz Trivia - Full Decoded Gemini Response for Topic '{$topic_name_for_log}' (HTTP {$http_code}): " . print_r($decoded_response, true));

    if ($http_code !== 200 || !$decoded_response ) {
        $error_msg = 'Unknown Gemini API error or malformed initial response.';
        if (isset($decoded_response['error']['message'])) {
            $error_msg = $decoded_response['error']['message'];
        }
        error_log("Wiz Trivia - Gemini API Error (before checking candidates) for Topic '{$topic_name_for_log}': " . $error_msg . " (HTTP Code: " . $http_code . ")");
        return array('error' => "Gemini API Error (Model: " . esc_html($model_to_use) . "): " . esc_html($error_msg) . " (HTTP Code: " . esc_html($http_code) . ")");
    }

    $ai_response_text = '';
    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_response_text = $decoded_response['candidates'][0]['content']['parts'][0]['text'];
    } else {
        $finishReason = isset($decoded_response['candidates'][0]['finishReason']) ? $decoded_response['candidates'][0]['finishReason'] : 'UNKNOWN_REASON (No text part)';
        $blockReasonDetail = 'Content_Part_Missing_Entirely';
        if(isset($decoded_response['promptFeedback']['blockReason'])) {
            $blockReasonDetail = "Prompt Blocked: " . $decoded_response['promptFeedback']['blockReason'];
        } elseif (isset($decoded_response['candidates'][0]['safetyRatings'])) {
            $blockReasonDetail = "SafetyRatingTriggered. Ratings: " . json_encode($decoded_response['candidates'][0]['safetyRatings']);
        }
        $error_detail = "Expected content text path not found. Finish Reason: {$finishReason}. Details: {$blockReasonDetail}.";
        error_log("Wiz Trivia - Gemini API Missing Content Text for Topic '{$topic_name_for_log}': " . $error_detail);
        if ($finishReason === 'MAX_TOKENS') {
             return array('error' => "Gemini API stopped due to MAX_TOKENS for '{$topic_name_for_log}'. MaxOutputTokens may be too low or prompt too large. Detail: " . esc_html($error_detail));
        }
        return array('error' => "Gemini API response structure error (no text part) for '{$topic_name_for_log}'. Detail: " . esc_html($error_detail));
    }
    
    error_log("Wiz Trivia - Raw Gemini Response Text (from parts[0].text) for Topic '{$topic_name_for_log}': [" . $ai_response_text . "]");
    
    $json_string_to_parse = trim($ai_response_text); // Gemini with responseMimeType='application/json' should return clean JSON.

    // Defensive checks, though ideally not needed with responseMimeType
    if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/is', $json_string_to_parse, $matches)) {
        $json_string_to_parse = $matches[1];
        error_log("Wiz Trivia - Extracted JSON from Markdown (Gemini, unexpected) for Topic '{$topic_name_for_log}'.");
    } else {
        $first_brace = strpos($json_string_to_parse, '{');
        $last_brace = strrpos($json_string_to_parse, '}');
        if ($first_brace === 0 && $last_brace === strlen($json_string_to_parse) - 1) {
            // Looks like clean JSON already
        } elseif ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
            $potential_json = substr($json_string_to_parse, $first_brace, $last_brace - $first_brace + 1);
            if (json_decode($potential_json) !== null || $json_string_to_parse !== $potential_json) {
                $json_string_to_parse = $potential_json;
                error_log("Wiz Trivia - Extracted JSON by finding first/last braces (Gemini) for Topic '{$topic_name_for_log}'. Original length: ".strlen($ai_response_text).", Extracted length: ".strlen($json_string_to_parse));
            }
        }
    }

    $cleaned_string = preg_replace('/[\x00-\x1F\x7F]/u', '', $json_string_to_parse);
    error_log("Wiz Trivia - Cleaned String for JSON Parsing (Gemini) for Topic '{$topic_name_for_log}': [" . $cleaned_string . "]");

    if (empty(trim($cleaned_string))) {
        error_log("Wiz Trivia - String became empty after cleaning for Gemini Topic '{$topic_name_for_log}'. Original was: [" . $ai_response_text . "]");
        return array('error' => "Gemini response content became empty after cleaning for '{$topic_name_for_log}'.");
    }

    $parsed_data = json_decode($cleaned_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Wiz Trivia - Gemini Invalid JSON (after cleaning) for Topic '{$topic_name_for_log}': [" . $cleaned_string . "] | PHP JSON Error: " . json_last_error_msg());
        return array('error' => "Gemini API returned invalid JSON (after cleaning). Error: " . json_last_error_msg() . ". Snippet: " . substr($cleaned_string,0,100));
    }

    $question_object = null;
    // Gemini, with responseMimeType='application/json' and a prompt for a single object, should return that object directly.
    if (is_array($parsed_data) && isset($parsed_data['question_text'])) {
        error_log("Wiz Trivia - Gemini response was a single object for Topic '{$topic_name_for_log}'.");
        $question_object = $parsed_data;
    } elseif (is_array($parsed_data) && count($parsed_data) === 1 && isset($parsed_data[0]) && is_array($parsed_data[0]) && isset($parsed_data[0]['question_text'])) {
        // Fallback if it still wraps it in an array
        error_log("Wiz Trivia - Gemini response was an array with one element (unexpectedly). Using the first element for Topic '{$topic_name_for_log}'.");
        $question_object = $parsed_data[0];
    } else {
        error_log("Wiz Trivia - Gemini response was not a single object or an array with one object for Topic '{$topic_name_for_log}'. Parsed data structure: " . print_r(array_keys($parsed_data), true));
        return array('error' => "Gemini API returned an unexpected JSON structure. Actual structure: " . substr(gettype($parsed_data) . ' with keys: ' . (is_array($parsed_data) ? implode(',', array_keys($parsed_data)) : 'N/A'), 0, 150) );
    }
    
    error_log("Wiz Trivia - Successfully Parsed JSON Object from Gemini for Topic '{$topic_name_for_log}'");
    return $question_object;
}

function wiz_trivia_call_deepseek_api($api_key, $prompt_text, $topic_name_for_log = "Unknown Topic") {
    $deepseek_api_url = 'https://api.deepseek.com/v1/chat/completions'; 
    $request_body = array(
        'model' => 'deepseek-chat', // Or 'deepseek-coder' if more appropriate for structured output, but 'deepseek-chat' should follow JSON instructions.
        'messages' => array(
            // Deepseek might prefer a system message too, or just user. Test what works best.
            // array('role' => 'system', 'content' => 'You are an expert trivia question writer. Output only valid JSON as per user instructions.'),
            array('role' => 'user', 'content' => $prompt_text)
        ),
        'temperature' => 1.0,
        'max_tokens' => 2048, // Ample for one JSON question object
        // Deepseek specific: check if they have a 'response_format' => 'json_object' or similar.
        // If not, rely on strong prompting for JSON.
    );

    $response = wp_remote_post($deepseek_api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_body),
        'timeout' => 90, // Increased timeout
    ));

    if (is_wp_error($response)) {
        error_log("Wiz Trivia - DeepSeek API Connection Error for Topic '{$topic_name_for_log}': " . $response->get_error_message());
        return array('error' => "DeepSeek API connection error: " . $response->get_error_message());
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);
    error_log("Wiz Trivia - Full Decoded DeepSeek Response for Topic '{$topic_name_for_log}' (HTTP " . wp_remote_retrieve_response_code($response) . "): " . print_r($decoded_response, true));

    if (wp_remote_retrieve_response_code($response) !== 200 || !$decoded_response || !isset($decoded_response['choices'][0]['message']['content'])) {
        $error_msg = isset($decoded_response['error']['message']) ? $decoded_response['error']['message'] : 'Unknown DeepSeek API error or malformed response structure.';
         if (isset($decoded_response['choices'][0]['finish_reason']) && $decoded_response['choices'][0]['finish_reason'] === 'length') {
            $error_msg .= ' (Finish Reason: length - max_tokens might be too low or prompt too restrictive for JSON output)';
        }
        error_log("Wiz Trivia - DeepSeek API Error (before parsing content) for Topic '{$topic_name_for_log}': " . $error_msg . " (Code: " . wp_remote_retrieve_response_code($response) . ")");
        return array('error' => "DeepSeek API Error: " . esc_html($error_msg) . " (Code: " . wp_remote_retrieve_response_code($response) . ")");
    }

    $ai_response_text = $decoded_response['choices'][0]['message']['content'];
    error_log("Wiz Trivia - Raw DeepSeek Response Text for Topic '{$topic_name_for_log}': [" . $ai_response_text . "]");
    
    $json_string_to_parse = trim($ai_response_text);

    // DeepSeek might wrap output in ```json ... ``` if not explicitly in a JSON mode.
    if (preg_match('/```json\s*(\{[\s\S]*?\})\s*```/is', $json_string_to_parse, $matches)) {
        $json_string_to_parse = $matches[1];
        error_log("Wiz Trivia - Extracted JSON from Markdown (DeepSeek) for Topic '{$topic_name_for_log}'.");
    } else {
        // Attempt to find the first '{' and last '}' if it's not clean JSON.
        $first_brace = strpos($json_string_to_parse, '{');
        $last_brace = strrpos($json_string_to_parse, '}');
        if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
             $potential_json = substr($json_string_to_parse, $first_brace, $last_brace - $first_brace + 1);
             if (json_decode($potential_json) !== null || $json_string_to_parse !== $potential_json) {
                 $json_string_to_parse = $potential_json;
                 error_log("Wiz Trivia - Extracted JSON by finding first/last braces (DeepSeek) for Topic '{$topic_name_for_log}'. Original length: ".strlen($ai_response_text).", Extracted length: ".strlen($json_string_to_parse));
             }
        } else {
             error_log("Wiz Trivia - No clear JSON block delimiters for DeepSeek Topic '{$topic_name_for_log}'. Proceeding with raw content.");
        }
    }
    
    $cleaned_string = preg_replace('/[\x00-\x1F\x7F]/u', '', $json_string_to_parse); // Remove control characters
    error_log("Wiz Trivia - Cleaned DeepSeek Response for JSON Parsing for Topic '{$topic_name_for_log}': [" . $cleaned_string . "]");

    if (empty(trim($cleaned_string))) {
        error_log("Wiz Trivia - String became empty after cleaning for DeepSeek Topic '{$topic_name_for_log}'. Original content was: [" . $ai_response_text . "]");
        return array('error' => "DeepSeek response content became empty after cleaning for '{$topic_name_for_log}'.");
    }

    $parsed_data = json_decode($cleaned_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Wiz Trivia - DeepSeek Invalid JSON (after cleaning) for Topic '{$topic_name_for_log}': [" . $cleaned_string . "] | PHP JSON Error: " . json_last_error_msg());
        return array('error' => "DeepSeek API returned invalid JSON (after cleaning). Error: " . json_last_error_msg() . ". Snippet: " . substr($cleaned_string,0,100));
    }

    $question_object = null;
    if (is_array($parsed_data) && isset($parsed_data['question_text'])) { // Direct object
        $question_object = $parsed_data;
    } elseif (is_array($parsed_data) && count($parsed_data) === 1 && isset($parsed_data[0]) && is_array($parsed_data[0]) && isset($parsed_data[0]['question_text'])) {
        // Fallback if it wraps in an array
        error_log("Wiz Trivia - DeepSeek response was an array with one element. Using the first element for Topic '{$topic_name_for_log}'.");
        $question_object = $parsed_data[0];
    } else {
        error_log("Wiz Trivia - DeepSeek response was not the expected single question object for Topic '{$topic_name_for_log}'. Parsed data structure: " . print_r(array_keys($parsed_data), true));
        return array('error' => "DeepSeek API returned an unexpected JSON structure (not a single question object).");
    }
    
    error_log("Wiz Trivia - Successfully Parsed JSON Object from DeepSeek for Topic '{$topic_name_for_log}'");
    return $question_object;
}


function wiz_trivia_fetch_and_clean_url_content($url) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return "";
    }
    $response = wp_remote_get($url, array('timeout' => 20));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return "";
    }
    $html_content = wp_remote_retrieve_body($response);
    $text_content = '';
    if (!class_exists('DOMDocument')) {
        $text_content = wp_strip_all_tags($html_content);
        $text_content = html_entity_decode($text_content);
        return preg_replace('/\s+/', ' ', trim($text_content));
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html_content);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    $main_content_node = $xpath->query('//article')->item(0) ?? $xpath->query('//main')->item(0) ?? $xpath->query("//*[@id='content']")->item(0) ?? $xpath->query("//*[@class='content']")->item(0) ?? $xpath->query('//body')->item(0);
    if ($main_content_node) {
        $text_content = wiz_trivia_get_text_from_node($main_content_node);
    } else {
        $text_content = wp_strip_all_tags($html_content);
        $text_content = html_entity_decode($text_content);
    }
    return preg_replace('/\s+/', ' ', trim($text_content));
}

function wiz_trivia_get_text_from_node(DOMNode $node): string {
    $text = '';
    if (in_array($node->nodeName, ['script', 'style', 'nav', 'footer', 'header', 'aside', 'form', 'button', 'select', 'textarea', 'input', 'noscript', 'iframe'])) {
        return '';
    }
    if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $child_value = trim($child->nodeValue);
                if (!empty($child_value)) {
                    if (!empty($text) && !ctype_space(substr($text, -1)) && !ctype_space(substr($child_value, 0, 1)) ) {
                         $text .= ' ';
                    }
                    $text .= $child_value;
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $child_text = wiz_trivia_get_text_from_node($child);
                if (!empty($child_text)) {
                     if (!empty($text) && !ctype_space(substr($text, -1)) && !ctype_space(substr($child_text, 0, 1)) ) {
                         $text .= ' ';
                    }
                    $text .= $child_text;
                }
            }
        }
    }
    return trim($text);
}

function wiz_trivia_activate() {
    if ( false === get_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME ) ) {
        add_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME, array(
            'game_title' => 'Generative AI Trivia',
            'game_logo_url' => 'https://digitrendz.blog/wp-content/uploads/2025/05/digitrendz-New-Logo-4a0538.svg'
        ) );
    }
    if ( false === get_option( WIZ_TRIVIA_AI_API_KEY_OPTION_NAME ) ) {
        add_option( WIZ_TRIVIA_AI_API_KEY_OPTION_NAME, '' );
    }
    if ( false === get_option( WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME ) ) {
        add_option( WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME, 'openai' );
    }
    if ( false === get_option( WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME ) ) {
        add_option( WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME, 'digitrendz.blog' );
    }
    if ( false === get_option( WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME ) ) {
        add_option( WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME, false );
    }
    if ( ! is_dir( WIZ_TRIVIA_DATA_DIR ) ) {
        wp_mkdir_p( WIZ_TRIVIA_DATA_DIR );
    }
    if ( ! file_exists( WIZ_TRIVIA_DATA_FILE ) ) {
        if ( is_writable( WIZ_TRIVIA_DATA_DIR ) ) {
            file_put_contents( WIZ_TRIVIA_DATA_FILE, json_encode( (object) null, JSON_PRETTY_PRINT ) );
        } else {
            error_log('Wiz Trivia Activation: Data directory not writable: ' . WIZ_TRIVIA_DATA_DIR);
        }
    }
    if (!wp_next_scheduled(WIZ_TRIVIA_CRON_HOOK)) {
        wp_schedule_event(time(), WIZ_TRIVIA_CRON_INTERVAL_NAME, WIZ_TRIVIA_CRON_HOOK);
    }
    delete_transient(WIZ_TRIVIA_PROGRESS_TRANSIENT); // Clear progress on activation
}
register_activation_hook( __FILE__, 'wiz_trivia_activate' );

function wiz_trivia_deactivate() {
    wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
    delete_transient(WIZ_TRIVIA_PROGRESS_TRANSIENT); // Clear progress on deactivation
}
register_deactivation_hook( __FILE__, 'wiz_trivia_deactivate' );

function wiz_trivia_add_action_links( $links, $file ) {
    if ( $file === plugin_basename( __FILE__ ) ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wiz-trivia-admin' ) ) . '">' . __( 'Settings', 'wiz-trivia' ) . '</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wiz_trivia_add_action_links', 10, 2 );

function wiz_trivia_uninstall_function() {
    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        exit;
    }
    wp_clear_scheduled_hook(WIZ_TRIVIA_CRON_HOOK);
    delete_transient(WIZ_TRIVIA_JOB_QUEUE_TRANSIENT);
    delete_transient(WIZ_TRIVIA_PROGRESS_TRANSIENT); // Clear progress on uninstall
    delete_option( WIZ_TRIVIA_SETTINGS_OPTION_NAME );
    delete_option( WIZ_TRIVIA_AI_API_KEY_OPTION_NAME );
    delete_option( WIZ_TRIVIA_SELECTED_AI_PROVIDER_OPTION_NAME );
    delete_option( WIZ_TRIVIA_PRIMARY_BLOG_DOMAIN_OPTION_NAME );
    delete_option( WIZ_TRIVIA_ALLOW_EXTERNAL_SOURCES_OPTION_NAME );
    
    // Attempt to delete the data file and directory if empty
    if ( file_exists( WIZ_TRIVIA_DATA_FILE ) ) {
        @unlink( WIZ_TRIVIA_DATA_FILE );
    }
    if ( is_dir( WIZ_TRIVIA_DATA_DIR ) ) {
        // Check if directory is empty before trying to remove
        $is_dir_empty = !(new \FilesystemIterator(WIZ_TRIVIA_DATA_DIR))->valid();
        if ($is_dir_empty) {
            @rmdir( WIZ_TRIVIA_DATA_DIR );
        } else {
            error_log('Wiz Trivia Uninstall: Data directory ' . WIZ_TRIVIA_DATA_DIR . ' was not empty and not removed.');
        }
    }
}
register_uninstall_hook( __FILE__, 'wiz_trivia_uninstall_function' );
?>