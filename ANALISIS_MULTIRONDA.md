# Análisis: Arquitectura Multi-Ronda para Tool Calling

## Fecha: 2025-11-04
## Contexto: Diseño para nueva arquitectura de providers

---

## 1. OVERVIEW DEL PROBLEMA

### ¿Qué es Multi-Ronda?

El **tool calling multi-ronda** es un patrón donde el modelo:
1. **Ronda 1**: Recibe pregunta → Decide llamar a herramientas
2. **Ronda 2**: Recibe resultados de tools → Procesa y puede llamar más tools o responder
3. **Ronda N**: Continúa hasta obtener respuesta final o alcanzar límite

### Estado Actual

| Componente | Legacy | New Architecture |
|------------|--------|------------------|
| **OpenAI Chat Completions** | ✅ Multi-ronda implementada | ❌ NO implementado |
| **OpenAI Responses API** | ✅ Multi-ronda implementada | ❌ NO implementado |
| **Claude Messages** | ❌ NO implementado | ❌ NO implementado |

---

## 2. ANÁLISIS DEL CÓDIGO LEGACY

### A. OpenAI Chat Completions (GPT-4, O1, GPT-3.5)

**Ubicación**: `class-aichat-ajax.php` líneas 592-683

#### Flujo de Ejecución

```php
// Inicialización
$max_rounds = 5; // Configurable vía filter
$round = 1;
$acc_messages = $messages; // Array acumulativo
$registered = aichat_get_registered_tools(); // Callbacks disponibles

while ( $round <= $max_rounds ) {
    // 1. Llamar al modelo
    $result = call_openai_auto( $api_key, $model, $acc_messages, ..., ['tools'=>$active_tools] );
    
    // 2. Verificar si hay tool_calls
    $has_tool_calls = !empty($result['tool_calls']);
    
    if ( !$has_tool_calls ) {
        // Respuesta final → salir
        $final_answer = $result['message'];
        break;
    }
    
    if ( $round === $max_rounds ) {
        // Límite alcanzado → devolver lo que haya
        $final_answer = $result['message'];
        break;
    }
    
    // 3. Construir mensaje assistant con tool_calls
    $assistant_msg = [
        'role' => 'assistant',
        'content' => $result['message'],
        'tool_calls' => [ /* formato OpenAI */ ]
    ];
    
    // 4. Ejecutar cada tool
    $tool_output_messages = [];
    foreach ( $result['tool_calls'] as $tc ) {
        $fname = $tc['name'];
        $args = json_decode($tc['arguments'], true);
        
        // Ejecutar callback registrado
        $output = call_user_func( $registered[$fname]['callback'], $args, $context );
        
        // Log en BD (wp_aichat_tool_calls)
        $wpdb->insert( ... );
        
        // Construir mensaje tool
        $tool_output_messages[] = [
            'role' => 'tool',
            'tool_call_id' => $tc['id'],
            'content' => json_encode($output)
        ];
    }
    
    // 5. Acumular mensajes para siguiente ronda
    $acc_messages = array_merge(
        $acc_messages,
        [ $assistant_msg ],
        $tool_output_messages
    );
    
    $round++;
}

return [ 'message' => $final_answer ];
```

#### Características Clave

1. **Array acumulativo**: `$acc_messages` crece cada ronda
   - Ronda 1: `[system, user_question]`
   - Ronda 2: `[system, user_question, assistant_with_tool_calls, tool_results]`
   - Ronda 3: `[...previous, assistant_with_tool_calls_2, tool_results_2]`

2. **Formato OpenAI**:
   ```json
   {
     "role": "assistant",
     "content": "texto opcional",
     "tool_calls": [
       {
         "id": "call_abc123",
         "type": "function",
         "function": {
           "name": "get_weather",
           "arguments": "{\"city\":\"Madrid\"}"
         }
       }
     ]
   }
   ```

3. **Tool outputs**:
   ```json
   {
     "role": "tool",
     "tool_call_id": "call_abc123",
     "content": "{\"temp\":20,\"condition\":\"sunny\"}"
   }
   ```

