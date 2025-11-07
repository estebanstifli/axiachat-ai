# Análisis Arquitectónico: Sistema Modular de Proveedores AI

**Fecha:** 2025
**Objetivo:** Analizar arquitectura actual y proponer sistema modular para soportar múltiples proveedores AI (cloud + locales)

---

## 1. RESUMEN EJECUTIVO

### 1.1 Estado Actual
El plugin actualmente soporta **2 proveedores hardcodeados** (OpenAI y Claude) con lógica acoplada en el core. La arquitectura no está preparada para extensión fácil de nuevos proveedores ni soporta modelos locales.

### 1.2 Objetivos de Modularización
- ✅ Soportar múltiples proveedores AI (OpenAI, Claude, Anthropic, Google, etc.)
- ✅ Habilitar modelos locales (Ollama, LM Studio, vLLM, LocalAI)
- ✅ Arquitectura modular con proveedores como add-ons PHP
- ✅ Mejor mantenibilidad y separación de responsabilidades
- ✅ Adición de nuevos proveedores sin modificar core
- ✅ Compatibilidad hacia atrás con bots existentes

### 1.3 Beneficios Esperados
| Aspecto | Antes | Después |
|---------|-------|---------|
| **Proveedores soportados** | 2 hardcodeados | Ilimitados modulares |
| **Añadir proveedor** | Modificar core (3+ archivos) | Crear add-on (1 carpeta) |
| **Modelos locales** | No soportado | Sí (Ollama, etc.) |
| **Mantenimiento** | Código disperso 2500+ líneas | Separado por proveedor |
| **Testing** | Acoplado | Aislado por adapter |

---

## 2. ANÁLISIS DE ARQUITECTURA ACTUAL

### 2.1 Proveedores Hardcodeados

**Ubicación:** `includes/class-aichat-ajax.php` (líneas 296-660)

```php
// Normalización y validación
if ( $provider === 'anthropic' ) { $provider = 'claude'; }

// Routing basado en strings
if ( $provider === 'openai' && !$this->is_openai_responses_model($model) ) {
    // Chat Completions estándar
} elseif ( $provider === 'openai' && $this->is_openai_responses_model($model) ) {
    // Responses API (reasoning)
} elseif ( $provider === 'claude' ) {
    // Anthropic Messages API
}
```

**❌ Problemas identificados:**
1. **Sin abstracción**: Lógica de cada proveedor embebida en método principal
2. **No extensible**: Añadir proveedor requiere modificar core class (2527 líneas)
3. **Sin polimorfismo**: Uso de if/elseif en lugar de interfaces
4. **Validación dispersa**: Provider-model coherence en múltiples archivos

### 2.2 Implementación de Llamadas API

**Métodos actuales:**
- `call_openai_chat()` - Línea 1110-1170 (Chat Completions)
- `call_openai_auto()` - Llamadas con tool support
- `call_openai_responses()` - Responses API (reasoning)
- `call_claude_messages()` - Línea 1195-1310 (Anthropic Messages)

**Patrón actual:**
```php
protected function call_openai_chat( $api_key, $model, $messages, $temperature, $max_tokens ) {
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    
    $payload = [
        'model'       => $model,
        'messages'    => array_values( $messages ),
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
    ];
    
    $res = wp_remote_post( $endpoint, [...] );
    
    // Normalización de respuesta
    return [ 
        'message' => $text, 
        'usage' => [
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens' => $total_tokens
        ]
    ];
}
```

**✅ Fortalezas:**
- Formato de respuesta consistente (`message` + `usage`)
- Extracción de tokens unificada (prompt/completion/total)
- Manejo de errores estandarizado
- Timeout configurado (45s)

**❌ Limitaciones:**
- Asunen protocolo HTTP/REST (no local)
- Hardcoded endpoints
- Sin soporte para streaming
- No declaración de capacidades (qué soporta cada proveedor)

### 2.3 Configuración de Bots

**Tabla:** `wp_aichat_bots`

**Campos relevantes para proveedores:**
```sql
provider VARCHAR(32) NOT NULL DEFAULT 'openai',
model VARCHAR(64) NOT NULL DEFAULT 'gpt-4o',
temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
max_tokens INT NOT NULL DEFAULT 2048,
reasoning ENUM('off','fast','accurate') NOT NULL DEFAULT 'off',
verbosity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
tools_json LONGTEXT NULL
```

**Validación (bots_ajax.php líneas 143-170):**
```php
if ( $prov === 'anthropic' ) {
    if ( strpos($model, 'claude-') !== 0 ) {
        $out['model'] = 'claude-3-5-sonnet-20240620'; // auto-fix
    }
}
if ( $prov === 'openai' ) {
    if ( strpos($model, 'claude-') === 0 ) {
        $out['provider'] = 'anthropic'; // auto-switch
    }
}
```

**❌ Problema:** Validación hardcodeada por proveedor, no escalable.

### 2.4 Gestión de Settings

**Ubicación:** `includes/settings.php` (líneas 116-129)

**Patrón actual:**
```php
register_setting( 'aichat_settings', 'aichat_openai_api_key', [
    'type'              => 'string',
    'sanitize_callback' => 'aichat_sanitize_api_key', // ✅ Ahora con encriptación
    'default'           => '',
] );

register_setting( 'aichat_settings', 'aichat_claude_api_key', [
    'type'              => 'string',
    'sanitize_callback' => 'aichat_sanitize_api_key',
    'default'           => '',
] );
```

**❌ Problemas:**
- Registro manual por cada proveedor
- No escalable (cada nuevo proveedor necesita código nuevo)
- UI de settings también hardcodeada

**✅ Oportunidad:** Sistema de settings dinámicos basado en proveedores activos.

### 2.5 Logging y Analytics

**Tabla:** `wp_aichat_conversations`

**Campos de tracking:**
```sql
model VARCHAR(100) NULL,
provider VARCHAR(40) NULL,
prompt_tokens INT UNSIGNED NULL,
completion_tokens INT UNSIGNED NULL,
total_tokens INT UNSIGNED NULL,
cost_micros BIGINT NULL
```

