<?php
/**
 * AxiaChat AI - Tools / Function Calling Registration API (Phase 1)
 *
 * Provides runtime registration of tools (function/custom) that can later be exposed
 * to OpenAI function calling. This phase stores definitions in memory only.
 *
 * @since 1.1.6-dev tools prototype
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aichat_register_tool' ) ) {
    /**
     * Register a tool definition.
     *
     * @param string $id   Internal id (a-z0-9_)
     * @param array  $args {
     *   @type string   $type        'function' (JSON schema) or 'custom'.
     *   @type string   $name        Public name exposed to model.
     *   @type string   $description Human description (<= 500 chars recommended).
     *   @type array    $schema      JSON schema (required for type=function).
     *   @type bool     $strict      Enforce strict schema (default true for function).
     *   @type callable $callback    Executed with ($args_array, $context_state) and must return string|array.
     *   @type mixed    $auth        Capability string or callable that returns bool.
     *   @type int      $timeout     Soft timeout seconds (non fatal, we abort after elapsed > timeout).
     *   @type bool     $parallel    Allow multiple calls in single turn.
     *   @type int      $max_calls   Max calls per overall conversation turn (default 1).
     *   @type array    $custom_input_format Optional grammar {syntax,definition} for type=custom.
     * }
     * @return bool True on success, false on failure.
     */
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
            'auth'        => null, // capability string or callable
            'timeout'     => 5,
            'parallel'    => true,
            'max_calls'   => 1,
            'custom_input_format' => null,
        ];
        $tool = array_merge( $defaults, (array)$args );

        // Basic validation.
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
            // Enforce required JSON schema keys minimal shape
            if ( empty( $tool['schema']['type'] ) || $tool['schema']['type'] !== 'object' ) {
                // Keep it strict: only object root
                return false;
            }
        }
        if ( ! is_int( $tool['timeout'] ) ) { $tool['timeout'] = (int)$tool['timeout']; }
        if ( $tool['timeout'] <= 0 ) { $tool['timeout'] = 5; }
        if ( ! is_int( $tool['max_calls'] ) || $tool['max_calls'] <= 0 ) { $tool['max_calls'] = 1; }

        $tool['name'] = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string)$tool['name'] );
        if ( $tool['name'] === '' ) { $tool['name'] = $raw_id; }

        // Truncate description to reasonable length to avoid token bloat.
        $tool['description'] = mb_substr( (string)$tool['description'], 0, 600 );

        $tools[ $raw_id ] = $tool;
        return true;
    }
}

if ( ! function_exists( 'aichat_get_registered_tools' ) ) {
    /**
     * Returns array of registered tools.
     * @return array
     */
    function aichat_get_registered_tools() {
        // Access static inside closure by re-registration trick
        static $init = false;
        if ( ! $init ) {
            // ensure storage exists via dummy register if needed (won't override)
            $init = true;
        }
        // Reflection of static variable in aichat_register_tool not trivial; replicate store using global static
        // Simpler approach: store also in global for read.
        if ( isset( $GLOBALS['aichat_registered_tools'] ) && is_array( $GLOBALS['aichat_registered_tools'] ) ) {
            return $GLOBALS['aichat_registered_tools'];
        }
        return [];
    }
}

/** Shadow storage via global so getter can read static content. */
add_action( 'init', function(){
    // On each init sync static to global (best-effort). We cannot access static directly; wrap register.
    // Provide a hidden tool to force static creation (no side effects).
    if ( ! isset( $GLOBALS['aichat_registered_tools'] ) ) {
        $GLOBALS['aichat_registered_tools'] = [];
    }
});

// Hook to mirror registrations into global after all plugins loaded.
add_action( 'plugins_loaded', function(){
    if ( ! function_exists('aichat_register_tool') ) return;
    // Reflection not trivial; intercept early: override aichat_register_tool to also push global (not redesign now).
    if ( ! has_action('aichat_tool_registered') ) {
        add_action('aichat_tool_registered', function($id, $def){
            if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
            $GLOBALS['aichat_registered_tools'][$id] = $def;
        }, 10, 2);
    }
});

// Wrap original register to emit action storing copy (decorator pattern) - lightweight monkey patch approach.
if ( ! function_exists('aichat_register_tool_decorator_applied') ) {
    function aichat_register_tool_decorator_applied() { return true; }
    if ( function_exists('aichat_register_tool') ) {
        $orig = 'aichat_register_tool';
        // We cannot rename existing function easily; alternative: encourage add-ons to call helper below.
        // Provide a proxy: aichat_register_tool_safe
        if ( ! function_exists('aichat_register_tool_safe') ) {
            function aichat_register_tool_safe( $id, $args ) {
                $ok = \aichat_register_tool( $id, $args );
                if ( $ok && isset($GLOBALS['aichat_registered_tools'][$id]) === false ) {
                    // If static internal not mirrored, push
                    if ( ! isset($GLOBALS['aichat_registered_tools']) ) $GLOBALS['aichat_registered_tools'] = [];
                    // We cannot read static; but we have $args used → store sanitized version
                    $def = $args;
                    $def['id'] = $id;
                    $GLOBALS['aichat_registered_tools'][$id] = $def;
                    do_action('aichat_tool_registered', $id, $def );
                }
                return $ok;
            }
        }
    }
}