4. **Límite configurable**: `apply_filters('aichat_tools_max_rounds', 5)`

5. **Logging en BD**: Cada tool execution se guarda en `wp_aichat_tool_calls`

---

### B. OpenAI Responses API (GPT-5)

**Ubicación**: `class-aichat-ajax.php` líneas 1910-2140

#### Diferencias vs Chat Completions

| Aspecto | Chat Completions | Responses API |
|---------|------------------|---------------|
| **Endpoint** | `/v1/chat/completions` | `/v1/responses` |
| **Continuidad** | Mensajes acumulativos | `previous_response_id` |
| **Tool call format** | `tool_calls[]` en assistant | `output[].type='function_call'` |
| **Tool output format** | `{role:'tool', tool_call_id, content}` | `input[].type='function_call_output'` |
| **Estado** | Stateless (envías todo) | Stateful (API mantiene contexto) |

#### Flujo de Ejecución

```php
$round = 1;
$response_id = null; // Se obtiene de la primera respuesta
$pending_tool_outputs = []; // Acumula outputs para enviar

while ( $round <= $max_rounds ) {
    
    if ( $response_id === null ) {
        // PRIMERA RONDA
        $payload = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => [
                [ 'role' => 'user', 'content' => [ ['type'=>'input_text', 'text'=>$input] ] ]
            ],
            'max_output_tokens' => $max_tokens,
            'tools' => $tools, // Opcional
        ];
    } else {
        // RONDAS SUBSECUENTES
        // Construir function_call_output items
        $fco_items = [];
        foreach ( $pending_tool_outputs as $to ) {
            $fco_items[] = [
                'type' => 'function_call_output',
                'call_id' => $to['tool_call_id'],
                'output' => $to['output'], // String JSON
            ];
        }
        
        $payload = [
            'model' => $model,
            'previous_response_id' => $response_id, // CLAVE
            'input' => $fco_items,
            'max_output_tokens' => $max_tokens,
        ];
    }
    
    // Llamar API
    $res = wp_remote_post( '/v1/responses', ... );
    $data = json_decode( $response_body );
    
    // Guardar response_id para continuar
    $response_id = $data['id'];
    
    // Parsear output
    $tool_calls = [];
    $text_frag = '';
    
    foreach ( $data['output'] as $blk ) {
        if ( $blk['type'] === 'message' ) {
            // Extraer texto
            foreach ( $blk['content'] as $c ) {
                if ( $c['type'] === 'output_text' ) {
                    $text_frag .= $c['text'];
                }
            }
        } elseif ( $blk['type'] === 'function_call' ) {
            $tool_calls[] = [
                'id' => $blk['call_id'],
                'name' => $blk['name'],
                'arguments' => $blk['arguments'],
            ];
        }
    }
    
    // ¿Hay tool calls?
    if ( empty($tool_calls) ) {
        // Respuesta final
        $final_text = $text_frag;
        break;
    }
    
    // Ejecutar tools
    $pending_tool_outputs = [];
    foreach ( $tool_calls as $tc ) {
        $output = execute_tool( $tc['name'], $tc['arguments'] );
        $pending_tool_outputs[] = [
            'tool_call_id' => $tc['id'],
            'output' => json_encode($output),
        ];
    }
    
    $round++;
}

return [ 'message' => $final_text ];
```

#### Características Clave Responses API

1. **Stateful**: La API mantiene el contexto mediante `response_id`
2. **No acumulas mensajes**: Solo envías los tool outputs de la última ronda
3. **Formato diferente**:
   - Tool calls: `output[].type='function_call'`
   - Tool outputs: `input[].type='function_call_output'`
4. **Web search nativo**: Soporta `{type:'web_search'}` como tool
5. **Reasoning effort**: Parámetro `reasoning: {effort: 'low|medium|high'}`

---

### C. Claude Messages API

**Estado**: ❌ NO tiene multi-ronda implementada en legacy