**Tabla agregada:** `wp_aichat_usage_daily`
```sql
PRIMARY KEY(date, provider, model)
```

**✅ Fortaleza:** Schema ya preparado para múltiples proveedores (campo `provider` genérico).

### 2.6 Normalización de Respuestas

**Formato estándar actual:**
```php
return [
    'message' => string,          // Texto de la respuesta
    'usage' => [
        'prompt_tokens' => int,   // Tokens de entrada
        'completion_tokens' => int, // Tokens de salida
        'total_tokens' => int     // Total
    ]
];

// En caso de error:
return [ 'error' => string ];
```

**Extracción unificada (OpenAI):**
```php
$prompt_tokens = isset($u['prompt_tokens']) 
    ? (int)$u['prompt_tokens'] 
    : ( isset($u['input_tokens']) ? (int)$u['input_tokens'] : null );
```

**✅ Ventaja:** Ya hay un contrato de respuesta, solo necesita formalizarse en interfaz.

---

## 3. SISTEMA DE ADD-ONS EXISTENTE

### 3.1 Patrón Analizado (MCP Add-on)

**Estructura:**
```
includes/add-ons/
└── mcp/
    ├── loader.php                    # Punto de entrada
    ├── class-mcp-transport.php       # Base para transportes
    ├── transports/
    │   └── class-http-transport.php  # Transporte HTTP/HTTPS
    ├── class-mcp-client-manager.php
    ├── integration.php
    ├── admin-settings.php            # UI admin
    └── admin-ajax.php                # AJAX handlers
```

**loader.php (patrón template):**
```php
<?php
// 1. Metadata function
function aichat_mcp_addon_info() {
    return [
        'id'          => 'mcp',
        'name'        => 'Model Context Protocol',
        'description' => 'Connect to MCP servers...',
        'version'     => '1.0.0',
        'requires'    => '1.0.0',
        'default'     => 0
    ];
}

// 2. Option check (early return si deshabilitado)
$enabled = get_option('aichat_addon_mcp_enabled', 0);
if ( ! $enabled ) { return; }

// 3. Component loading
require_once __DIR__ . '/transports/class-http-transport.php';
require_once __DIR__ . '/class-mcp-client-manager.php';
require_once __DIR__ . '/mcp-integration.php';

// 4. Admin-only UI
if ( is_admin() ) {
    require_once __DIR__ . '/mcp-admin-settings.php';
    require_once __DIR__ . '/mcp-admin-ajax.php';
}

// 5. Menu registration
add_action('admin_menu', function() {
    // Recheck option (por si cambia)
    if ( ! get_option('aichat_addon_mcp_enabled', 0) ) { return; }
    
    add_submenu_page(
        'aichat-settings',
        'MCP Servers',
        'MCP Servers',
        'manage_options',
        'aichat-mcp',
        'aichat_mcp_settings_page'
    );
}, 20);
```

**Activación en core (`axiachat-ai.php` líneas 85-99):**
```php
$ai_tools_enabled = get_option('aichat_addon_ai_tools_enabled', 1);
if ($ai_tools_enabled) {
    $addon_dir = AICHAT_PLUGIN_DIR . 'includes/add-ons/ai-tools/';
    require_once $addon_dir . 'api.php';
    require_once $addon_dir . 'macro-api.php';
    
    // Nested dependency
    $mcp_enabled = get_option('aichat_addon_mcp_enabled', 0);
    if ($mcp_enabled) {
        require_once AICHAT_PLUGIN_DIR . 'includes/add-ons/mcp/loader.php';
    }
}
```

**✅ Ventajas del patrón:**
- **Modular**: Código completamente separado del core
- **Condicional**: Solo carga si está enabled
- **Mantenible**: Un directorio = un add-on
- **Metadata**: Info estructurada para UI/gestión
- **Limpio**: Early return evita carga innecesaria

**🎯 Aplicación a proveedores:**
Este patrón es **perfecto** para proveedores modulares.

---

## 4. ARQUITECTURA MODULAR PROPUESTA

### 4.1 Concepto General

**Principio:** Cada proveedor AI es un **add-on independiente** con:
- Adapter class que implementa interfaz común
- Metadata (modelos, capabilities, settings schema)
- Auto-registro en sistema central
- Activación via opción

**Flujo:**
```
Bot config (provider='ollama')
    ↓
Provider Registry → Obtener adapter registrado
    ↓
Factory Pattern → Instanciar adapter específico
    ↓
Adapter Interface → chat($messages, $params)
    ↓
Response Normalizer → Formato estándar
```

### 4.2 Interfaz de Proveedor (Propuesta)

**Archivo:** `includes/interfaces/interface-aichat-provider.php`

