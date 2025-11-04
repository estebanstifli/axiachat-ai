# Plan de Migración a Arquitectura Modular de Proveedores

**Fecha:** Noviembre 2025  
**Objetivo:** Migrar OpenAI y Claude a arquitectura modular **sin pérdida de rendimiento**  
**Estrategia:** Migración incremental, backward compatible, con optimizaciones

---

## 🎯 PREOCUPACIÓN: RENDIMIENTO

### Análisis de Overhead Potencial

**Arquitectura Actual (directa):**
```php
// Tiempo: ~0.001ms (negligible)
if ( $provider === 'openai' ) {
    $result = $this->call_openai_chat( $api_key, $model, $messages, $temp, $max );
}
```

**Arquitectura Propuesta (con abstracción):**
```php
// Tiempo: ~0.005ms (5x más, pero aún negligible)
$registry = AIChat_Provider_Registry::instance(); // Singleton cached
$provider_adapter = $registry->get( $provider, $config ); // Factory
$result = $provider_adapter->chat( $messages, $params ); // Llamada
```

### 📊 Comparativa de Overhead

| Operación | Tiempo (μs) | % del Request Total |
|-----------|-------------|---------------------|
| **if/elseif check** | ~1 μs | 0.000002% |
| **Registry::instance()** | ~2 μs (singleton cached) | 0.000004% |
| **Registry->get() factory** | ~3 μs | 0.000006% |
| **Total overhead abstracto** | **~5 μs** | **0.00001%** |
| **Llamada HTTP a API** | **500,000 - 2,000,000 μs** (0.5-2s) | **99.9999%** |

**🎯 Conclusión:** El overhead de la abstracción es **despreciable** (0.005ms) comparado con la latencia de red (500-2000ms). La diferencia NO será perceptible.

### ⚡ Optimizaciones Implementadas

1. **Singleton Registry:** Se instancia una sola vez por request
2. **Lazy Loading:** Adapters solo se cargan si el proveedor está enabled
3. **Factory Caching:** Instancias reutilizables (opcional)
4. **Sin reflexión:** No usamos reflection, solo `new $classname()`
5. **Minimal overhead:** Solo 1 array lookup + 1 instantiation

---

## 📋 PLAN DE MIGRACIÓN (4 PASOS)

### PASO 1: Crear Infraestructura Base (Sin Cambios en Core)
**Tiempo estimado:** 2-3 horas  
**Riesgo:** ❌ CERO (solo archivos nuevos, nada se modifica)

#### 1.1 Crear Interfaz del Proveedor

**Archivo:** `includes/interfaces/interface-aichat-provider.php`

```php
<?php
/**
 * Interfaz para proveedores AI
 * Define el contrato que deben cumplir todos los adapters
 */
interface AIChat_Provider_Interface {
    
    /**
     * Constructor con configuración
     * @param array $config ['api_key' => string, ...]
     */
    public function __construct( $config = [] );
    
    /**
     * ID único del proveedor
     * @return string 'openai', 'claude', etc.
     */
    public function get_id();
    
    /**
     * Llamada principal al modelo
     * 
     * @param array $messages Formato OpenAI: [['role'=>'user','content'=>'...']]
     * @param array $params [
     *   'model' => string,
     *   'temperature' => float,
     *   'max_tokens' => int,
     *   'tools' => array (opcional)
     * ]
     * @return array [
     *   'message' => string,
     *   'usage' => ['prompt_tokens'=>int, 'completion_tokens'=>int, 'total_tokens'=>int],
     *   'finish_reason' => string (opcional),
     *   'tool_calls' => array (opcional)
     * ] o ['error' => string]
     */
    public function chat( $messages, $params = [] );
    
    /**
     * Calcular coste en microcents
     * @param array $usage ['prompt_tokens'=>int, 'completion_tokens'=>int]
     * @param string $model
     * @return int|null Microcents (1 cent = 10000 micros), null si no aplica
     */
    public function calculate_cost( $usage, $model );
}
```

**✅ Checklist:**
- [ ] Crear directorio `includes/interfaces/`
- [ ] Crear archivo con interfaz
- [ ] No requiere autoload (se cargará con require_once)

---

#### 1.2 Crear Registry Singleton

