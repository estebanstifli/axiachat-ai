<?php
/**
 * AI Chat Messages Class
 *
 * Handles predefined messages and responses for the AI Chat plugin.
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AIChat_Messages {
    /**
     * Initialize the messages class.
     */
    public function __construct() {
        // Constructor vacío, se puede expandir más adelante.
    }

    /**
     * Get a predefined response for a given message.
     *
     * @param string $message The user's message.
     * @return string|null The predefined response or null if none found.
     */
    public function get_predefined_response( $message ) {
        // Placeholder: lógica para respuestas predefinidas
        $predefined = [
            'hello' => __( 'Hi! How can I assist you today?', 'aichat' ),
            'help'  => __( 'Please describe your issue, and I’ll do my best to help!', 'aichat' ),
        ];

        $message = strtolower( trim( $message ) );
        return isset( $predefined[ $message ] ) ? $predefined[ $message ] : null;
    }
}