```php
<?php
/**
 * Interfaz para proveedores AI
 * Todos los adapters de proveedores deben implementar esta interfaz
 */
interface AIChat_Provider_Interface {
    
    /**
     * Inicializar proveedor con configuración
     * 
     * @param array $config Configuración del proveedor (API keys, endpoints, etc.)
     */
    public function __construct( $config = [] );
    
    /**
     * Obtener ID único del proveedor
     * 
     * @return string ID del proveedor (ej: 'openai', 'claude', 'ollama')
     */
    public function get_id();
    
    /**
     * Obtener nombre para mostrar
     * 
     * @return string Nombre del proveedor (ej: 'OpenAI', 'Anthropic Claude')
     */
    public function get_name();
    
    /**
     * Obtener lista de modelos disponibles
     * 
     * @return array Array de modelos [
     *   'gpt-4o' => ['name'=>'GPT-4 Optimized', 'context'=>128000, 'supports_tools'=>true],
     *   ...
     * ]
     */
    public function get_models();
    
    /**
     * Validar si un modelo es soportado por este proveedor
     * 
     * @param string $model ID del modelo
     * @return bool True si soportado
     */
    public function supports_model( $model );
    
    /**
     * Obtener capacidades del proveedor
     * 
     * @return array [
     *   'tools' => bool,           // Soporta function calling
     *   'streaming' => bool,       // Soporta streaming
     *   'vision' => bool,          // Soporta imágenes
     *   'reasoning' => bool,       // Soporta reasoning (o1)
     *   'local' => bool            // Es modelo local (no HTTP)
     * ]
     */
    public function get_capabilities();
    
    /**
     * Obtener schema de settings para este proveedor
     * 
     * @return array [
     *   'api_key' => [
     *     'type' => 'string',
     *     'label' => 'API Key',
     *     'required' => true,
     *     'encrypted' => true
     *   ],
     *   'endpoint' => [...],
     *   ...
     * ]
     */
    public function get_settings_schema();
    
    /**
     * Llamada principal al modelo (chat completion)
     * 
     * @param array $messages Array de mensajes formato OpenAI
     * @param array $params Parámetros [
     *   'model' => string,
     *   'temperature' => float,
     *   'max_tokens' => int,
     *   'tools' => array (opcional),
     *   'stream' => bool (opcional)
     * ]
     * @return array [
     *   'message' => string,              // Texto respuesta
     *   'usage' => [                      // Uso de tokens
     *     'prompt_tokens' => int,
     *     'completion_tokens' => int,
     *     'total_tokens' => int
     *   ],
     *   'finish_reason' => string,        // 'stop', 'length', 'tool_calls', etc.
     *   'tool_calls' => array (opcional), // Si hay llamadas a tools
     * ] 
     * 
     * En caso de error:
     * @return array [ 'error' => string ]
     */
    public function chat( $messages, $params = [] );
    
    /**
     * Validar configuración del proveedor
     * 
     * @return bool|WP_Error True si válido, WP_Error si falla
     */
    public function validate_config();
    
    /**
     * Calcular coste en microcents (si aplica)
     * 
     * @param array $usage Array con prompt_tokens, completion_tokens
     * @param string $model ID del modelo
     * @return int|null Coste en microcents (1 cent = 10000 micros), null si no calculable
     */
    public function calculate_cost( $usage, $model );
}
```

### 4.3 Clase Base Abstracta (Opcional)

**Archivo:** `includes/abstract/abstract-aichat-provider.php`

```php
<?php
/**
 * Clase base abstracta para proveedores
 * Implementa funcionalidad común y deja métodos específicos abstractos
 */
abstract class AIChat_Provider_Base implements AIChat_Provider_Interface {
    
    protected $config = [];
    protected $id = '';
    protected $name = '';
    
    public function __construct( $config = [] ) {
        $this->config = $config;
    }
    
    public function get_id() {
        return $this->id;
    }
    
    public function get_name() {
        return $this->name;
    }
    
    public function supports_model( $model ) {
        $models = $this->get_models();
        return isset( $models[ $model ] );
    }
    
    public function validate_config() {
        $schema = $this->get_settings_schema();
        foreach ( $schema as $key => $field ) {
            if ( ! empty( $field['required'] ) && empty( $this->config[ $key ] ) ) {
                return new WP_Error(
                    'missing_config',
                    sprintf( __( 'Missing required config: %s', 'axiachat-ai' ), $field['label'] )
                );
            }
        }
        return true;
    }
    
    /**
     * Helper: Normalizar respuesta HTTP a formato estándar
     */
    protected function normalize_http_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg = $body['error']['message'] ?? __( 'API error', 'axiachat-ai' );
            return [ 'error' => $msg ];
        }
        
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
    
    /**
     * Helper: Logging unificado
     */
    protected function log( $message, $context = [] ) {
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( "[Provider:{$this->id}] {$message}", $context, true );
        }
    }
    
    // Métodos abstractos (obligatorios en child classes)
    abstract public function get_models();
    abstract public function get_capabilities();
    abstract public function get_settings_schema();
    abstract public function chat( $messages, $params = [] );
    abstract public function calculate_cost( $usage, $model );
}
```

### 4.4 Sistema de Registry

**Archivo:** `includes/class-aichat-provider-registry.php`

```php
<?php
/**
 * Registro central de proveedores AI
 * Gestiona registro, activación y factory pattern
 */
class AIChat_Provider_Registry {
    
    private static $instance = null;
    private $providers = [];
    
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook para que add-ons registren sus proveedores
        do_action( 'aichat_register_providers', $this );
    }
    
    /**
     * Registrar un proveedor
     * 
     * @param string $id ID único del proveedor
     * @param string $class_name Nombre de la clase adapter
     * @param array $metadata Info del proveedor
     */
    public function register( $id, $class_name, $metadata = [] ) {
        if ( ! class_exists( $class_name ) ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "Provider class not found: {$class_name}", [], true );
            }
            return false;
        }
        
        $this->providers[ $id ] = [
            'class'    => $class_name,
            'metadata' => $metadata,
            'enabled'  => get_option( "aichat_provider_{$id}_enabled", 0 )
        ];
        
        aichat_log_debug( "Registered provider: {$id}", ['class' => $class_name], true );
        return true;
    }
    
    /**
     * Obtener instancia de un proveedor
     * 
     * @param string $id ID del proveedor
     * @param array $config Configuración a pasar al constructor
     * @return AIChat_Provider_Interface|null
     */
    public function get( $id, $config = [] ) {
        if ( ! isset( $this->providers[ $id ] ) ) {
            return null;
        }
        
        $provider_data = $this->providers[ $id ];
        $class = $provider_data['class'];
        
        // Factory pattern
        try {
            $instance = new $class( $config );
            
            // Validar que implementa la interfaz
            if ( ! $instance instanceof AIChat_Provider_Interface ) {
                aichat_log_debug( "Provider {$id} doesn't implement interface", [], true );
                return null;
            }
            
            return $instance;
            
        } catch ( Exception $e ) {
            aichat_log_debug( "Failed to instantiate provider {$id}: " . $e->getMessage(), [], true );
            return null;
        }
    }
    
    /**
     * Listar todos los proveedores registrados
     * 
     * @param bool $enabled_only Solo proveedores enabled
     * @return array
     */
    public function get_all( $enabled_only = false ) {
        if ( ! $enabled_only ) {
            return $this->providers;
        }
        
        return array_filter( $this->providers, function( $p ) {
            return ! empty( $p['enabled'] );
        });
    }
    
    /**
     * Verificar si un proveedor existe y está enabled
     * 
     * @param string $id ID del proveedor
     * @return bool
     */
    public function is_available( $id ) {
        return isset( $this->providers[ $id ] ) && ! empty( $this->providers[ $id ]['enabled'] );
    }
}
```