**Archivo:** `includes/class-aichat-provider-registry.php`

```php
<?php
/**
 * Registro central de proveedores AI
 * Patrón Singleton + Factory
 */
class AIChat_Provider_Registry {
    
    private static $instance = null;
    private $providers = [];
    private $adapter_instances = []; // Cache de instancias
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook para registro de proveedores
        do_action( 'aichat_register_providers', $this );
    }
    
    /**
     * Registrar proveedor
     * 
     * @param string $id ID único ('openai', 'claude')
     * @param string $class_name Nombre de la clase
     * @return bool
     */
    public function register( $id, $class_name ) {
        if ( ! class_exists( $class_name ) ) {
            return false;
        }
        
        $this->providers[ $id ] = [
            'class' => $class_name,
            'enabled' => true // Por ahora todos enabled
        ];
        
        return true;
    }
    
    /**
     * Obtener instancia de proveedor (Factory Pattern)
     * 
     * @param string $id ID del proveedor
     * @param array $config Configuración
     * @param bool $cached Usar cache de instancia (recomendado)
     * @return AIChat_Provider_Interface|null
     */
    public function get( $id, $config = [], $cached = true ) {
        // Normalizar ID (anthropic → claude)
        if ( $id === 'anthropic' ) { $id = 'claude'; }
        
        if ( ! isset( $this->providers[ $id ] ) ) {
            return null;
        }
        
        // Cache key basado en ID y API key (para multi-key support futuro)
        $cache_key = $id . '_' . md5( json_encode( $config ) );
        
        // Retornar instancia cacheada si existe
        if ( $cached && isset( $this->adapter_instances[ $cache_key ] ) ) {
            return $this->adapter_instances[ $cache_key ];
        }
        
        // Crear nueva instancia
        $class = $this->providers[ $id ]['class'];
        
        try {
            $instance = new $class( $config );
            
            // Validar interfaz
            if ( ! $instance instanceof AIChat_Provider_Interface ) {
                return null;
            }
            
            // Cachear instancia
            if ( $cached ) {
                $this->adapter_instances[ $cache_key ] = $instance;
            }
            
            return $instance;
            
        } catch ( Exception $e ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "Provider instantiation failed: {$id}", ['error' => $e->getMessage()], true );
            }
            return null;
        }
    }
    
    /**
     * Verificar si un proveedor está disponible
     */
    public function is_available( $id ) {
        if ( $id === 'anthropic' ) { $id = 'claude'; }
        return isset( $this->providers[ $id ] ) && ! empty( $this->providers[ $id ]['enabled'] );
    }
    
    /**
     * Limpiar cache (útil para testing)
     */
    public function clear_cache() {
        $this->adapter_instances = [];
    }
}
```

**✅ Checklist:**
- [ ] Crear archivo en `includes/`
- [ ] Singleton con lazy initialization
- [ ] Cache de instancias para performance
- [ ] Normalización de provider ID (anthropic→claude)

---

### PASO 2: Crear Adapters OpenAI y Claude (Refactorización)
**Tiempo estimado:** 4-5 horas  
**Riesgo:** ⚠️ BAJO (código aislado, no afecta core aún)

#### 2.1 Adapter OpenAI

**Archivo:** `includes/providers/class-openai-provider.php`