**Razón**: Claude sí soporta tools desde API v2023-06-01, pero el código legacy no lo implementó.

**Formato Claude Tools**:
```json
{
  "model": "claude-3-5-sonnet-20240620",
  "max_tokens": 1024,
  "tools": [
    {
      "name": "get_weather",
      "description": "Get weather for a city",
      "input_schema": {
        "type": "object",
        "properties": {
          "city": { "type": "string" }
        },
        "required": ["city"]
      }
    }
  ],
  "messages": [
    {"role": "user", "content": "What's the weather in Madrid?"}
  ]
}
```

**Respuesta con tool use**:
```json
{
  "content": [
    {
      "type": "tool_use",
      "id": "toolu_abc123",
      "name": "get_weather",
      "input": {"city": "Madrid"}
    }
  ],
  "stop_reason": "tool_use"
}
```

**Continuar conversación**:
```json
{
  "messages": [
    {"role": "user", "content": "What's the weather in Madrid?"},
    {
      "role": "assistant",
      "content": [
        {
          "type": "tool_use",
          "id": "toolu_abc123",
          "name": "get_weather",
          "input": {"city": "Madrid"}
        }
      ]
    },
    {
      "role": "user",
      "content": [
        {
          "type": "tool_result",
          "tool_use_id": "toolu_abc123",
          "content": "{\"temp\":20,\"condition\":\"sunny\"}"
        }
      ]
    }
  ]
}
```

---

## 3. PATRONES COMUNES ENTRE PROVIDERS

### Similitudes

| Aspecto | OpenAI CC | OpenAI Responses | Claude |
|---------|-----------|------------------|--------|
| **Loop de rondas** | ✅ while | ✅ while | ✅ (si se implementa) |
| **Detección tool calls** | `tool_calls` array | `output[].type='function_call'` | `content[].type='tool_use'` |
| **ID de llamada** | `call_id` | `call_id` | `tool_use.id` |
| **Argumentos** | JSON string | JSON string | JSON object |
| **Ejecutar callback** | ✅ | ✅ | ✅ |
| **Logging BD** | ✅ | ✅ | ✅ (pendiente) |
| **Límite rondas** | ✅ 5 | ✅ 5 | ✅ 5 |

### Diferencias

| Aspecto | OpenAI CC | OpenAI Responses | Claude |
|---------|-----------|------------------|--------|
| **Continuación** | Mensajes acumulativos | `previous_response_id` | Mensajes acumulativos |
| **Role tool** | `'tool'` | Input blocks | `'user'` con `tool_result` |
| **Formato output** | `{role:'tool', ...}` | `{type:'function_call_output', ...}` | `{type:'tool_result', ...}` |

---

## 4. OPCIONES DE DISEÑO

### Opción A: Multi-Ronda en Cada Provider (Descentralizada)

**Implementación**: Cada provider tiene su método `chat_with_tools()` con loop interno.

```php
class AIChat_OpenAI_Provider {
    public function chat( $messages, $params = [] ) {
        $tools = $params['tools'] ?? null;
        
        if ( empty($tools) ) {
            return $this->chat_simple( $messages, $params );
        }
        
        return $this->chat_with_tools( $messages, $params );
    }
    
    protected function chat_with_tools( $messages, $params ) {
        $max_rounds = 5;
        $round = 1;
        $acc_messages = $messages;
        
        while ( $round <= $max_rounds ) {
            $result = $this->api_call( $acc_messages, $params );
            
            if ( empty($result['tool_calls']) ) {
                return $result; // Final
            }
            
            $tool_outputs = $this->execute_tools( $result['tool_calls'] );
            $acc_messages = $this->append_tool_messages( $acc_messages, $result, $tool_outputs );
            
            $round++;
        }
        
        return $result;
    }
}
```

**Pros**:
- ✅ Flexibilidad total por provider (cada API es diferente)
- ✅ No contamina la interface con detalles de tools
- ✅ Encapsulación: lógica de continuación interna

