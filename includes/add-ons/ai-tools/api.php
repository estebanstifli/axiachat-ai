<?php
/**
 * AI Tools Registry API (moved from includes/tools.php)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aichat_register_tool' ) ) {
    function aichat_register_tool( $id, $args ) {
        static $tools = [];

        $raw_id = (string)$id;
        if ( ! preg_match( '/^[a-z0-9_]{2,64}$/', $raw_id ) ) {
            return false;
        }

        $defaults = [
            'type'        => 'function',
            'name'        => $raw_id,
            'description' => '',
            'schema'      => [],
            'strict'      => true,
            'callback'    => null,
            'auth'        => null,
            'timeout'     => 5,
            'parallel'    => true,
            'max_calls'   => 1,
            'custom_input_format' => null,
        ];
        $tool = array_merge( $defaults, (array)$args );

        if ( $tool['type'] !== 'function' && $tool['type'] !== 'custom' ) {
            return false;
        }
        if ( ! is_callable( $tool['callback'] ) ) {
            return false;
        }
        if ( $tool['type'] === 'function' ) {
            if ( ! is_array( $tool['schema'] ) || empty( $tool['schema'] ) ) {
                return false;
            }
            if ( empty( $tool['schema']['type'] ) || $tool['schema']['type'] !== 'object' ) {
                return false;
            }
        }
        if ( ! is_int( $tool['timeout'] ) ) { $tool['timeout'] = (int)$tool['timeout']; }
        if ( $tool['timeout'] <= 0 ) { $tool['timeout'] = 5; }
        if ( ! is_int( $tool['max_calls'] ) || $tool['max_calls'] <= 0 ) { $tool['max_calls'] = 1; }

        $tool['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string)$tool['name'] );
        if ( $tool['name'] === '' ) { $tool['name'] = $raw_id; }
        $tool['description'] = mb_substr( (string)$tool['description'], 0, 600 );

        $tools[ $raw_id ] = $tool;
        return true;
    }
}

if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
    function aichat_get_registered_tools() {
        if ( isset( $GLOBALS['aichat_registered_tools'] ) && is_array( $GLOBALS['aichat_registered_tools'] ) ) {
            return $GLOBALS['aichat_registered_tools'];
        }
        return [];
    }
}

add_action( 'init', function(){
    if ( ! isset( $GLOBALS['aichat_registered_tools'] ) ) {
        $GLOBALS['aichat_registered_tools'] = [];
    }
});

add_action( 'plugins_loaded', function(){
    if ( ! function_exists('aichat_register_tool') ) return;
    if ( ! has_action('aichat_tool_registered') ) {
        add_action('aichat_tool_registered', function($id, $def){
            if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
            $GLOBALS['aichat_registered_tools'][$id] = $def;
        }, 10, 2);
    }
});

if ( ! function_exists('aichat_register_tool_decorator_applied') ) {
    function aichat_register_tool_decorator_applied() { return true; }
    if ( function_exists('aichat_register_tool') ) {
        if ( ! function_exists('aichat_register_tool_safe') ) {
            function aichat_register_tool_safe( $id, $args ) {
                $ok = \aichat_register_tool( $id, $args );
                if ( $ok && isset($GLOBALS['aichat_registered_tools'][$id]) === false ) {
                    if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
                    $def = $args; $def['id'] = $id;
                    $GLOBALS['aichat_registered_tools'][$id] = $def;
                    do_action('aichat_tool_registered', $id, $def );
                }
                return $ok;
            }
        }
    }
}