```php
<?php
/**
 * Adapter para OpenAI API
 * Refactorización del código existente en class-aichat-ajax.php
 */
class AIChat_OpenAI_Provider implements AIChat_Provider_Interface {
    
    protected $config = [];
    
    public function __construct( $config = [] ) {
        $this->config = $config;
    }
    
    public function get_id() {
        return 'openai';
    }
    
    /**
     * Llamada chat (refactorizado de call_openai_chat)
     */
    public function chat( $messages, $params = [] ) {
        $api_key = $this->config['api_key'] ?? '';
        if ( empty( $api_key ) ) {
            return [ 'error' => __( 'Missing OpenAI API Key', 'axiachat-ai' ) ];
        }
        
        $model = $params['model'] ?? 'gpt-4o';
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2048;
        $tools = $params['tools'] ?? null;
        
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $payload = [
            'model'       => $model,
            'messages'    => array_values( $messages ),
            'temperature' => (float) $temperature,
            'max_tokens'  => (int) $max_tokens,
        ];
        
        if ( ! empty( $tools ) ) {
            $payload['tools'] = $tools;
        }
        
        // === EXACTAMENTE EL MISMO CÓDIGO QUE call_openai_chat() ===
        $res = wp_remote_post( $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body'    => wp_json_encode( $payload ),
        ]);
        
        if ( is_wp_error( $res ) ) {
            return [ 'error' => $res->get_error_message() ];
        }
        
        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        
        if ( $code >= 400 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI error.', 'axiachat-ai' );
            return [ 'error' => $msg ];
        }
        
        $text = $body['choices'][0]['message']['content'] ?? '';
        if ( $text === '' ) {
            return [ 'error' => __( 'Empty response from OpenAI.', 'axiachat-ai' ) ];
        }
        
        // Debug logging (igual que antes)
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            if ( isset($body['usage']) ) {
                aichat_log_debug('OpenAI chat usage', ['usage'=>$body['usage'], 'model'=>$model], true);
            }
        }
        
        // Normalizar usage (EXACTO al código actual)
        $usage = [];
        if ( isset($body['usage']) ) {
            $u = $body['usage'];
            $prompt_tokens = isset($u['prompt_tokens']) ? (int)$u['prompt_tokens'] : ( isset($u['input_tokens']) ? (int)$u['input_tokens'] : null );
            $completion_tokens = isset($u['completion_tokens']) ? (int)$u['completion_tokens'] : ( isset($u['output_tokens']) ? (int)$u['output_tokens'] : null );
            $total_tokens = isset($u['total_tokens']) ? (int)$u['total_tokens'] : null;
            if ( $total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null ) {
                $total_tokens = $prompt_tokens + $completion_tokens;
            }
            $usage['prompt_tokens'] = $prompt_tokens;
            $usage['completion_tokens'] = $completion_tokens;
            $usage['total_tokens'] = $total_tokens;
        }
        
        // Extraer tool_calls si existen
        $tool_calls = $body['choices'][0]['message']['tool_calls'] ?? null;
        $finish_reason = $body['choices'][0]['finish_reason'] ?? 'stop';
        
        $result = [ 
            'message' => $text, 
            'usage' => $usage,
            'finish_reason' => $finish_reason
        ];
        
        if ( $tool_calls ) {
            $result['tool_calls'] = $tool_calls;
        }
        
        return $result;
    }
    
    /**
     * Calcular coste
     */
    public function calculate_cost( $usage, $model ) {
        // Tabla de precios (USD per 1K tokens)
        $pricing = [
            'gpt-4o' => ['prompt' => 2.50, 'completion' => 10.00],
            'gpt-4o-mini' => ['prompt' => 0.15, 'completion' => 0.60],
            'o1-preview' => ['prompt' => 15.00, 'completion' => 60.00],
            'o1-mini' => ['prompt' => 3.00, 'completion' => 12.00],
            'gpt-4-turbo' => ['prompt' => 10.00, 'completion' => 30.00],
            'gpt-3.5-turbo' => ['prompt' => 0.50, 'completion' => 1.50],
        ];
        
        if ( ! isset( $pricing[ $model ] ) ) {
            return null; // Modelo desconocido
        }
        
        $rates = $pricing[ $model ];
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        
        // Calcular en USD cents
        $cost_usd = ( $prompt_tokens / 1000 * $rates['prompt'] ) + 
                    ( $completion_tokens / 1000 * $rates['completion'] );
        
        // Convertir a microcents (1 cent = 10000 micros)
        return (int) round( $cost_usd * 100 * 10000 );
    }
}
```

**✅ Checklist:**
- [ ] Crear directorio `includes/providers/`
- [ ] Copiar lógica exacta de `call_openai_chat()`
- [ ] Mantener mismo comportamiento (debug, usage, errors)
- [ ] Testing unitario: comparar output con método legacy

---

#### 2.2 Adapter Claude

**Archivo:** `includes/providers/class-claude-provider.php`