**Contras**:
- ❌ Duplicación de código (loop, limits, logging)
- ❌ 3 implementaciones separadas a mantener
- ❌ Testing más complejo (3 suites)

---

### Opción B: Multi-Ronda en Capa Abstracta (Centralizada)

**Implementación**: Clase base `AIChat_Provider_Tool_Handler` con template method.

```php
abstract class AIChat_Provider_Tool_Handler {
    
    /**
     * Template method: maneja loop multi-ronda
     */
    public function chat_with_tools( $messages, $params, $provider ) {
        $max_rounds = apply_filters('aichat_tools_max_rounds', 5);
        $round = 1;
        $state = $this->init_tool_state( $messages, $params );
        
        while ( $round <= $max_rounds ) {
            // Hook point: provider hace la llamada
            $result = $provider->call_api( $state['messages'], $params );
            
            // Hook point: provider parsea tool calls
            $tool_calls = $provider->extract_tool_calls( $result );
            
            if ( empty($tool_calls) ) {
                return $this->finalize_result( $result, $state );
            }
            
            // Ejecutar tools (común a todos)
            $tool_outputs = $this->execute_tools( $tool_calls, $params );
            
            // Log en BD (común a todos)
            $this->log_tool_executions( $tool_calls, $tool_outputs, $round );
            
            // Hook point: provider construye próxima request
            $state = $provider->prepare_next_round( $state, $result, $tool_outputs );
            
            $round++;
        }
        
        return $this->finalize_result( $result, $state );
    }
    
    protected function execute_tools( $tool_calls, $params ) {
        $registered = aichat_get_registered_tools();
        $outputs = [];
        
        foreach ( $tool_calls as $tc ) {
            $fname = $tc['name'];
            $args = is_string($tc['arguments']) 
                ? json_decode($tc['arguments'], true) 
                : $tc['arguments'];
            
            if ( isset($registered[$fname]) ) {
                $callback = $registered[$fname]['callback'];
                $output = call_user_func( $callback, $args, $params );
                $outputs[] = [
                    'tool_call_id' => $tc['id'],
                    'name' => $fname,
                    'output' => $output,
                ];
            }
        }
        
        return $outputs;
    }
}

// Cada provider implementa métodos abstractos
interface AIChat_Provider_Interface {
    
    /**
     * Llamada API sin procesar tools
     */
    public function call_api( $messages, $params );
    
    /**
     * Extraer tool calls de respuesta
     */
    public function extract_tool_calls( $result );
    
    /**
     * Construir próximo state para continuar
     */
    public function prepare_next_round( $state, $result, $tool_outputs );
}
```

**Pros**:
- ✅ Código común una sola vez (loop, logging, execute)
- ✅ Fácil añadir nuevos providers
- ✅ Testing centralizado del loop
- ✅ Consistencia entre providers

**Contras**:
- ❌ Interface más compleja (3 métodos nuevos)
- ❌ Menos flexible para casos edge de cada API
- ❌ Responses API es muy diferente (stateful vs stateless)

---

### Opción C: Híbrida (Recomendada)

**Implementación**: Trait reutilizable + métodos específicos en cada provider.