### 4.5 Ejemplo: Adapter OpenAI

**Archivo:** `includes/add-ons/providers/openai/class-openai-provider.php`

```php
<?php
/**
 * Adapter para OpenAI API
 * Refactorización del código existente a estructura modular
 */
class AIChat_OpenAI_Provider extends AIChat_Provider_Base {
    
    protected $id = 'openai';
    protected $name = 'OpenAI';
    
    public function get_models() {
        return [
            'gpt-4o' => [
                'name'           => 'GPT-4 Optimized',
                'context_window' => 128000,
                'supports_tools' => true,
                'supports_vision' => true,
                'cost_per_1k_prompt' => 2.50,      // USD
                'cost_per_1k_completion' => 10.00
            ],
            'gpt-4o-mini' => [
                'name'           => 'GPT-4o Mini',
                'context_window' => 128000,
                'supports_tools' => true,
                'supports_vision' => true,
                'cost_per_1k_prompt' => 0.15,
                'cost_per_1k_completion' => 0.60
            ],
            'o1-preview' => [
                'name'           => 'O1 Preview (Reasoning)',
                'context_window' => 128000,
                'supports_tools' => false,
                'reasoning'      => true,
                'cost_per_1k_prompt' => 15.00,
                'cost_per_1k_completion' => 60.00
            ],
            // ... más modelos
        ];
    }
    
    public function get_capabilities() {
        return [
            'tools'     => true,
            'streaming' => true,
            'vision'    => true,
            'reasoning' => true, // Modelos o1
            'local'     => false
        ];
    }
    
    public function get_settings_schema() {
        return [
            'api_key' => [
                'type'      => 'string',
                'label'     => __( 'OpenAI API Key', 'axiachat-ai' ),
                'required'  => true,
                'encrypted' => true,
                'help'      => __( 'Get your API key from platform.openai.com', 'axiachat-ai' )
            ],
            'organization' => [
                'type'     => 'string',
                'label'    => __( 'Organization ID (optional)', 'axiachat-ai' ),
                'required' => false
            ]
        ];
    }
    
    public function chat( $messages, $params = [] ) {
        $model = $params['model'] ?? 'gpt-4o';
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2048;
        $tools = $params['tools'] ?? null;
        
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $payload = [
            'model'       => $model,
            'messages'    => array_values( $messages ),
            'temperature' => (float) $temperature,
            'max_tokens'  => (int) $max_tokens
        ];
        
        if ( ! empty( $tools ) ) {
            $payload['tools'] = $tools;
        }
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type'  => 'application/json'
        ];
        
        if ( ! empty( $this->config['organization'] ) ) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }
        
        $this->log( 'Calling OpenAI chat', [
            'model' => $model,
            'messages_count' => count($messages),
            'has_tools' => !empty($tools)
        ]);
        
        $response = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 45
        ]);
        
        $data = $this->normalize_http_response( $response );
        if ( isset( $data['error'] ) ) {
            return $data; // Error ya normalizado
        }
        
        // Extraer respuesta
        $choice = $data['choices'][0] ?? null;
        if ( ! $choice ) {
            return [ 'error' => __( 'Empty response from OpenAI', 'axiachat-ai' ) ];
        }
        
        $message = $choice['message']['content'] ?? '';
        $finish_reason = $choice['finish_reason'] ?? 'stop';
        $tool_calls = $choice['message']['tool_calls'] ?? null;
        
        // Normalizar usage
        $usage_raw = $data['usage'] ?? [];
        $usage = [
            'prompt_tokens'     => $usage_raw['prompt_tokens'] ?? $usage_raw['input_tokens'] ?? 0,
            'completion_tokens' => $usage_raw['completion_tokens'] ?? $usage_raw['output_tokens'] ?? 0,
            'total_tokens'      => $usage_raw['total_tokens'] ?? 0
        ];
        
        if ( $usage['total_tokens'] === 0 && $usage['prompt_tokens'] > 0 ) {
            $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];
        }
        
        $result = [
            'message'       => $message,
            'usage'         => $usage,
            'finish_reason' => $finish_reason
        ];
        
        if ( $tool_calls ) {
            $result['tool_calls'] = $tool_calls;
        }
        
        return $result;
    }
    
    public function calculate_cost( $usage, $model ) {
        $models = $this->get_models();
        if ( ! isset( $models[ $model ] ) ) {
            return null;
        }
        
        $model_info = $models[ $model ];
        $prompt_cost = $model_info['cost_per_1k_prompt'] ?? 0;
        $completion_cost = $model_info['cost_per_1k_completion'] ?? 0;
        
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        
        // Calcular en USD cents
        $cost_usd = ( $prompt_tokens / 1000 * $prompt_cost ) + 
                    ( $completion_tokens / 1000 * $completion_cost );
        
        // Convertir a microcents (1 cent = 10000 micros)
        return (int) round( $cost_usd * 100 * 10000 );
    }
}
```

### 4.6 Ejemplo: Adapter Ollama (Local)

**Archivo:** `includes/add-ons/providers/ollama/class-ollama-provider.php`

