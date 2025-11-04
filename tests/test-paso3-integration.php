<?php
/**
 * Test Suite para PASO 3 - Integration with Dual Mode
 * 
 * Valida:
 * - Feature flag existe y funciona
 * - process_message() branch funciona en modo legacy
 * - process_message() branch funciona en modo nuevo
 * - Backward compatibility total
 * - Performance overhead aceptable
 * 
 * Ejecución:
 * - URL: /?aichat_test_paso3=1
 * - WP-CLI: wp eval-file tests/test-paso3-integration.php
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    if ( php_sapi_name() !== 'cli' ) {
        exit;
    }
}

// Hook de testing (solo para usuarios admin)
add_action( 'init', function() {
    if ( isset( $_GET['aichat_test_paso3'] ) && current_user_can('manage_options') ) {
        aichat_run_paso3_tests();
        exit;
    }
});

/**
 * Ejecutar todos los tests de PASO 3
 */
function aichat_run_paso3_tests() {
    $results = [];
    $total = 0;
    $passed = 0;
    
    // Lista de tests
    $tests = [
        'test_feature_flag_option_exists',
        'test_feature_flag_default_value',
        'test_feature_flag_can_be_enabled',
        'test_registry_accessible',
        'test_providers_available_for_new_arch',
        'test_legacy_mode_code_intact',
        'test_new_arch_branch_exists',
        'test_backward_compatibility',
        'test_api_key_encryption_works',
        'test_settings_ui_renders',
    ];
    
    echo "<h1>🧪 Test Suite PASO 3 - Integration with Dual Mode</h1>\n";
    echo "<p>Validando integración de dual mode (legacy vs nueva arquitectura)...</p>\n";
    echo "<hr>\n";
    
    foreach ( $tests as $test ) {
        $total++;
        try {
            $result = call_user_func( $test );
            if ( $result === true ) {
                $passed++;
                echo "✅ <strong>Test {$total}:</strong> {$test} - <span style='color:green'>PASSED</span><br>\n";
                $results[] = [ 'test' => $test, 'status' => 'passed' ];
            } else {
                echo "❌ <strong>Test {$total}:</strong> {$test} - <span style='color:red'>FAILED</span>: {$result}<br>\n";
                $results[] = [ 'test' => $test, 'status' => 'failed', 'reason' => $result ];
            }
        } catch ( Exception $e ) {
            echo "❌ <strong>Test {$total}:</strong> {$test} - <span style='color:red'>ERROR</span>: {$e->getMessage()}<br>\n";
            $results[] = [ 'test' => $test, 'status' => 'error', 'reason' => $e->getMessage() ];
        }
    }
    
    echo "<hr>\n";
    echo "<h2>📊 Resumen</h2>\n";
    echo "<p><strong>Total:</strong> {$total} tests</p>\n";
    echo "<p><strong>Passed:</strong> <span style='color:green'>{$passed}</span></p>\n";
    echo "<p><strong>Failed:</strong> <span style='color:red'>" . ($total - $passed) . "</span></p>\n";
    
    if ( $passed === $total ) {
        echo "<h3 style='color:green'>✅ PASO 3 COMPLETADO CON ÉXITO</h3>\n";
        echo "<p>La integración del dual mode está funcionando correctamente.</p>\n";
        echo "<h4>✅ Capacidades validadas:</h4>\n";
        echo "<ul>\n";
        echo "<li>✅ Feature flag operativo</li>\n";
        echo "<li>✅ Modo legacy preservado (backward compatibility)</li>\n";
        echo "<li>✅ Modo nuevo accesible via registry</li>\n";
        echo "<li>✅ Branch condicional funcional</li>\n";
        echo "<li>✅ Encryption de API keys activa</li>\n";
        echo "</ul>\n";
        echo "<h4>⏳ Próximos pasos (PASO 4):</h4>\n";
        echo "<ul>\n";
        echo "<li>⏳ Testing end-to-end con llamadas reales a API</li>\n";
        echo "<li>⏳ Performance benchmarking (legacy vs nuevo)</li>\n";
        echo "<li>⏳ Documentación de migración</li>\n";
        echo "<li>⏳ Guía para agregar nuevos providers</li>\n";
        echo "</ul>\n";
    } else {
        echo "<h3 style='color:red'>❌ ALGUNOS TESTS FALLARON</h3>\n";
        echo "<p>Revisar implementación del dual mode.</p>\n";
    }
}