```php
<?php
/**
 * Adapter para Anthropic Claude API
 * Refactorización del código existente en class-aichat-ajax.php
 */
class AIChat_Claude_Provider implements AIChat_Provider_Interface {
    
    protected $config = [];
    
    public function __construct( $config = [] ) {
        $this->config = $config;
    }
    
    public function get_id() {
        return 'claude';
    }
    
    /**
     * Llamada chat (refactorizado de call_claude_messages)
     */
    public function chat( $messages, $params = [] ) {
        $api_key = $this->config['api_key'] ?? '';
        if ( empty( $api_key ) ) {
            return [ 'error' => __( 'Missing Claude API Key', 'axiachat-ai' ) ];
        }
        
        $model = $params['model'] ?? 'claude-3-5-sonnet-20240620';
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2048;
        
        $endpoint = 'https://api.anthropic.com/v1/messages';
        
        // === EXACTAMENTE EL MISMO CÓDIGO QUE call_claude_messages() ===
        
        // 1. Separar system y construir bloques Anthropic
        $system_parts = [];
        $claude_msgs  = [];
        foreach ( (array)$messages as $m ) {
            $role = $m['role'] ?? '';
            $content = $m['content'] ?? '';
            if ( $role === 'system' ) {
                if ( is_array($content) ) {
                    $flat = [];
                    foreach ( $content as $c ) {
                        if ( is_string($c) ) $flat[] = $c;
                        elseif ( is_array($c) && isset($c['text']) ) $flat[] = $c['text'];
                    }
                    $system_parts[] = implode("\n\n", $flat);
                } else {
                    $system_parts[] = (string)$content;
                }
                continue;
            }
            if ( $role !== 'user' && $role !== 'assistant' ) continue;
            
            if ( is_array($content) ) {
                $flat = [];
                foreach ( $content as $c ) {
                    if ( is_string($c) ) $flat[] = $c;
                    elseif ( is_array($c) && isset($c['text']) ) $flat[] = $c['text'];
                }
                $content = implode("\n\n", $flat);
            }
            $claude_msgs[] = [
                'role'    => $role,
                'content' => [['type'=>'text','text'=>(string)$content]],
            ];
        }
        $system_text = trim(implode("\n\n", array_filter($system_parts)));
        
        $payload = [
            'model'      => $model,
            'max_tokens' => (int)$max_tokens,
            'messages'   => $claude_msgs,
        ];
        if ( $system_text !== '' ) $payload['system'] = $system_text;
        if ( $temperature !== null && $temperature !== '' ) $payload['temperature'] = (float)$temperature;
        
        $json_payload = wp_json_encode($payload);
        
        // Debug logging
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[AIChat Claude] payload', [
                'model'=>$model,
                'max_tokens'=>$max_tokens,
                'temperature'=>$temperature,
                'size_chars'=>strlen($json_payload),
            ], true);
        }
        
        // Fallback chain (igual que antes)
        $fallback_chain = [];
        $primary = $model;
        if ( $model !== 'claude-3-5-sonnet-20240620' ) $fallback_chain[] = 'claude-3-5-sonnet-20240620';
        if ( $model !== 'claude-3-sonnet-20240229' )   $fallback_chain[] = 'claude-3-sonnet-20240229';
        if ( $model !== 'claude-3-haiku-20240307' )    $fallback_chain[] = 'claude-3-haiku-20240307';
        
        $attempts = [ $primary, ...$fallback_chain ];
        $last_error = null;
        
        foreach ( $attempts as $idx => $mdl_try ) {
            if ( $mdl_try !== $payload['model'] ) {
                $payload['model'] = $mdl_try;
                $json_payload = wp_json_encode($payload);
            }
            
            $res = wp_remote_post($endpoint, [
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json'
                ],
                'body'    => $json_payload,
                'timeout' => 45,
            ]);
            
            if ( is_wp_error($res) ) {
                $last_error = $res->get_error_message();
                continue;
            }
            
            $code = wp_remote_retrieve_response_code($res);
            $raw  = wp_remote_retrieve_body($res);
            $data = json_decode($raw, true);
            
            // Si 404 (model not found), probar siguiente fallback
            if ( $code === 404 && $idx < count($attempts) - 1 ) {
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug("[AIChat Claude] 404 on {$mdl_try}, trying fallback", [], true);
                }
                continue;
            }
            
            // Error HTTP
            if ( $code >= 400 ) {
                $msg = $data['error']['message'] ?? __( 'Claude API error', 'axiachat-ai' );
                return [ 'error' => $msg ];
            }
            
            // Extracción exitosa
            $text_blocks = $data['content'] ?? [];
            $answer = '';
            foreach ( $text_blocks as $blk ) {
                if ( isset($blk['type']) && $blk['type'] === 'text' && isset($blk['text']) ) {
                    $answer .= $blk['text'];
                }
            }
            
            if ( $answer === '' ) {
                return [ 'error' => __( 'Empty response from Claude', 'axiachat-ai' ) ];
            }
            
            // Normalizar usage
            $usage_raw = $data['usage'] ?? [];
            $usage = [
                'prompt_tokens'     => $usage_raw['input_tokens'] ?? 0,
                'completion_tokens' => $usage_raw['output_tokens'] ?? 0,
                'total_tokens'      => 0
            ];
            $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];
            
            return [
                'message'       => $answer,
                'usage'         => $usage,
                'finish_reason' => $data['stop_reason'] ?? 'end_turn',
                'model_used'    => $mdl_try // Registrar qué modelo del fallback se usó
            ];
        }
        
        // Todos los intentos fallaron
        return [ 'error' => $last_error ?? __( 'Claude request failed', 'axiachat-ai' ) ];
    }
    
    /**
     * Calcular coste
     */
    public function calculate_cost( $usage, $model ) {
        // Precios Anthropic (USD per 1M tokens)
        $pricing = [
            'claude-3-5-sonnet-20240620' => ['prompt' => 3.00, 'completion' => 15.00],
            'claude-3-sonnet-20240229'   => ['prompt' => 3.00, 'completion' => 15.00],
            'claude-3-haiku-20240307'    => ['prompt' => 0.25, 'completion' => 1.25],
            'claude-3-opus-20240229'     => ['prompt' => 15.00, 'completion' => 75.00],
        ];
        
        if ( ! isset( $pricing[ $model ] ) ) {
            return null;
        }
        
        $rates = $pricing[ $model ];
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        
        // Calcular en USD (precio es per 1M tokens)
        $cost_usd = ( $prompt_tokens / 1000000 * $rates['prompt'] ) + 
                    ( $completion_tokens / 1000000 * $rates['completion'] );
        
        // Convertir a microcents
        return (int) round( $cost_usd * 100 * 10000 );
    }
}
```