```php
<?php
/**
 * Adapter para Ollama (modelos locales)
 * Demuestra soporte para AI local sin API keys
 */
class AIChat_Ollama_Provider extends AIChat_Provider_Base {
    
    protected $id = 'ollama';
    protected $name = 'Ollama (Local)';
    
    public function get_models() {
        // En una implementación real, esto podría hacer GET /api/tags al endpoint
        // Por ahora retornamos modelos comunes
        return [
            'llama3.3:70b' => [
                'name'           => 'Llama 3.3 70B',
                'context_window' => 128000,
                'supports_tools' => true,
                'local'          => true
            ],
            'llama3.2' => [
                'name'           => 'Llama 3.2',
                'context_window' => 128000,
                'supports_tools' => false,
                'local'          => true
            ],
            'mistral' => [
                'name'           => 'Mistral 7B',
                'context_window' => 32000,
                'supports_tools' => false,
                'local'          => true
            ],
            'gemma2:27b' => [
                'name'           => 'Gemma 2 27B',
                'context_window' => 8000,
                'supports_tools' => false,
                'local'          => true
            ]
        ];
    }
    
    public function get_capabilities() {
        return [
            'tools'     => true,  // Algunos modelos soportan tools
            'streaming' => true,
            'vision'    => false, // Depende del modelo (llava sí)
            'reasoning' => false,
            'local'     => true   // 🔥 CLAVE: Es modelo local
        ];
    }
    
    public function get_settings_schema() {
        return [
            'endpoint' => [
                'type'        => 'url',
                'label'       => __( 'Ollama Endpoint', 'axiachat-ai' ),
                'required'    => true,
                'default'     => 'http://localhost:11434',
                'help'        => __( 'URL of your Ollama server (default: http://localhost:11434)', 'axiachat-ai' ),
                'placeholder' => 'http://localhost:11434'
            ],
            'timeout' => [
                'type'    => 'number',
                'label'   => __( 'Timeout (seconds)', 'axiachat-ai' ),
                'default' => 120,
                'help'    => __( 'Local models may need longer timeout for first run', 'axiachat-ai' )
            ]
        ];
    }
    
    public function chat( $messages, $params = [] ) {
        $model = $params['model'] ?? 'llama3.3:70b';
        $temperature = $params['temperature'] ?? 0.7;
        $endpoint = rtrim( $this->config['endpoint'], '/' ) . '/api/chat';
        $timeout = $this->config['timeout'] ?? 120;
        
        // Ollama usa formato diferente (system como primer mensaje)
        $ollama_messages = [];
        foreach ( $messages as $msg ) {
            $ollama_messages[] = [
                'role'    => $msg['role'],
                'content' => is_array($msg['content']) ? implode("\n", array_column($msg['content'], 'text')) : $msg['content']
            ];
        }
        
        $payload = [
            'model'    => $model,
            'messages' => $ollama_messages,
            'stream'   => false,
            'options'  => [
                'temperature' => (float) $temperature,
                'num_predict' => (int) ($params['max_tokens'] ?? 2048)
            ]
        ];
        
        $this->log( 'Calling Ollama', [
            'endpoint' => $endpoint,
            'model'    => $model,
            'timeout'  => $timeout
        ]);
        
        $response = wp_remote_post( $endpoint, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => $timeout // 🔥 Timeout más largo para modelos locales
        ]);
        
        $data = $this->normalize_http_response( $response );
        if ( isset( $data['error'] ) ) {
            return $data;
        }
        
        $message = $data['message']['content'] ?? '';
        if ( empty( $message ) ) {
            return [ 'error' => __( 'Empty response from Ollama', 'axiachat-ai' ) ];
        }
        
        // Ollama no siempre reporta tokens, estimamos si no hay
        $prompt_tokens = $data['prompt_eval_count'] ?? 0;
        $completion_tokens = $data['eval_count'] ?? 0;
        
        return [
            'message'       => $message,
            'usage'         => [
                'prompt_tokens'     => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'total_tokens'      => $prompt_tokens + $completion_tokens
            ],
            'finish_reason' => 'stop',
            'model_info'    => [
                'loaded_at' => $data['loaded_at'] ?? null,
                'total_duration' => $data['total_duration'] ?? null
            ]
        ];
    }
    
    public function calculate_cost( $usage, $model ) {
        // Modelos locales no tienen coste por token
        return 0;
    }
    
    public function validate_config() {
        $endpoint = $this->config['endpoint'] ?? '';
        if ( empty( $endpoint ) ) {
            return new WP_Error( 'missing_endpoint', __( 'Ollama endpoint is required', 'axiachat-ai' ) );
        }
        
        // Opcional: Verificar conectividad
        $response = wp_remote_get( rtrim($endpoint, '/') . '/api/tags', [
            'timeout' => 5
        ]);
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 
                'connection_failed', 
                __( 'Cannot connect to Ollama server', 'axiachat-ai' ) . ': ' . $response->get_error_message()
            );
        }
        
        return true;
    }
}
```

### 4.7 Estructura de Add-on para Proveedores

**Directorio:** `includes/add-ons/providers/{provider-id}/`

```
includes/add-ons/providers/
├── openai/
│   ├── loader.php                        # Entry point + registro
│   ├── class-openai-provider.php         # Adapter implementation
│   └── admin-settings.php                # UI específica (opcional)
├── claude/
│   ├── loader.php
│   ├── class-claude-provider.php
│   └── admin-settings.php
├── ollama/
│   ├── loader.php
│   ├── class-ollama-provider.php
│   └── admin-settings.php
├── google/
│   ├── loader.php
│   └── class-google-provider.php
└── huggingface/
    ├── loader.php
    └── class-huggingface-provider.php
```

**Ejemplo loader.php (OpenAI):**
```php
<?php
/**
 * OpenAI Provider Add-on
 * Registers OpenAI as modular AI provider
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Metadata
function aichat_provider_openai_info() {
    return [
        'id'          => 'openai',
        'name'        => 'OpenAI',
        'description' => __( 'Official OpenAI API (GPT-4, o1, etc.)', 'axiachat-ai' ),
        'version'     => '1.0.0',
        'type'        => 'cloud',
        'requires'    => '1.0.0',
        'default'     => 1 // Enabled por defecto
    ];
}

// Option check
$enabled = get_option( 'aichat_provider_openai_enabled', 1 );
if ( ! $enabled ) { return; }

// Load adapter class
require_once __DIR__ . '/class-openai-provider.php';

// Register provider en el registry
add_action( 'aichat_register_providers', function( $registry ) {
    $registry->register( 
        'openai', 
        'AIChat_OpenAI_Provider',
        aichat_provider_openai_info()
    );
});

// Admin settings UI (opcional, puede ser genérica)
if ( is_admin() ) {
    // UI podría ser generada automáticamente desde get_settings_schema()
}
```