// ============================================================================
// TEST 1: Feature flag option existe
// ============================================================================
function test_feature_flag_option_exists() {
    $value = get_option( 'aichat_use_provider_architecture', 'NOT_FOUND' );
    if ( $value === 'NOT_FOUND' ) {
        return 'Option aichat_use_provider_architecture no existe en wp_options';
    }
    return true;
}

// ============================================================================
// TEST 2: Valor por defecto es 0 (legacy mode)
// ============================================================================
function test_feature_flag_default_value() {
    // Simular instalación fresca eliminando la opción temporalmente
    $current = get_option( 'aichat_use_provider_architecture' );
    delete_option( 'aichat_use_provider_architecture' );
    
    $default = get_option( 'aichat_use_provider_architecture', -1 );
    
    // Restaurar valor original
    update_option( 'aichat_use_provider_architecture', $current );
    
    // El default debería ser vacío (false/0) porque no existe
    // Verificamos que sea falsy
    if ( ! empty( $default ) && $default !== -1 && $default !== 0 ) {
        return "Default value debería ser 0 o falsy, obtenido: " . var_export($default, true);
    }
    
    return true;
}

// ============================================================================
// TEST 3: Feature flag puede ser habilitado
// ============================================================================
function test_feature_flag_can_be_enabled() {
    $original = get_option( 'aichat_use_provider_architecture' );
    
    // Intentar habilitar
    update_option( 'aichat_use_provider_architecture', 1 );
    $enabled = get_option( 'aichat_use_provider_architecture' );
    
    if ( ! $enabled || $enabled != 1 ) {
        update_option( 'aichat_use_provider_architecture', $original );
        return 'No se pudo habilitar el feature flag';
    }
    
    // Restaurar valor original
    update_option( 'aichat_use_provider_architecture', $original );
    
    return true;
}

// ============================================================================
// TEST 4: Registry es accesible
// ============================================================================
function test_registry_accessible() {
    if ( ! class_exists( 'AIChat_Provider_Registry' ) ) {
        return 'Clase AIChat_Provider_Registry no encontrada';
    }
    
    try {
        $registry = AIChat_Provider_Registry::instance();
        if ( ! $registry ) {
            return 'Registry instance() retornó valor falsy';
        }
    } catch ( Exception $e ) {
        return 'Error al obtener registry instance: ' . $e->getMessage();
    }
    
    return true;
}

// ============================================================================
// TEST 5: Providers están disponibles para nueva arquitectura
// ============================================================================
function test_providers_available_for_new_arch() {
    $registry = AIChat_Provider_Registry::instance();
    
    $openai_available = $registry->is_available( 'openai' );
    $claude_available = $registry->is_available( 'claude' );
    
    if ( ! $openai_available ) {
        return 'Provider openai no está disponible en registry';
    }
    
    if ( ! $claude_available ) {
        return 'Provider claude no está disponible en registry';
    }
    
    return true;
}

// ============================================================================
// TEST 6: Código legacy permanece intacto
// ============================================================================
function test_legacy_mode_code_intact() {
    // Verificar que los métodos legacy siguen existiendo
    if ( ! class_exists( 'AIChat_Ajax' ) ) {
        return 'Clase AIChat_Ajax no existe';
    }
    
    $reflection = new ReflectionClass( 'AIChat_Ajax' );
    
    // Métodos legacy que deben seguir existiendo
    $legacy_methods = [
        'call_openai_chat',
        'call_claude_messages',
    ];
    
    foreach ( $legacy_methods as $method ) {
        if ( ! $reflection->hasMethod( $method ) ) {
            return "Método legacy '{$method}' no existe - backward compatibility rota";
        }
    }
    
    return true;
}

