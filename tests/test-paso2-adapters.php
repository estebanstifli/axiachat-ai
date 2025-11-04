<?php
/**
 * Test Suite para PASO 2 - Provider Adapters
 * 
 * Valida:
 * - Adaptadores OpenAI y Claude creados correctamente
 * - Implementan la interfaz AIChat_Provider_Interface
 * - chat() retorna formato normalizado
 * - calculate_cost() funciona correctamente
 * - Registro automático en el Registry
 * - Fallback chain de Claude
 * 
 * Ejecución:
 * - URL: /?aichat_test_paso2=1
 * - WP-CLI: wp eval-file tests/test-paso2-adapters.php
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
    if ( isset( $_GET['aichat_test_paso2'] ) && current_user_can('manage_options') ) {
        aichat_run_paso2_tests();
        exit;
    }
});

/**
 * Ejecutar todos los tests de PASO 2
 */
function aichat_run_paso2_tests() {
    $results = [];
    $total = 0;
    $passed = 0;
    
    // Lista de tests
    $tests = [
        'test_openai_adapter_exists',
        'test_claude_adapter_exists',
        'test_openai_implements_interface',
        'test_claude_implements_interface',
        'test_openai_get_id',
        'test_claude_get_id',
        'test_openai_chat_method',
        'test_claude_chat_method',
        'test_openai_calculate_cost',
        'test_claude_calculate_cost',
        'test_providers_registered',
        'test_registry_returns_instances',
        'test_openai_cost_accuracy',
        'test_claude_cost_accuracy',
        'test_claude_fallback_chain',
        'test_openai_tools_support',
    ];
    
    echo "<h1>🧪 Test Suite PASO 2 - Provider Adapters</h1>\n";
    echo "<p>Validando adaptadores OpenAI y Claude...</p>\n";
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
        echo "<h3 style='color:green'>✅ PASO 2 COMPLETADO CON ÉXITO</h3>\n";
        echo "<p>Todos los adaptadores están implementados correctamente.</p>\n";
        echo "<h4>✅ Próximos pasos:</h4>\n";
        echo "<ul>\n";
        echo "<li>✅ OpenAI adapter creado y validado</li>\n";
        echo "<li>✅ Claude adapter creado y validado</li>\n";
        echo "<li>✅ Ambos proveedores registrados en el Registry</li>\n";
        echo "<li>⏳ PASO 3: Integración en class-aichat-ajax.php con dual mode</li>\n";
        echo "<li>⏳ PASO 4: Testing completo y documentación</li>\n";
        echo "</ul>\n";
    } else {
        echo "<h3 style='color:red'>❌ ALGUNOS TESTS FALLARON</h3>\n";
        echo "<p>Revisar implementación de los adaptadores.</p>\n";
    }
}

// ============================================================================
// TEST 1: Clase OpenAI Provider existe
// ============================================================================
function test_openai_adapter_exists() {
    if ( ! class_exists( 'AIChat_OpenAI_Provider' ) ) {
        return 'Clase AIChat_OpenAI_Provider no encontrada';
    }
    return true;
}

// ============================================================================
// TEST 2: Clase Claude Provider existe
// ============================================================================
function test_claude_adapter_exists() {
    if ( ! class_exists( 'AIChat_Claude_Provider' ) ) {
        return 'Clase AIChat_Claude_Provider no encontrada';
    }
    return true;
}

// ============================================================================
// TEST 3: OpenAI implementa interfaz
// ============================================================================
function test_openai_implements_interface() {
    $reflection = new ReflectionClass( 'AIChat_OpenAI_Provider' );
    if ( ! $reflection->implementsInterface( 'AIChat_Provider_Interface' ) ) {
        return 'AIChat_OpenAI_Provider no implementa AIChat_Provider_Interface';
    }
    return true;
}

// ============================================================================
// TEST 4: Claude implementa interfaz
// ============================================================================
function test_claude_implements_interface() {
    $reflection = new ReflectionClass( 'AIChat_Claude_Provider' );
    if ( ! $reflection->implementsInterface( 'AIChat_Provider_Interface' ) ) {
        return 'AIChat_Claude_Provider no implementa AIChat_Provider_Interface';
    }
    return true;
}

