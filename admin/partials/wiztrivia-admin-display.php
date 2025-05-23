<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}
?>
<div class="wrap">
    <h1>WizTrivia</h1>
    <p>Welcome to WizTrivia admin panel. Here you can generate and manage trivia questions.</p>
    
    <div class="wiztrivia-admin-container">
        <div class="wiztrivia-settings-panel">
            <h2>Generate New Questions</h2>
            <form id="wiztrivia-generate-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wiztrivia-topic">Topic</label></th>
                        <td>
                            <input type="text" id="wiztrivia-topic" name="topic" class="regular-text" required>
                            <p class="description">Enter the topic for your questions (e.g., "WordPress", "Digital Marketing")</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wiztrivia-source-links">Source Links (Optional)</label></th>
                        <td>
                            <textarea id="wiztrivia-source-links" name="source_links" rows="4" class="large-text"></textarea>
                            <p class="description">Add URLs to content sources (one per line)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wiztrivia-count">Questions per Level</label></th>
                        <td>
                            <input type="number" id="wiztrivia-count" name="count" min="1" max="10" value="3" class="small-text">
                            <p class="description">Number of questions to generate per difficulty level</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="wiztrivia-generate-btn">Generate Questions</button>
                    <span class="spinner" style="float:none;"></span>
                </p>
            </form>
            
            <div id="wiztrivia-generation-result" style="display:none;"></div>
        </div>
        
        <div class="wiztrivia-questions-panel">
            <h2>How to Use</h2>
            <p>Use the shortcode <code>[wiztrivia]</code> to display the quiz on any page or post.</p>
            <p>You can customize the quiz with attributes:</p>
            <ul>
                <li><code>[wiztrivia topic="WordPress"]</code> - Show questions about a specific topic</li>
                <li><code>[wiztrivia difficulty="easy"]</code> - Set difficulty (easy, medium, hard, advanced, expert)</li>
                <li><code>[wiztrivia count="5"]</code> - Number of questions to display</li>
            </ul>
            
            <h2>API Settings</h2>
            <form id="wiztrivia-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wiztrivia-ai-provider">AI Provider</label></th>
                        <td>
                            <select id="wiztrivia-ai-provider" name="ai_provider">
                                <option value="deepseek">DeepSeek AI</option>
                                <option value="openai" disabled>OpenAI (Coming Soon)</option>
                                <option value="gemini" disabled>Google Gemini (Coming Soon)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wiztrivia-api-key">API Key</label></th>
                        <td>
                            <input type="password" id="wiztrivia-api-key" name="ai_api_key" class="regular-text">
                            <p class="description">Enter your API key for the selected provider</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wiztrivia-website-domain">Website Domain</label></th>
                        <td>
                            <input type="text" id="wiztrivia-website-domain" name="website_domain" class="regular-text" value="<?php echo esc_attr(get_site_url()); ?>">
                            <p class="description">Domain to focus questions on</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">AI Knowledge</th>
                        <td>
                            <fieldset>
                                <label for="wiztrivia-include-ai-knowledge">
                                    <input type="checkbox" id="wiztrivia-include-ai-knowledge" name="include_ai_knowledge" value="1">
                                    Include AI's general knowledge (not just from provided sources)
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
    </div>
</div>