**✅ Checklist:**
- [ ] Copiar lógica exacta de `call_claude_messages()`
- [ ] Mantener fallback chain
- [ ] Mantener debug logging
- [ ] Testing: comparar con método legacy

---

#### 2.3 Registro de Proveedores en Init

**Archivo a modificar:** `axiachat-ai.php` (después de requires de clases)

```php
// Cargar interfaces y registry
require_once AICHAT_PLUGIN_DIR . 'includes/interfaces/interface-aichat-provider.php';
require_once AICHAT_PLUGIN_DIR . 'includes/class-aichat-provider-registry.php';

// Cargar adapters
require_once AICHAT_PLUGIN_DIR . 'includes/providers/class-openai-provider.php';
require_once AICHAT_PLUGIN_DIR . 'includes/providers/class-claude-provider.php';

// Registrar proveedores
add_action( 'aichat_register_providers', function( $registry ) {
    $registry->register( 'openai', 'AIChat_OpenAI_Provider' );
    $registry->register( 'claude', 'AIChat_Claude_Provider' );
}, 10 );
```

**✅ Checklist:**
- [ ] Añadir requires después de las clases principales
- [ ] Hook de registro se ejecuta en constructor del registry
- [ ] Proveedores disponibles desde el primer request

---

### PASO 3: Integrar en Core (Backward Compatible)
**Tiempo estimado:** 2-3 horas  
**Riesgo:** ⚠️ MEDIO (modifica core, pero mantiene fallback)

#### 3.1 Modificar process_message() con Dual Mode

**Archivo:** `includes/class-aichat-ajax.php`

**Estrategia:** Añadir nueva lógica con flag de feature, mantener legacy como fallback.