// ============================================================================
// TEST 5: OpenAI get_id() retorna 'openai'
// ============================================================================
function test_openai_get_id() {
    $provider = new AIChat_OpenAI_Provider( [ 'api_key' => 'test_key' ] );
    $id = $provider->get_id();
    if ( $id !== 'openai' ) {
        return "get_id() retornó '{$id}' en lugar de 'openai'";
    }
    return true;
}

// ============================================================================
// TEST 6: Claude get_id() retorna 'claude'
// ============================================================================
function test_claude_get_id() {
    $provider = new AIChat_Claude_Provider( [ 'api_key' => 'test_key' ] );
    $id = $provider->get_id();
    if ( $id !== 'claude' ) {
        return "get_id() retornó '{$id}' en lugar de 'claude'";
    }
    return true;
}

// ============================================================================
// TEST 7: OpenAI chat() retorna error sin API key
// ============================================================================
function test_openai_chat_method() {
    $provider = new AIChat_OpenAI_Provider( [] );
    $result = $provider->chat( [ [ 'role' => 'user', 'content' => 'test' ] ] );
    
    if ( ! is_array( $result ) ) {
        return 'chat() debe retornar array';
    }
    
    if ( ! isset( $result['error'] ) ) {
        return 'chat() sin API key debe retornar error';
    }
    
    return true;
}

// ============================================================================
// TEST 8: Claude chat() retorna error sin API key
// ============================================================================
function test_claude_chat_method() {
    $provider = new AIChat_Claude_Provider( [] );
    $result = $provider->chat( [ [ 'role' => 'user', 'content' => 'test' ] ] );
    
    if ( ! is_array( $result ) ) {
        return 'chat() debe retornar array';
    }
    
    if ( ! isset( $result['error'] ) ) {
        return 'chat() sin API key debe retornar error';
    }
    
    return true;
}

// ============================================================================
// TEST 9: OpenAI calculate_cost() existe y funciona
// ============================================================================
function test_openai_calculate_cost() {
    $provider = new AIChat_OpenAI_Provider( [ 'api_key' => 'test' ] );
    $usage = [
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'total_tokens' => 1500
    ];
    
    $cost = $provider->calculate_cost( $usage, 'gpt-4o' );
    
    if ( $cost === null ) {
        return 'calculate_cost() retornó null para modelo conocido';
    }
    
    if ( ! is_int( $cost ) ) {
        return 'calculate_cost() debe retornar int (microcents)';
    }
    
    if ( $cost <= 0 ) {
        return 'calculate_cost() debe retornar valor positivo';
    }
    
    return true;
}

// ============================================================================
// TEST 10: Claude calculate_cost() existe y funciona
// ============================================================================
function test_claude_calculate_cost() {
    $provider = new AIChat_Claude_Provider( [ 'api_key' => 'test' ] );
    $usage = [
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'total_tokens' => 1500
    ];
    
    $cost = $provider->calculate_cost( $usage, 'claude-3-5-sonnet-20240620' );
    
    if ( $cost === null ) {
        return 'calculate_cost() retornó null para modelo conocido';
    }
    
    if ( ! is_int( $cost ) ) {
        return 'calculate_cost() debe retornar int (microcents)';
    }
    
    if ( $cost <= 0 ) {
        return 'calculate_cost() debe retornar valor positivo';
    }
    
    return true;
}

// ============================================================================
// TEST 11: Proveedores registrados en Registry
// ============================================================================
function test_providers_registered() {
    $registry = AIChat_Provider_Registry::instance();
    
    if ( ! $registry->is_available( 'openai' ) ) {
        return 'OpenAI no está registrado en el Registry';
    }
    
    if ( ! $registry->is_available( 'claude' ) ) {
        return 'Claude no está registrado en el Registry';
    }
    
    $stats = $registry->get_stats();
    if ( $stats['providers_count'] < 2 ) {
        return 'Registry debe tener al menos 2 proveedores registrados';
    }
    
    return true;
}