```php
trait AIChat_Tool_Execution {
    
    /**
     * Ejecutar tools registrados (común)
     */
    protected function execute_registered_tools( $tool_calls, $context = [] ) {
        $registered = function_exists('aichat_get_registered_tools') 
            ? aichat_get_registered_tools() 
            : [];
        
        $outputs = [];
        
        foreach ( $tool_calls as $tc ) {
            $fname = $tc['name'] ?? '';
            $args = is_string($tc['arguments'] ?? '') 
                ? json_decode($tc['arguments'], true) 
                : ($tc['arguments'] ?? []);
            
            if ( !is_array($args) ) $args = [];
            
            $start = microtime(true);
            
            if ( isset($registered[$fname]) && is_callable($registered[$fname]['callback']) ) {
                try {
                    $result = call_user_func( $registered[$fname]['callback'], $args, $context );
                    $output_str = is_array($result) ? wp_json_encode($result) : (string)$result;
                } catch ( \Throwable $e ) {
                    $output_str = wp_json_encode([
                        'ok' => false,
                        'error' => 'exception',
                        'message' => $e->getMessage()
                    ]);
                }
            } else {
                $output_str = wp_json_encode(['ok' => false, 'error' => 'unknown_tool']);
            }
            
            $elapsed_ms = round((microtime(true) - $start) * 1000);
            
            // Truncar si es muy largo
            if ( mb_strlen($output_str) > 4000 ) {
                $output_str = mb_substr($output_str, 0, 4000) . '…';
            }
            
            $outputs[] = [
                'tool_call_id' => $tc['id'] ?? '',
                'name' => $fname,
                'output' => $output_str,
                'elapsed_ms' => $elapsed_ms,
            ];
        }
        
        return $outputs;
    }
    
    /**
     * Log tool executions en BD (común)
     */
    protected function log_tool_executions( $tool_calls, $outputs, $round, $context = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_tool_calls';
        
        foreach ( $outputs as $output ) {
            $wpdb->insert( $table, [
                'request_uuid' => $context['request_uuid'] ?? '',
                'conversation_id' => null,
                'session_id' => $context['session_id'] ?? '',
                'bot_slug' => $context['bot_slug'] ?? '',
                'round' => $round,
                'call_id' => $output['tool_call_id'],
                'tool_name' => $output['name'],
                'arguments_json' => '', // Recuperar de tool_calls
                'output_excerpt' => $output['output'],
                'duration_ms' => $output['elapsed_ms'],
                'error_code' => (strpos($output['output'], '"error"') !== false ? 'error' : null),
                'created_at' => current_time('mysql'),
            ], [ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]);
        }
    }
    
    /**
     * Get max rounds limit (común)
     */
    protected function get_max_rounds( $params = [] ) {
        return (int) apply_filters( 'aichat_tools_max_rounds', 5, $params['bot'] ?? null, $params['session'] ?? null );
    }
}

// Cada provider usa el trait e implementa su loop
class AIChat_OpenAI_Provider implements AIChat_Provider_Interface {
    use AIChat_Tool_Execution;
    
    protected function chat_completions_with_tools( $messages, $params ) {
        $max_rounds = $this->get_max_rounds( $params );
        $round = 1;
        $acc_messages = $messages;
        
        while ( $round <= $max_rounds ) {
            // Llamada específica OpenAI
            $result = $this->api_call_completions( $acc_messages, $params );
            
            if ( empty($result['tool_calls']) ) {
                return $result;
            }
            
            // Ejecutar tools (trait)
            $context = [
                'request_uuid' => $params['request_uuid'] ?? '',
                'session_id' => $params['session_id'] ?? '',
                'bot_slug' => $params['bot_slug'] ?? '',
            ];
            $outputs = $this->execute_registered_tools( $result['tool_calls'], $context );
            
            // Log (trait)
            $this->log_tool_executions( $result['tool_calls'], $outputs, $round, $context );
            
            // Continuar (específico OpenAI)
            $acc_messages = $this->append_openai_tool_messages( $acc_messages, $result, $outputs );
            
            $round++;
        }
        
        return $result;
    }
    
    protected function append_openai_tool_messages( $messages, $result, $outputs ) {
        $assistant_msg = [
            'role' => 'assistant',
            'content' => $result['message'] ?? '',
            'tool_calls' => [],
        ];
        
        foreach ( $result['tool_calls'] as $tc ) {
            $assistant_msg['tool_calls'][] = [
                'id' => $tc['id'],
                'type' => 'function',
                'function' => [
                    'name' => $tc['name'],
                    'arguments' => $tc['arguments'],
                ],
            ];
        }
        
        $tool_messages = [];
        foreach ( $outputs as $out ) {
            $tool_messages[] = [
                'role' => 'tool',
                'tool_call_id' => $out['tool_call_id'],
                'content' => $out['output'],
            ];
        }
        
        return array_merge( $messages, [ $assistant_msg ], $tool_messages );
    }
}
```