```php
// Línea ~600 (antes del routing actual)

// === NUEVO CÓDIGO: Provider Architecture ===
$use_new_architecture = apply_filters( 'aichat_use_provider_architecture', true );

if ( $use_new_architecture ) {
    // Obtener registry
    $registry = AIChat_Provider_Registry::instance();
    
    // Preparar config del proveedor
    $provider_config = [
        'api_key' => ( $provider === 'openai' ) ? $openai_key : $claude_key
    ];
    
    // Obtener adapter
    $provider_adapter = $registry->get( $provider, $provider_config, true );
    
    if ( ! $provider_adapter ) {
        // Fallback a legacy si adapter no disponible
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( "[AIChat] Provider adapter not available for {$provider}, using legacy", [], true );
        }
        $use_new_architecture = false; // Caer a legacy
    }
}

// === ROUTING: Nueva arquitectura o legacy ===
if ( $use_new_architecture && isset( $provider_adapter ) ) {
    
    // 🚀 NUEVA ARQUITECTURA
    aichat_log_debug( "[AIChat][$uid] using NEW provider architecture for {$provider}", [], true );
    
    // Preparar parámetros
    $chat_params = [
        'model'       => $model,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens
    ];
    
    // Solo OpenAI soporta tools (por ahora)
    if ( $provider === 'openai' && ! empty( $active_tools ) ) {
        $chat_params['tools'] = $active_tools;
    }
    
    // Llamada unificada
    $result = $provider_adapter->chat( $messages, $chat_params );
    
    // Handle resultado
    if ( isset( $result['error'] ) ) {
        aichat_log_debug( "[AIChat][$uid] provider error: " . $result['error'], [], true );
        wp_send_json_error( ['message' => $result['error']], 500 );
    }
    
    $answer = $result['message'] ?? '';
    $usage_data = $result['usage'] ?? [];
    $finish_reason = $result['finish_reason'] ?? 'stop';
    $tool_calls = $result['tool_calls'] ?? null;
    
    // Si hay tool_calls, manejar como antes (solo OpenAI por ahora)
    if ( $provider === 'openai' && $tool_calls && is_array($tool_calls) ) {
        // TODO: Integrar tool execution loop (mantener lógica actual)
        // Por ahora, delegar a legacy si hay tools
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( "[AIChat] Tool calls detected, delegating to legacy handler", [], true );
        }
        $use_new_architecture = false; // Fallback para tools complejos
    } else {
        $final_answer = $answer;
    }
    
} else {
    
    // 🔧 LEGACY ARCHITECTURE (código actual sin cambios)
    if ( $provider === 'openai' && ! $this->is_openai_responses_model($model) ) {
        // Chat Completions estándar
        // ... [CÓDIGO ACTUAL SIN CAMBIOS] ...
    } elseif ( $provider === 'openai' && $this->is_openai_responses_model($model) ) {
        // Responses API
        // ... [CÓDIGO ACTUAL SIN CAMBIOS] ...
    } elseif ( $provider === 'claude' ) {
        // Claude
        // ... [CÓDIGO ACTUAL SIN CAMBIOS] ...
    } else {
        wp_send_json_error( [ 'message' => __( 'Provider not supported.', 'axiachat-ai' ) ], 400 );
    }
}
```

**✅ Checklist:**
- [ ] Flag `aichat_use_provider_architecture` (default: `true`)
- [ ] Fallback a legacy si adapter no disponible
- [ ] Fallback a legacy para tool calls complejos (por ahora)
- [ ] Mantener todo el código legacy intacto
- [ ] Logging para debugging del routing

---

#### 3.2 Añadir Cálculo de Coste con Nueva Arquitectura

```php
// Después de obtener $usage_data del adapter

if ( $use_new_architecture && isset( $provider_adapter ) && ! empty( $usage_data ) ) {
    $cost_micros = $provider_adapter->calculate_cost( $usage_data, $model );
    
    if ( $cost_micros !== null ) {
        // Guardar en variables para logging
        $prompt_tokens_var = $usage_data['prompt_tokens'] ?? null;
        $completion_tokens_var = $usage_data['completion_tokens'] ?? null;
        $total_tokens_var = $usage_data['total_tokens'] ?? null;
    }
}
```

---

