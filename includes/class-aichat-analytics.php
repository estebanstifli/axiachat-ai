<?php
/**
 * AI Chat Analytics Class
 *
 * Handles conversation logging and analytics for the AI Chat plugin.
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AIChat_Analytics {
    /**
     * Initialize the analytics class.
     */
    public function __construct() {
        // Constructor vacío, se puede expandir más adelante.
    }

    /**
     * Log a conversation to the database.
     *
     * @param string $message The user's message.
     * @param string $response The chatbot's response.
     */
    public function log_conversation( $message, $response ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aichat_conversations';

        $wpdb->insert(
            $table_name,
            [
                'user_id'  => get_current_user_id(),
                'message'  => $message,
                'response' => $response,
                'timestamp' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }
}