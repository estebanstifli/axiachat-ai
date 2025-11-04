<?php
/**
 * Test Script para PASO 1: Infraestructura Base
 * 
 * Verifica que la interfaz y el registry funcionan correctamente.
 * 
 * Ejecutar desde WordPress:
 * - Via WP-CLI: wp eval-file tests/test-paso1-infrastructure.php
 * - Via navegador: añadir parámetro ?aichat_test_paso1=1 (solo admin)
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // Si se ejecuta standalone, cargar WordPress
    require_once dirname(__DIR__, 4) . '/wp-load.php';
}

// Solo permitir a administradores
if ( ! current_user_can('manage_options') && ! defined('WP_CLI') ) {
    wp_die('Unauthorized');
}

echo "=== TEST PASO 1: INFRAESTRUCTURA BASE ===\n\n";

// Test 1: Verificar que la interfaz existe
echo "Test 1: Verificar interfaz AIChat_Provider_Interface...\n";
if ( interface_exists('AIChat_Provider_Interface') ) {
    echo "✅ PASS: Interfaz existe\n";
} else {
    echo "❌ FAIL: Interfaz no encontrada\n";
    exit(1);
}

// Test 2: Verificar que el registry existe
echo "\nTest 2: Verificar clase AIChat_Provider_Registry...\n";
if ( class_exists('AIChat_Provider_Registry') ) {
    echo "✅ PASS: Clase Registry existe\n";
} else {
    echo "❌ FAIL: Clase Registry no encontrada\n";
    exit(1);
}

// Test 3: Singleton pattern
echo "\nTest 3: Verificar patrón Singleton...\n";
$registry1 = AIChat_Provider_Registry::instance();
$registry2 = AIChat_Provider_Registry::instance();
if ( $registry1 === $registry2 ) {
    echo "✅ PASS: Singleton funciona correctamente (misma instancia)\n";
} else {
    echo "❌ FAIL: Singleton retorna instancias diferentes\n";
    exit(1);
}

// Test 4: Registrar proveedor dummy
echo "\nTest 4: Registrar proveedor de prueba...\n";

// Crear clase dummy que implementa la interfaz
class AIChat_Test_Provider implements AIChat_Provider_Interface {
    protected $config = [];
    
    public function __construct( $config = [] ) {
        $this->config = $config;
    }
    
    public function get_id() {
        return 'test_provider';
    }
    
    public function chat( $messages, $params = [] ) {
        return [
            'message' => 'Test response',
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30
            ]
        ];
    }
    
    public function calculate_cost( $usage, $model ) {
        return 100; // 100 microcents = 0.01 USD cents
    }
}

$registry = AIChat_Provider_Registry::instance();
$registered = $registry->register( 'test_provider', 'AIChat_Test_Provider', true );

if ( $registered ) {
    echo "✅ PASS: Proveedor registrado correctamente\n";
} else {
    echo "❌ FAIL: No se pudo registrar proveedor\n";
    exit(1);
}

// Test 5: Verificar disponibilidad
echo "\nTest 5: Verificar disponibilidad de proveedor...\n";
if ( $registry->is_available('test_provider') ) {
    echo "✅ PASS: Proveedor disponible\n";
} else {
    echo "❌ FAIL: Proveedor no disponible\n";
    exit(1);
}

// Test 6: Factory pattern - obtener instancia
echo "\nTest 6: Factory pattern - obtener instancia...\n";
$provider = $registry->get( 'test_provider', ['api_key' => 'test_key'] );

if ( $provider instanceof AIChat_Provider_Interface ) {
    echo "✅ PASS: Factory retorna instancia correcta\n";
} else {
    echo "❌ FAIL: Factory no retorna instancia válida\n";
    exit(1);
}

// Test 7: Verificar que implementa métodos requeridos
echo "\nTest 7: Verificar métodos de la interfaz...\n";
$methods = ['get_id', 'chat', 'calculate_cost'];
$all_ok = true;
foreach ( $methods as $method ) {
    if ( method_exists( $provider, $method ) ) {
        echo "  ✅ Método '{$method}' existe\n";
    } else {
        echo "  ❌ Método '{$method}' no encontrado\n";
        $all_ok = false;
    }
}
if ( $all_ok ) {
    echo "✅ PASS: Todos los métodos implementados\n";
} else {
    echo "❌ FAIL: Faltan métodos en la implementación\n";
    exit(1);
}

// Test 8: Llamar método chat
echo "\nTest 8: Ejecutar método chat()...\n";
$result = $provider->chat( [
    ['role' => 'user', 'content' => 'Test message']
], [
    'model' => 'test-model',
    'temperature' => 0.7,
    'max_tokens' => 100
]);

if ( isset($result['message']) && isset($result['usage']) ) {
    echo "✅ PASS: Método chat() retorna formato correcto\n";
    echo "  - Mensaje: " . substr($result['message'], 0, 50) . "\n";
    echo "  - Tokens: {$result['usage']['total_tokens']}\n";
} else {
    echo "❌ FAIL: Formato de respuesta incorrecto\n";
    exit(1);
}

// Test 9: Calcular coste
echo "\nTest 9: Calcular coste...\n";
$cost = $provider->calculate_cost( $result['usage'], 'test-model' );
if ( is_int($cost) && $cost > 0 ) {
    echo "✅ PASS: Cálculo de coste correcto: {$cost} microcents\n";
} else {
    echo "❌ FAIL: Cálculo de coste incorrecto\n";
    exit(1);
}

// Test 10: Cache de instancias
echo "\nTest 10: Verificar cache de instancias...\n";
$provider2 = $registry->get( 'test_provider', ['api_key' => 'test_key'], true );
if ( $provider === $provider2 ) {
    echo "✅ PASS: Cache funciona (misma instancia retornada)\n";
} else {
    echo "❌ FAIL: Cache no funciona (instancias diferentes)\n";
    exit(1);
}

// Test 11: Sin cache
echo "\nTest 11: Verificar sin cache...\n";
$provider3 = $registry->get( 'test_provider', ['api_key' => 'test_key'], false );
if ( $provider !== $provider3 ) {
    echo "✅ PASS: Sin cache crea nueva instancia\n";
} else {
    echo "❌ FAIL: Sin cache retorna instancia cacheada\n";
    exit(1);
}

// Test 12: Listar proveedores
echo "\nTest 12: Listar proveedores registrados...\n";
$all_providers = $registry->get_all();
if ( isset( $all_providers['test_provider'] ) ) {
    echo "✅ PASS: Proveedor aparece en listado\n";
    echo "  Total registrados: " . count($all_providers) . "\n";
} else {
    echo "❌ FAIL: Proveedor no aparece en listado\n";
    exit(1);
}

// Test 13: Estadísticas
echo "\nTest 13: Obtener estadísticas del registry...\n";
$stats = $registry->get_stats();
echo "  - Total proveedores: {$stats['total_providers']}\n";
echo "  - Proveedores habilitados: {$stats['enabled_providers']}\n";
echo "  - Instancias cacheadas: {$stats['cached_instances']}\n";
if ( $stats['total_providers'] >= 1 && $stats['cached_instances'] >= 1 ) {
    echo "✅ PASS: Estadísticas correctas\n";
} else {
    echo "❌ FAIL: Estadísticas incorrectas\n";
    exit(1);
}

// Test 14: Limpiar cache
echo "\nTest 14: Limpiar cache...\n";
$registry->clear_cache();
$stats_after = $registry->get_stats();
if ( $stats_after['cached_instances'] === 0 ) {
    echo "✅ PASS: Cache limpiado correctamente\n";
} else {
    echo "❌ FAIL: Cache no se limpió\n";
    exit(1);
}

// Test 15: Normalización de provider ID
echo "\nTest 15: Normalización de ID (anthropic → claude)...\n";
$registry->register( 'claude', 'AIChat_Test_Provider', true );
$is_available_anthropic = $registry->is_available('anthropic');
$is_available_claude = $registry->is_available('claude');

if ( $is_available_anthropic && $is_available_claude ) {
    echo "✅ PASS: Normalización funciona (anthropic y claude son el mismo)\n";
} else {
    echo "❌ FAIL: Normalización no funciona\n";
    exit(1);
}

echo "\n";
echo "=================================================\n";
echo "✅ TODOS LOS TESTS PASARON CORRECTAMENTE\n";
echo "=================================================\n";
echo "\n";
echo "Resumen:\n";
echo "- Interfaz: OK\n";
echo "- Registry: OK\n";
echo "- Singleton: OK\n";
echo "- Factory: OK\n";
echo "- Cache: OK\n";
echo "- Normalización: OK\n";
echo "\n";
echo "🎉 PASO 1 COMPLETADO - Infraestructura base lista para PASO 2\n";
echo "\n";