---

## 5. ESTRATEGIA DE IMPLEMENTACIÓN

### 5.1 Fases de Migración

#### **FASE 1: Fundación (No Breaking Changes)**
**Objetivo:** Crear infraestructura sin romper código existente

**Tareas:**
1. ✅ Crear interfaz `AIChat_Provider_Interface`
2. ✅ Crear clase base `AIChat_Provider_Base`
3. ✅ Crear `AIChat_Provider_Registry` singleton
4. ✅ Crear estructura de directorios `add-ons/providers/`
5. ✅ Documentar contrato de respuesta estándar

**Validación:** Plugin funciona igual que antes, nuevas clases aún no usadas.

---

#### **FASE 2: Refactorización OpenAI/Claude a Adapters**
**Objetivo:** Migrar proveedores existentes a nueva arquitectura

**Tareas:**
1. ✅ Extraer código OpenAI → `AIChat_OpenAI_Provider`
   - Mover `call_openai_chat()` → método `chat()`
   - Mover `call_openai_auto()` → soporte tools
   - Mover `call_openai_responses()` → reasoning flag
   - Implementar `get_models()`, `get_capabilities()`, etc.

2. ✅ Extraer código Claude → `AIChat_Claude_Provider`
   - Mover `call_claude_messages()` → método `chat()`
   - Normalizar system message handling
   - Fallback chain en `chat()`

3. ✅ Crear loaders en `add-ons/providers/openai/` y `/claude/`

4. ✅ Modificar `class-aichat-ajax.php::process_message()`:
   ```php
   // ANTES:
   if ( $provider === 'openai' ) {
       $result = $this->call_openai_chat(...);
   } elseif ( $provider === 'claude' ) {
       $result = $this->call_claude_messages(...);
   }
   
   // DESPUÉS:
   $registry = AIChat_Provider_Registry::instance();
   $provider_adapter = $registry->get( $provider, [
       'api_key' => aichat_get_setting( "aichat_{$provider}_api_key" )
   ]);
   
   if ( ! $provider_adapter ) {
       wp_send_json_error( ['message' => 'Provider not available'], 500 );
   }
   
   $result = $provider_adapter->chat( $messages, [
       'model'       => $model,
       'temperature' => $temperature,
       'max_tokens'  => $max_tokens,
       'tools'       => $tools_array
   ]);
   ```

5. ✅ Mantener métodos legacy como wrappers (deprecation notices):
   ```php
   /**
    * @deprecated Use AIChat_Provider_Registry instead
    */
   protected function call_openai_chat( $api_key, $model, $messages, $temperature, $max_tokens ) {
       _deprecated_function( __METHOD__, '2.0.0', 'AIChat_Provider_Registry::get()->chat()' );
       
       $provider = AIChat_Provider_Registry::instance()->get('openai', ['api_key' => $api_key]);
       return $provider->chat( $messages, ['model'=>$model, 'temperature'=>$temperature, 'max_tokens'=>$max_tokens] );
   }
   ```

**Validación:** 
- Todos los bots existentes funcionan igual
- Tests unitarios pasan
- No errores en logs

---

#### **FASE 3: Sistema de Settings Dinámicos**
**Objetivo:** Auto-registro de settings basado en proveedores activos

**Tareas:**
1. ✅ Modificar `includes/settings.php`:
   ```php
   add_action( 'admin_init', 'aichat_register_provider_settings' );
   function aichat_register_provider_settings() {
       $registry = AIChat_Provider_Registry::instance();
       $providers = $registry->get_all( true ); // Solo enabled
       
       foreach ( $providers as $id => $data ) {
           $provider = $registry->get( $id );
           $schema = $provider->get_settings_schema();
           
           foreach ( $schema as $key => $field ) {
               $option_name = "aichat_provider_{$id}_{$key}";
               
               register_setting( 'aichat_settings', $option_name, [
                   'type'              => $field['type'],
                   'sanitize_callback' => ! empty($field['encrypted']) ? 'aichat_sanitize_api_key' : 'sanitize_text_field',
                   'default'           => $field['default'] ?? ''
               ]);
           }
       }
   }
   ```

2. ✅ Crear UI genérica para renderizar settings:
   ```php
   function aichat_render_provider_settings( $provider_id ) {
       $registry = AIChat_Provider_Registry::instance();
       $provider = $registry->get( $provider_id );
       $schema = $provider->get_settings_schema();
       
       foreach ( $schema as $key => $field ) {
           $option_name = "aichat_provider_{$provider_id}_{$key}";
           $value = aichat_get_setting( $option_name );
           
           // Render según field['type']
           switch ( $field['type'] ) {
               case 'string':
               case 'url':
                   echo '<input type="text" name="'.$option_name.'" value="'.esc_attr($value).'" />';
                   break;
               case 'number':
                   echo '<input type="number" name="'.$option_name.'" value="'.esc_attr($value).'" />';
                   break;
               // ... más tipos
           }
       }
   }
   ```

3. ✅ UI de gestión de proveedores (enable/disable):
   - Listado con toggle on/off
   - Expandible para settings específicos
   - Botón "Test Connection" por proveedor

**Validación:** Settings se crean automáticamente al activar proveedor.

---

#### **FASE 4: Implementar Proveedores Nuevos**
**Objetivo:** Demostrar extensibilidad con nuevos proveedores

**Ejemplos a implementar:**
1. ✅ **Ollama** (local) - Ya diseñado arriba
2. ✅ **Google Gemini** (cloud)
3. ✅ **Anthropic Claude** (ya migrado de fase 2)
4. ⏳ **Hugging Face Inference API**
5. ⏳ **LM Studio** (local)
6. ⏳ **vLLM** (local/self-hosted)

