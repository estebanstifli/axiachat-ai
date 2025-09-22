<?php
/**
 * AI Chat API Class
 *
 * Handles integration with AI APIs for the AI Chat plugin.
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AIChat_API {
    /**
     * Initialize the API class.
     */
    public function __construct() {
        // Constructor vacío, se puede expandir más adelante.
    }

    /**
     * Call the OpenAI API.
     *
     * @param string $message The user's message.
     * @return array Response from OpenAI or error.
     */
    public function call_openai_api( $message ) {
        $api_key = get_option( 'aichat_openai_api_key', '' );
        $model = get_option( 'aichat_openai_model', 'gpt-3.5-turbo' );
        $context = get_option( 'aichat_context', 'You are a helpful AI assistant.' );

        if ( empty( $api_key ) ) {
            return [ 'error' => __( 'OpenAI API key is not set.', 'aichat' ) ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode( [
                'model'    => $model,
                'messages' => [
                    [ 'role' => 'system', 'content' => $context ],
                    [ 'role' => 'user', 'content' => $message ],
                ],
                'max_tokens' => get_option( 'aichat_context_max_length', 4096 ),
                'temperature' => 0.7,
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return [ 'error' => $body['error']['message'] ];
        }

        return [ 'message' => $body['choices'][0]['message']['content'] ];
    }

    /**
     * Call the Anthropic (Claude) API.
     *
     * @param string $message The user's message.
     * @return array Response from Anthropic or error.
     */
    public function call_anthropic_api( $message ) {
        $api_key = get_option( 'aichat_claude_api_key', '' );
        $model = get_option( 'aichat_claude_model', 'claude-3-sonnet-20240229' );
        $context = get_option( 'aichat_context', 'You are a helpful AI assistant.' );

        if ( empty( $api_key ) ) {
            return [ 'error' => __( 'Claude API key is not set.', 'aichat' ) ];
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode( [
                'model' => $model,
                'max_tokens' => get_option( 'aichat_context_max_length', 4096 ),
                'messages' => [
                    [ 'role' => 'user', 'content' => $context . "\n\n" . $message ],
                ],
            ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return [ 'error' => $body['error']['message'] ];
        }

        return [ 'message' => $body['content'][0]['text'] ];
    }
}