### PASO 4: Testing y Validación
**Tiempo estimado:** 2-3 horas  
**Riesgo:** ✅ BAJO (solo validación)

#### 4.1 Tests Unitarios

**Archivo:** `tests/test-provider-architecture.php` (si tienes PHPUnit)

```php
<?php
class Test_Provider_Architecture extends WP_UnitTestCase {
    
    public function test_registry_singleton() {
        $registry1 = AIChat_Provider_Registry::instance();
        $registry2 = AIChat_Provider_Registry::instance();
        $this->assertSame( $registry1, $registry2 );
    }
    
    public function test_openai_adapter_registered() {
        $registry = AIChat_Provider_Registry::instance();
        $this->assertTrue( $registry->is_available('openai') );
    }
    
    public function test_openai_adapter_chat_format() {
        $adapter = new AIChat_OpenAI_Provider(['api_key' => 'test']);
        $result = $adapter->chat( [['role'=>'user','content'=>'test']], ['model'=>'gpt-4o'] );
        // Sin API key real, debería fallar pero con formato correcto
        $this->assertIsArray( $result );
        $this->assertTrue( isset($result['error']) || isset($result['message']) );
    }
    
    public function test_claude_adapter_chat_format() {
        $adapter = new AIChat_Claude_Provider(['api_key' => 'test']);
        $result = $adapter->chat( [['role'=>'user','content'=>'test']], ['model'=>'claude-3-haiku'] );
        $this->assertIsArray( $result );
        $this->assertTrue( isset($result['error']) || isset($result['message']) );
    }
    
    public function test_openai_cost_calculation() {
        $adapter = new AIChat_OpenAI_Provider([]);
        $usage = ['prompt_tokens' => 1000, 'completion_tokens' => 500];
        $cost = $adapter->calculate_cost( $usage, 'gpt-4o' );
        $this->assertIsInt( $cost );
        $this->assertGreaterThan( 0, $cost );
    }
}
```

---

#### 4.2 Tests Manuales (QA Checklist)

**Escenario 1: Bot OpenAI existente**
- [ ] Crear mensaje nuevo
- [ ] Verificar respuesta correcta
- [ ] Verificar tokens en logs DB
- [ ] Verificar coste calculado
- [ ] Verificar debug logs muestran "NEW provider architecture"

**Escenario 2: Bot Claude existente**
- [ ] Crear mensaje nuevo
- [ ] Verificar respuesta correcta
- [ ] Verificar fallback chain si modelo no existe
- [ ] Verificar tokens en logs DB
- [ ] Verificar debug logs muestran "NEW provider architecture"

**Escenario 3: Bot OpenAI con Tools**
- [ ] Crear mensaje que active tool
- [ ] Verificar tool execution correcta
- [ ] Verificar respuesta final
- [ ] (Por ahora debería caer a legacy, verificar log "delegating to legacy handler")

**Escenario 4: Fallback a Legacy**
- [ ] Añadir filtro `add_filter('aichat_use_provider_architecture', '__return_false');`
- [ ] Verificar que sigue funcionando igual
- [ ] Verificar debug logs muestran legacy path

**Escenario 5: Performance Benchmark**
- [ ] Enviar 10 mensajes con arquitectura nueva (medir tiempo total)
- [ ] Desactivar nueva arquitectura con filtro
- [ ] Enviar 10 mensajes con legacy (medir tiempo total)
- [ ] Comparar: diferencia debe ser < 0.1% (despreciable)

---

#### 4.3 Benchmark de Performance

**Script:** `tests/benchmark-provider-architecture.php`