// ============================================================================
// TEST 12: Registry retorna instancias válidas
// ============================================================================
function test_registry_returns_instances() {
    $registry = AIChat_Provider_Registry::instance();
    
    $openai = $registry->get( 'openai', [ 'api_key' => 'test' ] );
    if ( ! $openai instanceof AIChat_Provider_Interface ) {
        return 'Registry no retorna instancia válida de OpenAI';
    }
    
    $claude = $registry->get( 'claude', [ 'api_key' => 'test' ] );
    if ( ! $claude instanceof AIChat_Provider_Interface ) {
        return 'Registry no retorna instancia válida de Claude';
    }
    
    return true;
}

// ============================================================================
// TEST 13: Precisión de cálculo de coste OpenAI
// ============================================================================
function test_openai_cost_accuracy() {
    $provider = new AIChat_OpenAI_Provider( [ 'api_key' => 'test' ] );
    
    // GPT-4o: $2.50 input, $10.00 output per 1M tokens
    // 1000 input + 500 output
    // = (1000/1000000 * 2.50) + (500/1000000 * 10.00)
    // = 0.0025 + 0.005 = 0.0075 USD
    // = 0.75 cents = 7500 microcents
    $usage = [
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'total_tokens' => 1500
    ];
    
    $cost = $provider->calculate_cost( $usage, 'gpt-4o' );
    $expected = 7500;
    
    if ( abs( $cost - $expected ) > 10 ) { // Tolerancia de 10 microcents
        return "Coste esperado: {$expected}, obtenido: {$cost}";
    }
    
    return true;
}

// ============================================================================
// TEST 14: Precisión de cálculo de coste Claude
// ============================================================================
function test_claude_cost_accuracy() {
    $provider = new AIChat_Claude_Provider( [ 'api_key' => 'test' ] );
    
    // Claude 3.5 Sonnet: $3.00 input, $15.00 output per 1M tokens
    // 1000 input + 500 output
    // = (1000/1000000 * 3.00) + (500/1000000 * 15.00)
    // = 0.003 + 0.0075 = 0.0105 USD
    // = 1.05 cents = 10500 microcents
    $usage = [
        'prompt_tokens' => 1000,
        'completion_tokens' => 500,
        'total_tokens' => 1500
    ];
    
    $cost = $provider->calculate_cost( $usage, 'claude-3-5-sonnet-20240620' );
    $expected = 10500;
    
    if ( abs( $cost - $expected ) > 10 ) { // Tolerancia de 10 microcents
        return "Coste esperado: {$expected}, obtenido: {$cost}";
    }
    
    return true;
}

// ============================================================================
// TEST 15: Claude fallback chain preservation
// ============================================================================
function test_claude_fallback_chain() {
    // Validar que el código del adapter incluye la lógica de fallback
    $reflection = new ReflectionMethod( 'AIChat_Claude_Provider', 'chat' );
    $source = file_get_contents( $reflection->getFileName() );
    
    // Buscar markers del fallback chain
    $markers = [
        'fallback_chain',
        'claude-3-5-sonnet-20240620',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
        'code === 404'
    ];
    
    foreach ( $markers as $marker ) {
        if ( strpos( $source, $marker ) === false ) {
            return "Fallback chain incompleto: falta '{$marker}'";
        }
    }
    
    return true;
}

// ============================================================================
// TEST 16: OpenAI tools support
// ============================================================================
function test_openai_tools_support() {
    // Validar que el código del adapter incluye soporte para tools
    $reflection = new ReflectionMethod( 'AIChat_OpenAI_Provider', 'chat' );
    $source = file_get_contents( $reflection->getFileName() );
    
    // Buscar markers de tools support
    $markers = [
        'tools',
        'tool_calls',
        'function_call'
    ];
    
    foreach ( $markers as $marker ) {
        if ( strpos( $source, $marker ) === false ) {
            return "Tools support incompleto: falta '{$marker}'";
        }
    }
    
    return true;
}
