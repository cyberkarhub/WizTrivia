<?php
/**
 * WizTrivia Admin Display
 * Version: 2.2.0
 * Date: 2025-05-23 08:22:45
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}
?>

<div class="wrap wiztrivia-admin-wrap">
    <div class="wiztrivia-admin-header">
        <h1>
            <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . '../assets/images/wiztrivia-icon.png'; ?>" alt="WizTrivia">
            WizTrivia
            <span class="version">v<?php echo WIZTRIVIA_VERSION; ?></span>
        </h1>
        <div class="wiztrivia-header-actions">
            <a href="https://wiztrivia.com/docs" target="_blank" class="wiztrivia-button secondary small">
                <span class="dashicons dashicons-book"></span> Documentation
            </a>
        </div>
    </div>

    <div class="wiztrivia-admin-content">
        <ul class="wiztrivia-admin-tabs">
            <li><a href="#dashboard" class="active"><span class="dashicons dashicons-dashboard"></span> Dashboard</a></li>
            <li><a href="#questions"><span class="dashicons dashicons-format-chat"></span> Questions</a></li>
            <li><a href="#generate"><span class="dashicons dashicons-update"></span> Generate</a></li>
            <li><a href="#settings"><span class="dashicons dashicons-admin-settings"></span> Settings</a></li>
            <li><a href="#help"><span class="dashicons dashicons-editor-help"></span> Help</a></li>
        </ul>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="wiztrivia-admin-tab-content active">
            <div class="wiztrivia-dashboard">
                <div class="wiztrivia-card">
                    <h3><span class="dashicons dashicons-chart-bar"></span> Statistics</h3>
                    <div class="wiztrivia-stats">
                        <div class="wiztrivia-stat-item">
                            <div class="value" id="question-count">0</div>
                            <div class="label">Questions</div>
                        </div>
                        <div class="wiztrivia-stat-item">
                            <div class="value" id="topic-count">0</div>
                            <div class="label">Topics</div>
                        </div>
                        <div class="wiztrivia-stat-item">
                            <div class="value" id="quiz-count">0</div>
                            <div class="label">Quizzes</div>
                        </div>
                    </div>
                    <a href="#questions" class="wiztrivia-button secondary small tab-link">View All Questions</a>
                </div>

                <div class="wiztrivia-card">
                    <h3><span class="dashicons dashicons-update"></span> AI Generation</h3>
                    <p>Generate questions on any topic using AI technology.</p>
                    <p>Connect with DeepSeek, OpenAI, or Google Gemini to create engaging questions for your quizzes.</p>
                    <a href="#generate" class="wiztrivia-button small tab-link">Start Generating</a>
                </div>

                <div class="wiztrivia-card">
                    <h3><span class="dashicons dashicons-admin-settings"></span> Quick Settings</h3>
                    <div class="wiztrivia-form-row">
                        <label for="quick-api-key">API Key</label>
                        <input type="password" id="quick-api-key" placeholder="Enter your API key">
                    </div>
                    <div class="wiztrivia-form-row">
                        <label for="quick-provider">Provider</label>
                        <select id="quick-provider">
                            <option value="deepseek">DeepSeek</option>
                            <option value="openai">OpenAI</option>
                            <option value="gemini">Google Gemini</option>
                        </select>
                    </div>
                    <a href="#settings" class="wiztrivia-button secondary small tab-link">Advanced Settings</a>
                </div>
            </div>

            <div class="wiztrivia-alert info">
                <span class="dashicons dashicons-info"></span>
                Welcome to WizTrivia! Get started by generating questions or configuring your settings.
            </div>
        </div>

        <!-- Questions Tab -->
        <div id="questions" class="wiztrivia-admin-tab-content">
            <div class="wiztrivia-questions-toolbar">
                <div class="wiztrivia-bulk-action-wrapper">
                    <select id="bulk-action-selector">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                        <option value="regenerate">Regenerate</option>
                    </select>
                    <button class="wiztrivia-button" id="bulk-action-apply">Apply</button>
                    
                    <div class="wiztrivia-select-all-wrapper">
                        <input type="checkbox" id="select-all-questions">
                        <label for="select-all-questions">Select All</label>
                    </div>
                </div>

                <div class="wiztrivia-search-box">
                    <input type="text" id="question-search" placeholder="Search questions...">
                    <button type="button" id="question-search-button">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>
            </div>

            <div id="questions-container">
                <table class="wiztrivia-questions-table">
                    <thead>
                        <tr>
                            <th class="checkbox-column"><input type="checkbox" id="check-all-table"></th>
                            <th>Question</th>
                            <th class="topic-column">Topic</th>
                            <th class="difficulty-column">Difficulty</th>
                            <th class="actions-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="questions-list">
                        <!-- Questions will be loaded here via JavaScript -->
                    </tbody>
                </table>

                <div id="questions-empty" class="wiztrivia-questions-empty" style="display: none;">
                    <p>No questions found. Generate some questions to get started!</p>
                    <a href="#generate" class="wiztrivia-button tab-link">Generate Questions</a>
                </div>
            </div>

            <div class="wiztrivia-pagination" id="questions-pagination">
                <!-- Pagination will be added here via JavaScript -->
            </div>
        </div>

        <!-- Generate Tab -->
        <div id="generate" class="wiztrivia-admin-tab-content">
            <div class="wiztrivia-ai-form">
                <h3><span class="dashicons dashicons-update"></span> Generate Questions with AI</h3>
                
                <div class="wiztrivia-form-row">
                    <label for="generate-topic">Topic</label>
                    <input type="text" id="generate-topic" placeholder="Enter a topic for your questions">
                    <span class="description">e.g. WordPress, Digital Marketing, Cybersecurity</span>
                </div>

                <div class="wiztrivia-form-row">
                    <label for="generate-count">Number of Questions (per difficulty level)</label>
                    <div class="wiztrivia-count-slider">
                        <input type="range" id="generate-count" min="1" max="20" value="5">
                        <output for="generate-count" id="count-output">5</output>
                    </div>
                    <span class="description">This will generate questions for easy, medium, and hard difficulty levels</span>
                </div>

                <div class="wiztrivia-form-row">
                    <label for="generate-source-links">Source Links (Optional)</label>
                    <textarea id="generate-source-links" placeholder="Enter one URL per line to use as sources for questions"></textarea>
                    <span class="description">Add specific articles that will be used as reference for creating questions</span>
                </div>

                <button id="generate-questions-btn" class="wiztrivia-button">
                    <span class="dashicons dashicons-update"></span> Generate Questions
                </button>

                <div id="generation-status" class="wiztrivia-ai-status">
                    <!-- Status messages will appear here -->
                </div>
            </div>

            <div class="wiztrivia-alert info">
                <span class="dashicons dashicons-info"></span>
                <strong>Tip:</strong> Add source links to create questions based on specific content from your website.
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="wiztrivia-admin-tab-content">
            <form id="wiztrivia-settings-form">
                <!-- General Settings Section -->
                <div class="wiztrivia-settings-section">
                    <h2>General Settings</h2>
                    
                    <div class="wiztrivia-form-row">
                        <label for="general-questions-per-quiz">Questions Per Quiz</label>
                        <input type="number" id="general-questions-per-quiz" min="1" max="50" value="10">
                        <span class="description">Default number of questions to show in each quiz</span>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="general-time-limit">Time Limit (seconds)</label>
                        <input type="number" id="general-time-limit" min="0" max="300" value="60">
                        <span class="description">Time limit for each question (0 for no limit)</span>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="general-randomize">Randomize Questions</label>
                        <input type="checkbox" id="general-randomize" checked>
                        <span class="description">Shuffle the order of questions in each quiz</span>
                    </div>
                </div>
                
                <!-- Display Settings Section -->
                <div class="wiztrivia-settings-section">
                    <h2>Display Settings</h2>
                    
                    <div class="wiztrivia-form-row">
                        <label for="display-theme">Quiz Theme</label>
                        <select id="display-theme">
                            <option value="default">Default</option>
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                            <option value="colorful">Colorful</option>
                        </select>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="display-primary-color">Primary Color</label>
                        <div class="color-picker-wrapper">
                            <div class="color-preview" id="primary-color-preview"></div>
                            <input type="text" id="display-primary-color" class="wiztrivia-color-picker" value="#2271b1">
                        </div>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="display-font-size">Font Size</label>
                        <select id="display-font-size">
                            <option value="small">Small</option>
                            <option value="medium" selected>Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                </div>
                
                <!-- AI Settings Section -->
                <div class="wiztrivia-settings-section">
                    <h2>AI Integration Settings</h2>
                    
                    <div class="wiztrivia-form-row">
                        <label>AI Provider</label>
                        <div class="wiztrivia-provider-options">
                            <div class="wiztrivia-provider-option selected" data-provider="deepseek">
                                <h4><img src="<?php echo plugin_dir_url(dirname(__FILE__)) . '../assets/images/deepseek-logo.png'; ?>" alt="DeepSeek"> DeepSeek</h4>
                                <p>Advanced AI model with excellent performance for trivia generation.</p>
                            </div>
                            
                            <div class="wiztrivia-provider-option" data-provider="openai">
                                <h4><img src="<?php echo plugin_dir_url(dirname(__FILE__)) . '../assets/images/openai-logo.png'; ?>" alt="OpenAI"> OpenAI</h4>
                                <p>Powerful GPT models for generating high-quality trivia questions.</p>
                            </div>
                            
                            <div class="wiztrivia-provider-option" data-provider="gemini">
                                <h4><img src="<?php echo plugin_dir_url(dirname(__FILE__)) . '../assets/images/gemini-logo.png'; ?>" alt="Google Gemini"> Google Gemini</h4>
                                <p>Google's advanced AI model for question generation.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="ai-api-key">API Key</label>
                        <input type="password" id="ai-api-key" placeholder="Enter your API key for the selected provider">
                        <span class="description">Your API key will be stored securely</span>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="ai-website-domain">Website Domain</label>
                        <input type="url" id="ai-website-domain" placeholder="https://example.com">
                        <span class="description">Used to generate questions relevant to your website content</span>
                    </div>
                    
                    <div class="wiztrivia-form-row">
                        <label for="ai-include-knowledge">Include AI Knowledge Base</label>
                        <input type="checkbox" id="ai-include-knowledge" checked>
                        <span class="description">Allow AI to use its own knowledge when generating questions</span>
                    </div>
                </div>
                
                <button type="submit" class="wiztrivia-button">
                    <span class="dashicons dashicons-saved"></span> Save Settings
                </button>
            </form>
        </div>

        <!-- Help Tab -->
        <div id="help" class="wiztrivia-admin-tab-content">
            <div class="wiztrivia-alert info">
                <span class="dashicons dashicons-info"></span>
                Need help? Check out the resources below or contact our support team.
            </div>
            
            <h2>Quick Start Guide</h2>
            <p>Follow these steps to set up WizTrivia on your website:</p>
            <ol>
                <li><strong>Configure Settings:</strong> Set up your API keys and preferences in the Settings tab.</li>
                <li><strong>Generate Questions:</strong> Use the Generate tab to create trivia questions with AI.</li>
                <li><strong>Create a Quiz:</strong> Add the [wiztrivia] shortcode to any page or post to display a quiz.</li>
                <li><strong>Customize:</strong> Use shortcode attributes to customize each quiz (e.g., [wiztrivia topic="WordPress" count="5"]).</li>
            </ol>
            
            <div class="wiztrivia-help-section">
                <h3>Documentation & Resources</h3>
                <div class="wiztrivia-help-grid">
                    <div class="wiztrivia-help-item">
                        <h4><span class="dashicons dashicons-book"></span> User Guide</h4>
                        <p>Comprehensive documentation for using WizTrivia effectively.</p>
                        <a href="https://wiztrivia.com/docs" target="_blank">Read Documentation</a>
                    </div>
                    
                    <div class="wiztrivia-help-item">
                        <h4><span class="dashicons dashicons-editor-code"></span> Shortcode Reference</h4>
                        <p>Learn all available shortcode attributes and options.</p>
                        <a href="https://wiztrivia.com/shortcodes" target="_blank">View Shortcodes</a>
                    </div>
                    
                    <div class="wiztrivia-help-item">
                        <h4><span class="dashicons dashicons-format-video"></span> Tutorial Videos</h4>
                        <p>Step-by-step tutorial videos for WizTrivia features.</p>
                        <a href="https://wiztrivia.com/tutorials" target="_blank">Watch Tutorials</a>
                    </div>
                    
                    <div class="wiztrivia-help-item">
                        <h4><span class="dashicons dashicons-sos"></span> Support</h4>
                        <p>Need help? Contact our support team for assistance.</p>
                        <a href="https://wiztrivia.com/support" target="_blank">Get Support</a>
                    </div>
                </div>
            </div>
            
            <div class="wiztrivia-help-section">
                <h3>Shortcode Examples</h3>
                <pre><code>[wiztrivia]</code> - Basic quiz with default settings</pre>
                <pre><code>[wiztrivia topic="WordPress" count="5" difficulty="easy"]</code> - 5 easy WordPress questions</pre>
                <pre><code>[wiztrivia topics="WordPress,PHP,HTML" random="true" time="30"]</code> - Mixed topics with 30-second timer</pre>
                <pre><code>[wiztrivia theme="dark" show_answers="true"]</code> - Dark theme quiz showing answers after completion</pre>
            </div>
        </div>
    </div>
</div>