**Pros**:
- ✅ Código común reutilizable (trait)
- ✅ Flexibilidad total en loop (cada provider lo implementa)
- ✅ No contamina interface base
- ✅ Fácil testing (trait se puede testear aislado)
- ✅ Balance perfecto: reutilización + flexibilidad

**Contras**:
- ⚠️ Traits pueden ser menos obvios que herencia
- ⚠️ Requiere disciplina (usar trait consistentemente)

---

## 5. RECOMENDACIÓN

### **Opción C: Trait Híbrido** ✅

**Justificación**:

1. **Reutilización**: `execute_registered_tools()` y `log_tool_executions()` son idénticos entre providers
2. **Flexibilidad**: Cada provider maneja su loop (OpenAI CC vs Responses son MUY diferentes)
3. **Mantenibilidad**: Código común en un solo lugar
4. **Extensibilidad**: Nuevos providers solo implementan su loop específico
5. **Backward compat**: No rompe interface existente

### Estructura Propuesta

```
includes/
  interfaces/
    interface-aichat-provider.php (sin cambios)
  
  traits/
    trait-aichat-tool-execution.php (NUEVO)
      - execute_registered_tools()
      - log_tool_executions()
      - get_max_rounds()
  
  providers/
    class-openai-provider.php (MODIFICAR)
      - chat() router
      - chat_completions_with_tools() ← usa trait
      - chat_responses_with_tools() ← usa trait
      - append_openai_tool_messages()
    
    class-claude-provider.php (MODIFICAR)
      - chat() router
      - chat_with_tools() ← usa trait
      - append_claude_tool_messages()
```

---

## 6. PLAN DE IMPLEMENTACIÓN

### Fase 1: Crear Trait Base
- [ ] Crear `trait-aichat-tool-execution.php`
- [ ] Implementar `execute_registered_tools()`
- [ ] Implementar `log_tool_executions()`
- [ ] Implementar `get_max_rounds()`

### Fase 2: OpenAI Chat Completions
- [ ] Añadir `use AIChat_Tool_Execution` al provider
- [ ] Crear método `chat_completions_with_tools()`
- [ ] Implementar loop multi-ronda
- [ ] Implementar `append_openai_tool_messages()`
- [ ] Testing con bot real

### Fase 3: OpenAI Responses API
- [ ] Crear método `chat_responses_with_tools()`
- [ ] Implementar loop con `previous_response_id`
- [ ] Implementar `prepare_function_call_outputs()`
- [ ] Testing con GPT-5 + tools

### Fase 4: Claude Provider
- [ ] Crear método `chat_with_tools()`
- [ ] Implementar loop multi-ronda
- [ ] Implementar `append_claude_tool_messages()`
- [ ] Testing con Claude Sonnet

### Fase 5: Testing & Docs
- [ ] Test suite para trait
- [ ] Test suite para cada provider multi-ronda
- [ ] Docs de arquitectura
- [ ] Migration guide

---

## 7. CÓDIGO DE EJEMPLO: TRAIT