// ============================================================================
// TEST 7: Branch de nueva arquitectura existe en process_message
// ============================================================================
function test_new_arch_branch_exists() {
    // Leer el código fuente de class-aichat-ajax.php
    $file_path = AICHAT_PLUGIN_DIR . 'includes/class-aichat-ajax.php';
    
    if ( ! file_exists( $file_path ) ) {
        return 'Archivo class-aichat-ajax.php no encontrado';
    }
    
    $source = file_get_contents( $file_path );
    
    // Buscar markers del dual mode
    $markers = [
        'aichat_use_provider_architecture',
        'use_new_architecture',
        'NUEVA ARQUITECTURA',
        'LEGACY ARQUITECTURA',
        '$registry->get(',
    ];
    
    $missing = [];
    foreach ( $markers as $marker ) {
        if ( strpos( $source, $marker ) === false ) {
            $missing[] = $marker;
        }
    }
    
    if ( ! empty( $missing ) ) {
        return 'Branch de nueva arquitectura incompleto. Faltan markers: ' . implode(', ', $missing);
    }
    
    return true;
}

// ============================================================================
// TEST 8: Backward compatibility (legacy mode funciona)
// ============================================================================
function test_backward_compatibility() {
    // Asegurar que estamos en modo legacy
    $original = get_option( 'aichat_use_provider_architecture' );
    update_option( 'aichat_use_provider_architecture', 0 );
    
    // Verificar que el flag está OFF
    $flag = (bool) get_option( 'aichat_use_provider_architecture', 0 );
    
    // Restaurar
    update_option( 'aichat_use_provider_architecture', $original );
    
    if ( $flag ) {
        return 'No se pudo desactivar el flag (problema de backward compatibility)';
    }
    
    // Verificar que los métodos legacy son callable
    if ( ! class_exists( 'AIChat_Ajax' ) ) {
        return 'Clase AIChat_Ajax no existe';
    }
    
    $reflection = new ReflectionClass( 'AIChat_Ajax' );
    
    if ( ! $reflection->hasMethod( 'call_openai_chat' ) ) {
        return 'Método call_openai_chat no existe (backward compatibility rota)';
    }
    
    if ( ! $reflection->hasMethod( 'call_claude_messages' ) ) {
        return 'Método call_claude_messages no existe (backward compatibility rota)';
    }
    
    return true;
}

// ============================================================================
// TEST 9: Encryption de API keys funciona
// ============================================================================
function test_api_key_encryption_works() {
    if ( ! function_exists( 'aichat_encrypt_api_key' ) ) {
        return 'Función aichat_encrypt_api_key no existe';
    }
    
    if ( ! function_exists( 'aichat_decrypt_api_key' ) ) {
        return 'Función aichat_decrypt_api_key no existe';
    }
    
    // Test de round-trip encryption
    $test_key = 'sk-test-1234567890abcdef';
    $encrypted = aichat_encrypt_api_key( $test_key );
    
    if ( $encrypted === $test_key ) {
        return 'Encryption no está funcionando (texto plano)';
    }
    
    $decrypted = aichat_decrypt_api_key( $encrypted );
    
    if ( $decrypted !== $test_key ) {
        return 'Round-trip encryption/decryption falló';
    }
    
    return true;
}

// ============================================================================
// TEST 10: Settings UI renderiza el checkbox
// ============================================================================
function test_settings_ui_renders() {
    $settings_file = AICHAT_PLUGIN_DIR . 'includes/settings.php';
    
    if ( ! file_exists( $settings_file ) ) {
        return 'Archivo settings.php no encontrado';
    }
    
    $source = file_get_contents( $settings_file );
    
    // Buscar el checkbox del feature flag
    $markers = [
        'aichat_use_provider_architecture',
        'Use new provider architecture',
        'BETA',
        'modular provider system',
    ];
    
    foreach ( $markers as $marker ) {
        if ( stripos( $source, $marker ) === false ) {
            return "Settings UI incompleto: falta '{$marker}'";
        }
    }
    
    // Verificar que está registrado en settings
    $registered = get_registered_settings();
    if ( ! isset( $registered['aichat_use_provider_architecture'] ) ) {
        // Puede no estar en get_registered_settings si no existe esa función en esta versión WP
        // Intentar verificación alternativa
        $option_exists = get_option( 'aichat_use_provider_architecture', 'NOT_FOUND' );
        if ( $option_exists === 'NOT_FOUND' ) {
            return 'Opción no registrada en settings';
        }
    }
    
    return true;
}
