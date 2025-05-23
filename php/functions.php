<?php
/**
 * WizTrivia Functions
 * Version: 1.0.4
 * Date: 2025-05-23 00:07:37
 * User: cyberkarhub
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Display modal with proper focus handling
 */
function wiztrivia_show_modal($content_html, $title = '', $button_text = 'Continue', $article_url = '', $article_title = '') {
    ?>
    <div id="wiztrivia-modal" class="wiztrivia-modal" style="display:none;">
        <div class="wiztrivia-modal-content">
            <h3 class="wiztrivia-modal-title"><?php echo esc_html($title); ?></h3>
            <div class="wiztrivia-modal-body">
                <?php echo wp_kses_post($content_html); ?>
                
                <?php if (!empty($article_url) && !empty($article_title)): ?>
                <div class="wiztrivia-article-suggestion">
                    <p>Want to learn more? Check out this article:</p>
                    <a href="<?php echo esc_url($article_url); ?>" target="_blank" 
                       class="wiztrivia-article-link"><?php echo esc_html($article_title); ?></a>
                </div>
                <?php endif; ?>
            </div>
            <div class="wiztrivia-modal-footer">
                <button id="wiztrivia-modal-continue" class="wiztrivia-button"><?php echo esc_html($button_text); ?></button>
            </div>
        </div>
    </div>
    <script>
    (function() {
        // Set focus with a small delay to ensure the element is rendered
        setTimeout(function() {
            try {
                document.getElementById('wiztrivia-modal-continue').focus();
            } catch(e) {
                console.warn("Error focusing modal button:", e);
            }
        }, 50);
    })();
    </script>
    <?php
}