**Proceso por proveedor:**
1. Crear carpeta `add-ons/providers/{id}/`
2. Implementar adapter class extendiendo `AIChat_Provider_Base`
3. Crear `loader.php` con registro
4. Agregar loader al core (opcional, puede ser auto-discovery)
5. Testing

**Validación:** Cada proveedor funciona independientemente.

---

#### **FASE 5: Características Avanzadas**
**Objetivo:** Aprovechar arquitectura modular para features nuevas

**Posibles mejoras:**
1. **Streaming responses** - Interface `stream()` method
2. **Model selection UI** - Dropdown dinámico según proveedor activo
3. **Cost tracking** - Usar `calculate_cost()` para analytics
4. **Fallback chains** - Si proveedor falla, intentar siguiente
5. **Load balancing** - Distribuir entre múltiples API keys
6. **Provider health checks** - Monitor uptime/latency
7. **A/B testing** - Comparar respuestas entre proveedores

---

### 5.2 Compatibilidad hacia Atrás

**Estrategia:**
1. **Bots existentes siguen funcionando:**
   - Campo `provider` en DB ya existe
   - Valores 'openai' y 'claude' reconocidos por registry
   - No migración de datos necesaria

2. **Settings legacy soportados:**
   ```php
   // Si existe aichat_openai_api_key (legacy), usarlo como fallback
   $api_key = aichat_get_setting( 'aichat_provider_openai_api_key' ) ?: 
              aichat_get_setting( 'aichat_openai_api_key' );
   ```

3. **Deprecation gradual:**
   - Fase 2: Legacy methods con `_deprecated_function()`
   - Fase 3: Notices en admin si usa settings old
   - Fase 4: (Futuro) Eliminar código legacy

**Migration helper (opcional):**
```php
add_action( 'admin_init', 'aichat_migrate_legacy_settings' );
function aichat_migrate_legacy_settings() {
    if ( get_option( 'aichat_settings_migrated_v2' ) ) { return; }
    
    // Migrar OpenAI key
    $old_openai = get_option( 'aichat_openai_api_key' );
    if ( $old_openai && ! get_option( 'aichat_provider_openai_api_key' ) ) {
        update_option( 'aichat_provider_openai_api_key', $old_openai );
    }
    
    // Migrar Claude key
    $old_claude = get_option( 'aichat_claude_api_key' );
    if ( $old_claude && ! get_option( 'aichat_provider_claude_api_key' ) ) {
        update_option( 'aichat_provider_claude_api_key', $old_claude );
    }
    
    update_option( 'aichat_settings_migrated_v2', 1 );
}
```

---

### 5.3 Testing Plan

#### **Unit Tests (PHPUnit)**
```php
class Test_Provider_OpenAI extends WP_UnitTestCase {
    
    public function test_implements_interface() {
        $provider = new AIChat_OpenAI_Provider(['api_key' => 'test']);
        $this->assertInstanceOf( AIChat_Provider_Interface::class, $provider );
    }
    
    public function test_get_models_returns_array() {
        $provider = new AIChat_OpenAI_Provider(['api_key' => 'test']);
        $models = $provider->get_models();
        $this->assertIsArray( $models );
        $this->assertNotEmpty( $models );
    }
    
    public function test_supports_model() {
        $provider = new AIChat_OpenAI_Provider(['api_key' => 'test']);
        $this->assertTrue( $provider->supports_model('gpt-4o') );
        $this->assertFalse( $provider->supports_model('invalid-model') );
    }
    
    public function test_chat_requires_api_key() {
        $provider = new AIChat_OpenAI_Provider([]);
        $result = $provider->chat( [['role'=>'user','content'=>'test']] );
        $this->assertArrayHasKey( 'error', $result );
    }
}
```

#### **Integration Tests**
1. **Registry registration:**
   - Registrar proveedor → Debe aparecer en `get_all()`
   - Desactivar proveedor → No debe estar en `get_all(true)`

2. **Factory pattern:**
   - `get('openai')` → Devuelve instancia correcta
   - `get('invalid')` → Devuelve null

3. **End-to-end:**
   - Crear bot con provider='ollama'
   - Enviar mensaje → Debe usar adapter Ollama
   - Verificar response format estándar

#### **Manual QA Checklist**
- [ ] Bot existente (OpenAI) funciona sin cambios
- [ ] Bot existente (Claude) funciona sin cambios
- [ ] Crear nuevo bot con proveedor nuevo (Ollama)
- [ ] Settings page muestra campos dinámicos por proveedor
- [ ] API key encryption funciona con nuevos proveedores
- [ ] Logs conversation table registra provider correcto
- [ ] Usage analytics funciona por proveedor
- [ ] Error handling muestra mensajes claros
- [ ] Desactivar proveedor → Bots con ese proveedor muestran error

---

## 6. RIESGOS Y MITIGACIONES

### 6.1 Riesgos Técnicos

| Riesgo | Impacto | Probabilidad | Mitigación |
|--------|---------|--------------|------------|
| **Breaking changes en bots existentes** | Alto | Media | Mantener legacy wrappers, testing exhaustivo, rollback plan |
| **Performance degradation** | Medio | Baja | Registry usa singleton, factory es ligero, benchmarking |
| **Complejidad aumentada** | Medio | Alta | Documentación clara, ejemplos, convención sobre configuración |
| **Fragmentación de código** | Bajo | Media | Interfaz estricta, code reviews, linting |
| **Dependencias entre providers** | Bajo | Baja | Providers completamente aislados, sin shared state |

### 6.2 Riesgos de Negocio

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| **Usuarios confundidos por cambios UI** | Medio | Changelog claro, migration wizard, tooltips |
| **Coste de soporte aumenta** | Medio | Docs completas, troubleshooting guide, debug mode |
| **Adopción lenta de nuevos proveedores** | Bajo | Highlight en release notes, ejemplos de uso |