```php
<?php
/**
 * Trait para ejecución de tools multi-ronda
 * 
 * Proporciona funcionalidad común para todos los providers:
 * - Ejecutar callbacks de tools registrados
 * - Logging en BD
 * - Límites de rondas
 * 
 * @package AIChat
 * @since 2.0.0
 */

trait AIChat_Tool_Execution {
    
    /**
     * Ejecutar tools registrados
     * 
     * @param array $tool_calls Tool calls extraídos del provider
     * @param array $context Contexto (session_id, bot_slug, etc.)
     * @return array Outputs normalizados
     */
    protected function execute_registered_tools( $tool_calls, $context = [] ) {
        $registered = function_exists('aichat_get_registered_tools') 
            ? aichat_get_registered_tools() 
            : [];
        
        $outputs = [];
        
        foreach ( $tool_calls as $tc ) {
            $fname = $tc['name'] ?? '';
            $raw_args = $tc['arguments'] ?? '{}';
            $args = is_string($raw_args) ? json_decode($raw_args, true) : $raw_args;
            if ( !is_array($args) ) $args = [];
            
            $start = microtime(true);
            $output_str = '';
            
            if ( isset($registered[$fname]) && is_callable($registered[$fname]['callback']) ) {
                try {
                    $cb_context = array_merge($context, [
                        'question' => $context['message'] ?? '',
                        'round' => $context['round'] ?? 1,
                    ]);
                    
                    $result = call_user_func( $registered[$fname]['callback'], $args, $cb_context );
                    
                    if ( is_array($result) ) {
                        $output_str = wp_json_encode($result);
                    } elseif ( is_string($result) ) {
                        $output_str = $result;
                    } else {
                        $output_str = '"ok"';
                    }
                } catch ( \Throwable $e ) {
                    $output_str = wp_json_encode([
                        'ok' => false,
                        'error' => 'exception',
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                $output_str = wp_json_encode(['ok' => false, 'error' => 'unknown_tool']);
            }
            
            $elapsed_ms = round((microtime(true) - $start) * 1000);
            
            // Truncar outputs largos
            if ( mb_strlen($output_str) > 4000 ) {
                $output_str = mb_substr($output_str, 0, 4000) . '…';
            }
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug("[AIChat Tools] Executed: {$fname} | {$elapsed_ms}ms | args_len=" . strlen($raw_args), [], true);
            }
            
            $outputs[] = [
                'tool_call_id' => $tc['id'] ?? '',
                'name' => $fname,
                'arguments' => $raw_args,
                'output' => $output_str,
                'elapsed_ms' => $elapsed_ms,
            ];
        }
        
        return $outputs;
    }
    
    /**
     * Log tool executions en base de datos
     * 
     * @param array $tool_calls Tool calls originales
     * @param array $outputs Outputs ejecutados
     * @param int $round Número de ronda
     * @param array $context Contexto
     */
    protected function log_tool_executions( $tool_calls, $outputs, $round, $context = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_tool_calls';
        
        foreach ( $outputs as $output ) {
            $wpdb->insert( $table, [
                'request_uuid' => $context['request_uuid'] ?? '',
                'conversation_id' => null, // Se vincula después
                'session_id' => $context['session_id'] ?? '',
                'bot_slug' => $context['bot_slug'] ?? '',
                'round' => $round,
                'call_id' => $output['tool_call_id'],
                'tool_name' => $output['name'],
                'arguments_json' => $output['arguments'] ?? '{}',
                'output_excerpt' => $output['output'],
                'duration_ms' => $output['elapsed_ms'],
                'error_code' => (strpos($output['output'], '"error"') !== false ? 'error' : null),
                'created_at' => current_time('mysql'),
            ], [ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ]);
        }
    }
    
    /**
     * Obtener límite de rondas configurado
     * 
     * @param array $params Parámetros con bot, session
     * @return int Máximo de rondas
     */
    protected function get_max_rounds( $params = [] ) {
        $max = (int) apply_filters( 
            'aichat_tools_max_rounds', 
            5, 
            $params['bot'] ?? null, 
            $params['session'] ?? null 
        );
        
        return $max < 1 ? 1 : $max;
    }
}
```

---

## 8. PRÓXIMOS PASOS

1. **Aprobar arquitectura**: Confirmar Opción C (Trait Híbrido)
2. **Crear trait**: Implementar `trait-aichat-tool-execution.php`
3. **OpenAI Provider**: Implementar multi-ronda para Chat Completions
4. **Testing**: Validar con bot real
5. **OpenAI Responses**: Implementar multi-ronda para GPT-5
6. **Claude**: Implementar multi-ronda
7. **Documentation**: Guía para nuevos providers

---

**Autor**: AI Assistant  
**Fecha**: 2025-11-04  
**Status**: Propuesta para revisión