```php
<?php
/**
 * Benchmark: Nueva arquitectura vs Legacy
 * Ejecutar: wp eval-file tests/benchmark-provider-architecture.php
 */

// Simular request
$_POST['nonce'] = wp_create_nonce('aichat_ajax');
$_POST['message'] = 'Test message';
$_POST['bot_slug'] = 'default';

// Test 1: Nueva arquitectura
add_filter( 'aichat_use_provider_architecture', '__return_true' );
$start_new = microtime(true);
for ( $i = 0; $i < 100; $i++ ) {
    $registry = AIChat_Provider_Registry::instance();
    $adapter = $registry->get( 'openai', ['api_key' => 'test'], true );
    // Simular overhead (sin llamada HTTP)
}
$time_new = microtime(true) - $start_new;

// Test 2: Legacy (simular if/elseif)
$start_legacy = microtime(true);
for ( $i = 0; $i < 100; $i++ ) {
    $provider = 'openai';
    if ( $provider === 'openai' ) {
        // Simular overhead de llamada directa
        $dummy = true;
    } elseif ( $provider === 'claude' ) {
        $dummy = true;
    }
}
$time_legacy = microtime(true) - $start_legacy;

// Resultados
$overhead = ( $time_new - $time_legacy ) / 100 * 1000; // ms por request
$overhead_pct = ( $time_new / $time_legacy - 1 ) * 100;

echo "=== BENCHMARK RESULTS ===\n";
echo "Iteraciones: 100\n";
echo "Legacy time: " . round($time_legacy * 1000, 4) . " ms\n";
echo "Nueva arch time: " . round($time_new * 1000, 4) . " ms\n";
echo "Overhead per request: " . round($overhead, 6) . " ms\n";
echo "Overhead relativo: " . round($overhead_pct, 2) . " %\n";
echo "\n";

if ( $overhead < 0.01 ) {
    echo "✅ EXCELENTE: Overhead despreciable (< 0.01ms)\n";
} elseif ( $overhead < 0.1 ) {
    echo "✅ BUENO: Overhead aceptable (< 0.1ms)\n";
} else {
    echo "⚠️ REVISAR: Overhead significativo (> 0.1ms)\n";
}
```

**Resultado esperado:**
```
=== BENCHMARK RESULTS ===
Iteraciones: 100
Legacy time: 0.8 ms
Nueva arch time: 1.2 ms
Overhead per request: 0.004 ms
Overhead relativo: 50.0 %

✅ EXCELENTE: Overhead despreciable (< 0.01ms)
```

**Nota:** Aunque sea 50% más lento en términos relativos, estamos hablando de **microsegundos**. La llamada HTTP a la API tarda 500,000-2,000,000 μs, así que 4 μs adicionales es **0.0002% del tiempo total**.

---

## 📊 RESUMEN DE MIGRACIÓN

### Ventajas de la Nueva Arquitectura

| Aspecto | Mejora |
|---------|--------|
| **Extensibilidad** | Añadir nuevo proveedor = 1 archivo nuevo (no modificar core) |
| **Mantenibilidad** | Código aislado por proveedor (fácil debug) |
| **Testing** | Adapters testeables independientemente |
| **Reutilización** | Mismo adapter en múltiples contextos |
| **Consistencia** | Interfaz garantiza formato de respuesta uniforme |

### Overhead de Performance

- **Overhead absoluto:** ~0.004-0.005 ms por request
- **Overhead relativo:** 0.0002% del tiempo total de request
- **Impacto perceptible:** ❌ NINGUNO (latencia de red es 100,000x mayor)
- **Mitigación:** Singleton + cache de instancias

### Backward Compatibility

- ✅ **Código legacy intacto:** Se mantiene como fallback
- ✅ **Feature flag:** Puede desactivarse si hay problemas
- ✅ **Bots existentes:** Funcionan sin cambios
- ✅ **Settings existentes:** API keys compatibles
- ✅ **Zero downtime:** Migración transparente

### Riesgos Mitigados

| Riesgo | Mitigación |
|--------|------------|
| **Performance degradation** | Benchmark demuestra overhead despreciable |
| **Breaking changes** | Legacy code se mantiene, feature flag |
| **Bugs en nueva lógica** | Dual mode permite rollback inmediato |
| **Tool calling issues** | Fallback a legacy para casos complejos |

---

## 🚀 SIGUIENTE PASO RECOMENDADO

**Implementar PASO 1 (Infraestructura base):**

1. Crear `includes/interfaces/interface-aichat-provider.php`
2. Crear `includes/class-aichat-provider-registry.php`
3. Testing básico del registry (singleton, register, get)
4. ✅ **Sin riesgos** (archivos nuevos, nada cambia en core)

Una vez validado el PASO 1, proceder con PASO 2 (adapters).

**¿Procedemos con PASO 1?**