### 6.3 Plan de Rollback

**Si algo falla en producción:**
1. **Fase 1-2:** No afecta producción (solo código nuevo)
2. **Fase 3:** Rollback settings registration, legacy keys siguen funcionando
3. **Fase 4:** Desactivar provider específico sin afectar otros
4. **Fase 5:** Features opcionales, pueden deshabilitarse

**Estrategia:**
- Feature flags para activar/desactivar nueva arquitectura
- Mantener legacy code hasta confirmar estabilidad (2-3 releases)
- Versioning semántico (v2.0.0 para breaking changes)

---

## 7. CONCLUSIONES

### 7.1 Estado Actual vs Propuesto

| Aspecto | Actual | Propuesto |
|---------|--------|-----------|
| **Arquitectura** | Monolítica (2500+ líneas) | Modular (providers aislados) |
| **Proveedores** | 2 hardcoded | Ilimitados extensibles |
| **Extensibilidad** | Modificar core | Crear add-on |
| **Mantenibilidad** | Código acoplado | Separación de concerns |
| **Testing** | Difícil (dependencias) | Fácil (mocks, aislamiento) |
| **Local AI** | No soportado | Sí (Ollama, LM Studio) |
| **Onboarding nuevos devs** | Curva alta | Patrón claro |

### 7.2 Esfuerzo Estimado

| Fase | Tiempo | Complejidad | Riesgo |
|------|--------|-------------|--------|
| **Fase 1: Fundación** | 8-12 horas | Baja | Bajo |
| **Fase 2: Refactoring OpenAI/Claude** | 20-30 horas | Alta | Medio |
| **Fase 3: Settings dinámicos** | 12-16 horas | Media | Medio |
| **Fase 4: Nuevos providers (x3)** | 15-20 horas | Media | Bajo |
| **Fase 5: Features avanzadas** | 10-15 horas | Media | Bajo |
| **Testing + QA** | 10-15 horas | Media | - |
| **Total** | **75-108 horas** (~2-3 semanas) | - | - |

### 7.3 Recomendaciones

**✅ Proceder con implementación porque:**
1. **Arquitectura existente no es sostenible** - 2 proveedores ya requieren 2500+ líneas acopladas
2. **Demanda de local AI está creciendo** - Ollama, LM Studio muy populares
3. **Patrón add-on ya probado** - MCP/AI Tools demuestran viabilidad
4. **Beneficios superan riesgos** - Mejor mantenibilidad vale la inversión
5. **Migración puede ser gradual** - No requiere big-bang deployment

**⚠️ Consideraciones:**
- Empezar con **Fase 1-2** (refactoring) antes de añadir providers nuevos
- Mantener **backward compatibility** estricta
- Crear **documentación developer-friendly** con ejemplos
- Establecer **convención clara** para nuevos providers
- Considerar **marketplace futuro** de community providers

---

## 8. PRÓXIMOS PASOS

### Inmediatos (Esta semana)
1. **Revisar y aprobar** este análisis
2. **Decidir alcance** del MVP (¿solo Fase 1-2 o hasta Fase 4?)
3. **Crear branch** `feature/modular-providers`
4. **Setup testing environment** para no romper producción

### Corto plazo (Próximas 2 semanas)
1. Implementar **Fase 1** (interfaces, registry)
2. Implementar **Fase 2** (refactor OpenAI/Claude)
3. Testing exhaustivo con bots existentes
4. Code review + adjustments

### Medio plazo (Mes 1-2)
1. Implementar **Fase 3** (settings dinámicos)
2. Implementar **Fase 4** (Ollama + 1 provider más)
3. Beta testing con usuarios seleccionados
4. Preparar docs + changelog

### Largo plazo (Roadmap)
1. **Fase 5** features (streaming, health checks)
2. **Provider marketplace** (community contributions)
3. **Admin UI mejorada** para gestión de providers
4. **Analytics dashboard** por proveedor
5. **A/B testing framework** para comparar providers

---

## APÉNDICES

### A. Glosario

- **Provider:** Servicio AI (OpenAI, Claude, Ollama, etc.)
- **Adapter:** Clase que implementa interfaz común para un provider
- **Registry:** Sistema central que gestiona proveedores disponibles
- **Factory Pattern:** Patrón de diseño para instanciar proveedores dinámicamente
- **Add-on:** Módulo independiente que extiende funcionalidad core
- **Capabilities:** Características que soporta un proveedor (tools, streaming, etc.)

### B. Referencias

- **Código actual:**
  - `includes/class-aichat-ajax.php` - Main provider routing
  - `includes/bots_ajax.php` - Bot configuration
  - `includes/settings.php` - Settings registration
  
- **Patrones existentes:**
  - `includes/add-ons/mcp/loader.php` - Add-on loading pattern
  - `includes/add-ons/ai-tools/api.php` - Registry pattern

- **Documentación externa:**
  - OpenAI API: https://platform.openai.com/docs/api-reference
  - Anthropic Claude: https://docs.anthropic.com/claude/reference
  - Ollama API: https://github.com/ollama/ollama/blob/main/docs/api.md

### C. Diagrama de Flujo Propuesto

```
User sends message
    ↓
class-aichat-ajax.php::process_message()
    ↓
Extract bot config (provider='ollama', model='llama3.3:70b')
    ↓
AIChat_Provider_Registry::instance()
    ↓
Registry->get('ollama', ['endpoint'=>'http://localhost:11434'])
    ↓
Factory creates AIChat_Ollama_Provider instance
    ↓
Provider->validate_config() → ✅ OK
    ↓
Provider->chat($messages, $params)
    ↓
HTTP POST to local Ollama server
    ↓
Response normalization
    ↓
Return ['message'=>..., 'usage'=>[...]]
    ↓
Save to wp_aichat_conversations (provider='ollama', model='llama3.3:70b')
    ↓
Return to frontend
```

---

**Documento generado:** 2025  
**Autor:** AI Chat Development Team  
**Versión:** 1.0  
**Estado:** ✅ Listo para revisión y aprobación
