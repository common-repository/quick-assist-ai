<?php // Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wpco-popup" class="wpco-popup">
    <div class="wpco-popup-content">
        <span class="wpco-popup-close">&times;</span>
        <div id="wpco-chat-container">
            <div id="wpco-chat-output"></div>
            <div id="processingMessage" style="display:none;">
                Processing...
                <div id="progress-bar-container">
                    <div id="progress-bar"></div>
                </div>
            </div>
        </div>
        <div class="chat-input-container">
            <textarea id="wpco-chat-input" rows="1" placeholder="<?php echo 'Ask me anything...'; ?>" oninput="toggleSendIcon(this.value)"></textarea>
            <button id="wpco-chat-send" disabled>
                <img src="<?php echo esc_url( home_url('wp-content/plugins/quick-assist-ai/assets/media/send-icon.png') ); ?>" alt="Send">
            </button>
        </div>
        <div class="disclaimer">GPTs can make mistakes. Check important info. <br> Would love to hear from you. Write a review <a href="https://wordpress.org/plugins/quick-assist-ai/">here</a></div>
    </div>
</div>

<div id="wpco-chat-icon" class="wpco-chat-icon">
    <img src="<?php echo esc_url( home_url('wp-content/plugins/quick-assist-ai/assets/media/assistant_icon.jpg') ); ?>" alt="Chat Icon" />
</div>

