# Guía de Implementación: Proveedor Gemini

## 1. Información General

### Descripción
Google Gemini es una familia de modelos multimodales de última generación que destacan por:
- **Razonamiento avanzado**: Modelos 2.5 con "thinking mode" para problemas complejos
- **Contexto extenso**: Hasta 1 millón de tokens
- **Multimodalidad nativa**: Texto, imagen, audio, video
- **Velocidad y eficiencia**: Modelos optimizados para diferentes casos de uso
- **Grounding con Google Search**: Búsqueda web nativa integrada

---

## 2. Modelos Disponibles

### Gemini 2.5 Pro (`gemini-2.5-pro`)
- **Descripción**: Modelo más avanzado para razonamiento complejo y coding
- **Casos de uso**: Problemas complejos de razonamiento, código avanzado, STEM
- **Características especiales**: 
  - Thinking mode habilitado por defecto
  - Mejor rendimiento en tareas de razonamiento
- **Precios (por 1M tokens)**:
  - **Gratis**: Input free, Output free
  - **Pago**: Input $0.35 (texto) / $2.10 (audio/video), Output $1.40 (texto) / $8.40 (audio)

### Gemini 2.5 Flash (`gemini-2.5-flash`)
- **Descripción**: Primer modelo híbrido de razonamiento con ventana de 1M tokens
- **Casos de uso**: Balance precio-rendimiento, uso agéntico, procesamiento a escala
- **Características especiales**:
  - Contexto de 1 millón de tokens
  - Thinking budgets configurables
  - Excelente para agentes
- **Precios (por 1M tokens)**:
  - **Gratis**: Input free, Output free
  - **Pago**: Input $0.30 (texto) / $1.80 (audio/video), Output $1.20 (texto) / $7.20 (audio)

### Gemini 2.5 Flash-Lite (`gemini-2.5-flash-lite`)
- **Descripción**: Modelo más pequeño y rentable para uso a gran escala
- **Casos de uso**: Alta throughput, tareas masivas, bajo costo
- **Características especiales**:
  - Más rápido y económico
  - Ideal para volúmenes altos
- **Precios (por 1M tokens)**:
  - **Gratis**: Input free, Output free
  - **Pago**: Input $0.075 (texto) / $0.45 (audio/video), Output $0.30 (texto) / $1.80 (audio)

### Gemini 2.0 Flash (`gemini-2.0-flash`)
- **Descripción**: Modelo multimodal balanceado de segunda generación
- **Casos de uso**: Rendimiento general equilibrado, agentes
- **Características especiales**:
  - 1 millón de tokens de contexto
  - Construido para la era de los agentes
  - Generación de imágenes nativa ($0.039/imagen)
- **Precios (por 1M tokens)**:
  - **Gratis**: Input free, Output free
  - **Pago**: Input $0.30 (texto) / $1.80 (imagen/audio/video), Output $1.20 (texto) / $7.20 (audio)

### Gemini 2.0 Flash-Lite (`gemini-2.0-flash-lite`)
- **Descripción**: Modelo pequeño y eficiente de segunda generación
- **Casos de uso**: Uso masivo, bajo costo
- **Precios (por 1M tokens)**:
  - **Gratis**: Input free, Output free
  - **Pago**: Input $0.075 (texto) / $0.45 (imagen/audio/video), Output $0.30 (texto) / $1.80 (audio)

---

## 3. API REST - Estructura de Endpoints

### Endpoint Base
```
https://generativelanguage.googleapis.com/v1beta/
```

### Método Principal: `models.generateContent`
```
POST https://generativelanguage.googleapis.com/v1beta/{model=models/*}:generateContent
```

**Path Parameter:**
- `model` (required): Nombre del modelo en formato `models/{model_name}`
  - Ejemplo: `models/gemini-2.5-flash`

### Método de Streaming: `models.streamGenerateContent`
```
POST https://generativelanguage.googleapis.com/v1beta/{model=models/*}:streamGenerateContent
```

**Respuesta**: Stream de objetos `GenerateContentResponse`

---

## 4. Autenticación

### API Key
- **Método**: Header HTTP
- **Obtención**: [https://aistudio.google.com/apikey](https://aistudio.google.com/apikey)
- **Variable de entorno recomendada**: `GEMINI_API_KEY`
- **Header**: `x-goog-api-key: YOUR_API_KEY` o como query param `?key=YOUR_API_KEY`

### Ejemplo con Python SDK:
```python
from google import genai

# Opción 1: Variable de entorno (recomendado)
client = genai.Client()  # Lee GEMINI_API_KEY automáticamente

# Opción 2: Explícito
client = genai.Client(api_key="YOUR_API_KEY")
```

---

## 5. Estructura de Request

### Request Body (JSON)
```json
{
  "contents": [
    {
      "role": "user",
      "parts": [
        {"text": "Mensaje del usuario"}
      ]
    }
  ],
  "systemInstruction": {
    "parts": [
      {"text": "Instrucciones del sistema"}
    ]
  },
  "generationConfig": {
    "temperature": 0.7,
    "maxOutputTokens": 2048,
    "topP": 0.95,
    "topK": 40,
    "stopSequences": [],
    "responseMimeType": "text/plain"
  },
  "safetySettings": [
    {
      "category": "HARM_CATEGORY_HARASSMENT",
      "threshold": "BLOCK_MEDIUM_AND_ABOVE"
    }
  ],
  "tools": [],
  "toolConfig": {}
}
```

### Campos Clave

#### `contents[]` (required)
- **Tipo**: Array de objetos `Content`
- **Descripción**: Conversación actual con el modelo
- **Estructura**:
  ```json
  {
    "role": "user",  // o "model"
    "parts": [
      {"text": "Contenido texto"},
      {"inline_data": {"mime_type": "image/jpeg", "data": "base64..."}}
    ]
  }
  ```

#### `systemInstruction` (optional)
- **Tipo**: Objeto `Content`
- **Descripción**: Instrucciones del sistema (similar a "system" en OpenAI)
- **Solo texto**: Actualmente solo soporta texto

#### `generationConfig` (optional)
- **temperature** (number): 0.0-2.0, controla aleatoriedad (default varía por modelo)
- **maxOutputTokens** (integer): Tokens máximos en respuesta
- **topP** (number): Nucleus sampling (default varía por modelo)
- **topK** (integer): Top-K sampling
- **stopSequences** (string[]): Hasta 5 secuencias de parada
- **responseMimeType** (string): `text/plain`, `application/json`, `text/x.enum`
- **responseSchema** (object): Schema OpenAPI para JSON mode
- **candidateCount** (integer): Número de respuestas alternativas
- **seed** (integer): Semilla para reproducibilidad
- **presencePenalty** (number): Penalización por presencia
- **frequencyPenalty** (number): Penalización por frecuencia
- **thinkingConfig** (object): Configuración de thinking mode
  ```json
  {
    "includeThoughts": true,
    "thinkingBudget": 8192  // 0 = desactivar thinking
  }
  ```

#### `tools[]` (optional)
- **Tipo**: Array de objetos `Tool`
- **Descripción**: Herramientas disponibles para el modelo
- **Tipos soportados**: 
  - `Function`: Function calling
  - `codeExecution`: Ejecución de código
  - `googleSearch`: Grounding con Google Search (nativo)

#### `safetySettings[]` (optional)
- **Categorías**: 
  - `HARM_CATEGORY_HARASSMENT`
  - `HARM_CATEGORY_HATE_SPEECH`
  - `HARM_CATEGORY_SEXUALLY_EXPLICIT`
  - `HARM_CATEGORY_DANGEROUS_CONTENT`
  - `HARM_CATEGORY_CIVIC_INTEGRITY`
- **Thresholds**:
  - `BLOCK_NONE`: Permitir todo
  - `BLOCK_LOW_AND_ABOVE`
  - `BLOCK_MEDIUM_AND_ABOVE`
  - `BLOCK_ONLY_HIGH`

---

## 6. Estructura de Response

### Response Body (JSON)
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {"text": "Respuesta del modelo"}
        ],
        "role": "model"
      },
      "finishReason": "STOP",
      "safetyRatings": [],
      "citationMetadata": {
        "citationSources": []
      },
      "tokenCount": 150,
      "groundingMetadata": {},
      "index": 0
    }
  ],
  "usageMetadata": {
    "promptTokenCount": 50,
    "candidatesTokenCount": 150,
    "totalTokenCount": 200,
    "thoughtsTokenCount": 0
  },
  "modelVersion": "gemini-2.5-flash-001"
}
```

### Campos Clave

#### `candidates[]`
- **content**: Contenido generado con `parts[]` y `role`
- **finishReason**: Razón de finalización
  - `STOP`: Parada natural
  - `MAX_TOKENS`: Límite alcanzado
  - `SAFETY`: Bloqueado por seguridad
  - `RECITATION`: Recitación detectada
  - Otros...
- **safetyRatings[]**: Ratings de seguridad por categoría
- **citationMetadata**: Información de citas/fuentes
- **groundingMetadata**: Metadata de grounding (Google Search, Maps, etc.)
  - `groundingChunks[]`: Fuentes web/contexto
  - `groundingSupports[]`: Soporte con confidence scores
  - `webSearchQueries[]`: Queries usadas
- **tokenCount**: Tokens en esta respuesta

#### `usageMetadata`
- **promptTokenCount**: Tokens en el prompt
- **candidatesTokenCount**: Tokens en todas las respuestas
- **totalTokenCount**: Total (prompt + candidates)
- **thoughtsTokenCount**: Tokens de "thinking" (modelos 2.5)
- **cachedContentTokenCount**: Tokens del cache (si aplica)

---

## 7. Características Especiales

### 7.1 Thinking Mode (Modelos 2.5)
- **Descripción**: Razonamiento explícito antes de responder
- **Habilitado por defecto** en modelos 2.5 Pro/Flash
- **Configuración**:
  ```json
  "generationConfig": {
    "thinkingConfig": {
      "includeThoughts": true,    // Incluir pensamientos en respuesta
      "thinkingBudget": 8192      // 0 = desactivar, max tokens para pensar
    }
  }
  ```
- **Tokens de thinking**: Facturados en `thoughtsTokenCount` (incluidos en output price)

### 7.2 System Instructions
- **Diferencia con OpenAI**: No es un mensaje más, va en `systemInstruction` separado
- **Estructura**:
  ```json
  "systemInstruction": {
    "parts": [{"text": "Eres un asistente..."}]
  }
  ```

### 7.3 Grounding con Google Search
- **Nativo**: No requiere API externa como OpenAI
- **Configuración** (vía tools):
  ```json
  "tools": [
    {
      "googleSearch": {}  // Habilita búsqueda web
    }
  ]
  ```
- **Resultado**: `groundingMetadata` en respuesta con:
  - `groundingChunks[]`: Fuentes web (uri, title, text)
  - `groundingSupports[]`: Segmentos con soporte y confidence
  - `webSearchQueries[]`: Queries ejecutadas

### 7.4 JSON Mode / Structured Output
- **Método 1**: `responseMimeType: "application/json"`
- **Método 2**: Con schema OpenAPI
  ```json
  "generationConfig": {
    "responseMimeType": "application/json",
    "responseSchema": {
      "type": "object",
      "properties": {
        "title": {"type": "string"},
        "summary": {"type": "string"}
      },
      "required": ["title", "summary"]
    }
  }
  ```

### 7.5 Context Caching
- **Propósito**: Reducir costo y latencia en prompts repetitivos
- **Implementación**: Via `cachedContent` field
- **Precios especiales**: Más barato que tokens normales

### 7.6 Multimodal
- **Texto + Imagen + Audio + Video**: En un solo request
- **Formato**: Via `parts[]` con `inline_data` o `file_data`
- **Pricing diferenciado**: Imagen/audio/video más caro que texto

---

## 8. Comparación con OpenAI y Claude

| Aspecto | OpenAI | Claude | Gemini |
|---------|--------|--------|--------|
| **Estructura de mensajes** | `messages[]` con `role` + `content` | `messages[]` con `role` + `content` | `contents[]` con `role` + `parts[]` |
| **System prompt** | Mensaje con `role: "system"` | Parámetro `system` separado | `systemInstruction` separado |
| **Parámetros generación** | En body raíz (`temperature`, etc.) | En body raíz | Objeto `generationConfig` |
| **Tools/Functions** | `tools[]` en body raíz | `tools[]` en body raíz | `tools[]` en body raíz |
| **Web Search** | Via tool `web_search` (Responses API) | Server-side tool `web_search_20250305` | Tool nativo `googleSearch` |
| **Thinking mode** | No nativo | No nativo | **Nativo en 2.5** |
| **Multimodal** | Soportado (texto+imagen+audio) | Soportado (texto+imagen+PDF) | **Nativo** (texto+imagen+audio+video) |
| **Contexto máximo** | 128k (GPT-4), 200k (GPT-4 Turbo) | 200k | **1 millón** (Flash) |
| **Streaming** | `stream: true` | `stream: true` | Endpoint separado `:streamGenerateContent` |
| **Pricing tokens** | `usage.prompt_tokens` + `completion_tokens` | `usage.input_tokens` + `output_tokens` | `usageMetadata` con desglose |

---

## 9. Mapeo para Nuestra Implementación

### 9.1 Conversión de Mensajes
**De nuestro formato interno → Gemini:**

```php
// Nuestro formato interno (similar a OpenAI)
$messages = [
    ['role' => 'system', 'content' => 'Eres un asistente...'],
    ['role' => 'user', 'content' => 'Hola'],
    ['role' => 'assistant', 'content' => 'Hola, ¿en qué puedo ayudarte?'],
    ['role' => 'user', 'content' => '¿Qué tiempo hace?']
];

// Conversión a Gemini
$systemInstruction = null;
$contents = [];

foreach ($messages as $msg) {
    if ($msg['role'] === 'system') {
        // System va separado
        $systemInstruction = [
            'parts' => [['text' => $msg['content']]]
        ];
    } else {
        // User/assistant → contents
        $role = $msg['role'] === 'assistant' ? 'model' : 'user';
        $contents[] = [
            'role' => $role,
            'parts' => [['text' => $msg['content']]]
        ];
    }
}

$request_body = [
    'contents' => $contents,
    'systemInstruction' => $systemInstruction,
    'generationConfig' => [
        'temperature' => $params['temperature'] ?? 0.7,
        'maxOutputTokens' => $params['max_tokens'] ?? 2048,
    ]
];
```

### 9.2 Mapeo de Parámetros

| Nuestro | OpenAI | Claude | Gemini |
|---------|--------|--------|--------|
| `temperature` | `temperature` | `temperature` | `generationConfig.temperature` |
| `max_tokens` | `max_tokens` | `max_tokens` | `generationConfig.maxOutputTokens` |
| `top_p` | `top_p` | `top_p` | `generationConfig.topP` |
| `stop` | `stop` | `stop_sequences` | `generationConfig.stopSequences` |
| `presence_penalty` | `presence_penalty` | - | `generationConfig.presencePenalty` |
| `frequency_penalty` | `frequency_penalty` | - | `generationConfig.frequencyPenalty` |

### 9.3 Extracción de Respuesta

```php
$response = json_decode($api_response, true);

// Texto de respuesta
$text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Razón de finalización
$finish_reason = $response['candidates'][0]['finishReason'] ?? 'UNKNOWN';

// Tokens usage
$usage = [
    'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
    'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
    'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
    'thoughts_tokens' => $response['usageMetadata']['thoughtsTokenCount'] ?? 0, // Solo 2.5
];

// Grounding metadata (si se usó Google Search)
$grounding = $response['candidates'][0]['groundingMetadata'] ?? null;
if ($grounding) {
    $sources = $grounding['groundingChunks'] ?? [];
    $web_queries = $grounding['webSearchQueries'] ?? [];
}
```

### 9.4 Cálculo de Costos

```php
function calculate_cost_gemini($usage, $model) {
    // Precios por 1M tokens (tier pago)
    $pricing = [
        'gemini-2.5-pro' => ['input' => 0.35, 'output' => 1.40],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 1.20],
        'gemini-2.5-flash-lite' => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.0-flash' => ['input' => 0.30, 'output' => 1.20],
        'gemini-2.0-flash-lite' => ['input' => 0.075, 'output' => 0.30],
    ];
    
    $prices = $pricing[$model] ?? ['input' => 0, 'output' => 0];
    
    $input_cost = ($usage['prompt_tokens'] / 1000000) * $prices['input'];
    
    // Output incluye tokens de thinking (si existen)
    $output_tokens = $usage['completion_tokens'];
    $output_cost = ($output_tokens / 1000000) * $prices['output'];
    
    return [
        'input_cost' => $input_cost,
        'output_cost' => $output_cost,
        'total_cost' => $input_cost + $output_cost
    ];
}
```

### 9.5 Manejo de Errores

```php
// Errores comunes
if (isset($response['error'])) {
    $error_code = $response['error']['code'] ?? 0;
    $error_message = $response['error']['message'] ?? 'Unknown error';
    
    // Mapeo de códigos HTTP comunes
    switch ($error_code) {
        case 400:
            // Bad request: validar estructura
            break;
        case 401:
            // API key inválida
            break;
        case 429:
            // Rate limit excedido
            break;
        case 500:
        case 503:
            // Error del servidor
            break;
    }
}

// Verificar bloqueo por seguridad
$finish_reason = $response['candidates'][0]['finishReason'] ?? '';
if ($finish_reason === 'SAFETY') {
    $safety_ratings = $response['candidates'][0]['safetyRatings'] ?? [];
    // Analizar qué categoría bloqueó
}
```

---

## 10. Ejemplo Completo: Request/Response

### Request (cURL)
```bash
curl -X POST \
  "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "contents": [
      {
        "role": "user",
        "parts": [{"text": "¿Cómo funciona la IA?"}]
      }
    ],
    "systemInstruction": {
      "parts": [{"text": "Eres un experto en IA que explica conceptos de forma simple."}]
    },
    "generationConfig": {
      "temperature": 0.7,
      "maxOutputTokens": 500,
      "topP": 0.95,
      "topK": 40
    }
  }'
```

### Response
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "La IA funciona mediante algoritmos que aprenden patrones de datos..."
          }
        ],
        "role": "model"
      },
      "finishReason": "STOP",
      "safetyRatings": [
        {
          "category": "HARM_CATEGORY_SEXUALLY_EXPLICIT",
          "probability": "NEGLIGIBLE"
        },
        {
          "category": "HARM_CATEGORY_HATE_SPEECH",
          "probability": "NEGLIGIBLE"
        },
        {
          "category": "HARM_CATEGORY_HARASSMENT",
          "probability": "NEGLIGIBLE"
        },
        {
          "category": "HARM_CATEGORY_DANGEROUS_CONTENT",
          "probability": "NEGLIGIBLE"
        }
      ],
      "tokenCount": 87,
      "index": 0
    }
  ],
  "usageMetadata": {
    "promptTokenCount": 25,
    "candidatesTokenCount": 87,
    "totalTokenCount": 112
  },
  "modelVersion": "gemini-2.5-flash-001"
}
```

---

## 11. Implementación en `class-gemini-provider.php`

### Estructura Básica
```php
<?php
/**
 * Gemini Provider Class
 * Google Gemini API integration for AIChat plugin
 */

if (!defined('ABSPATH')) exit;

class AIChat_Gemini_Provider implements AIChat_Provider {
    
    private $api_key;
    private $api_base = 'https://generativelanguage.googleapis.com/v1beta';
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?? get_option('aichat_gemini_api_key', '');
    }
    
    /**
     * Main chat method - unified entry point
     */
    public function chat($messages, $params = []) {
        // Siempre usar chat_simple (no tools por ahora)
        return $this->chat_simple($messages, $params);
    }
    
    /**
     * Simple text chat (no tools)
     */
    protected function chat_simple($messages, $params) {
        // 1. Convertir mensajes a formato Gemini
        $gemini_request = $this->convert_messages_to_gemini($messages, $params);
        
        // 2. Hacer request
        $model = $params['model'] ?? 'gemini-2.5-flash';
        $endpoint = "{$this->api_base}/models/{$model}:generateContent";
        
        $response = $this->make_request($endpoint, $gemini_request);
        
        // 3. Procesar respuesta
        return $this->process_response($response, $params);
    }
    
    /**
     * Convert our message format to Gemini format
     */
    protected function convert_messages_to_gemini($messages, $params) {
        $system_instruction = null;
        $contents = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system_instruction = [
                    'parts' => [['text' => $msg['content']]]
                ];
            } else {
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]]
                ];
            }
        }
        
        $request = ['contents' => $contents];
        
        if ($system_instruction) {
            $request['systemInstruction'] = $system_instruction;
        }
        
        // Generation config
        $request['generationConfig'] = [
            'temperature' => $params['temperature'] ?? 0.7,
            'maxOutputTokens' => $params['max_tokens'] ?? 2048,
            'topP' => $params['top_p'] ?? 0.95,
        ];
        
        // Disable thinking by default (can be enabled via params)
        if (isset($params['enable_thinking']) && $params['enable_thinking']) {
            $request['generationConfig']['thinkingConfig'] = [
                'includeThoughts' => true,
                'thinkingBudget' => $params['thinking_budget'] ?? 8192
            ];
        } else {
            // Explicitly disable for 2.5 models
            $request['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => 0
            ];
        }
        
        return $request;
    }
    
    /**
     * Make HTTP request to Gemini API
     */
    protected function make_request($endpoint, $body) {
        $url = add_query_arg('key', $this->api_key, $endpoint);
        
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Process Gemini API response
     */
    protected function process_response($response, $params) {
        // Error handling
        if (isset($response['error'])) {
            return [
                'error' => $response['error']['message'] ?? 'Unknown Gemini API error',
                'error_code' => $response['error']['code'] ?? 0
            ];
        }
        
        // Extract text
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Extract usage
        $usage = [
            'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
            'thoughts_tokens' => $response['usageMetadata']['thoughtsTokenCount'] ?? 0,
        ];
        
        // Finish reason
        $finish_reason = $response['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        
        return [
            'message' => $text,
            'usage' => $usage,
            'finish_reason' => $finish_reason,
            'model' => $params['model'] ?? 'gemini-2.5-flash',
            'raw_response' => $response // Para debugging
        ];
    }
    
    /**
     * Calculate cost based on usage
     */
    public function calculate_cost($usage, $model) {
        $pricing = [
            'gemini-2.5-pro' => ['input' => 0.35, 'output' => 1.40],
            'gemini-2.5-flash' => ['input' => 0.30, 'output' => 1.20],
            'gemini-2.5-flash-lite' => ['input' => 0.075, 'output' => 0.30],
            'gemini-2.0-flash' => ['input' => 0.30, 'output' => 1.20],
            'gemini-2.0-flash-lite' => ['input' => 0.075, 'output' => 0.30],
        ];
        
        $prices = $pricing[$model] ?? ['input' => 0, 'output' => 0];
        
        $input_cost = ($usage['prompt_tokens'] / 1000000) * $prices['input'];
        $output_cost = ($usage['completion_tokens'] / 1000000) * $prices['output'];
        
        return [
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $input_cost + $output_cost,
            'currency' => 'USD'
        ];
    }
}
```

---

## 12. Próximos Pasos de Implementación

### Paso 3: Settings Add-on
1. **Archivo**: `includes/add-ons/gemini-settings.php`
2. **Campos**:
   - Enable/disable Gemini
   - API Key input
   - Default model selector
   - Enable thinking mode toggle
3. **Registro**: Hook en `aichat_providers_registry`

### Paso 4: Actualizar `bots.js`
```javascript
// Añadir en sección de proveedores
'gemini': {
    label: 'Google Gemini',
    models: [
        { value: 'gemini-2.5-pro', label: 'Gemini 2.5 Pro (Razonamiento)' },
        { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash (Balanceado)' },
        { value: 'gemini-2.5-flash-lite', label: 'Gemini 2.5 Flash-Lite (Rápido)' },
        { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash' },
        { value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash-Lite' }
    ]
}
```

### Paso 5: Registro en Provider Registry
```php
// En includes/class-aichat-core.php o similar
add_filter('aichat_providers_registry', function($providers) {
    if (get_option('aichat_gemini_enabled', false)) {
        $providers['gemini'] = new AIChat_Gemini_Provider();
    }
    return $providers;
});
```

---

## 13. Consideraciones Importantes

### Límites de Rate
- **Free tier**: Límites generosos pero no especificados públicamente
- **Paid tier**: Límites más altos, contactar ventas para detalles

### Thinking Mode
- **Por defecto ON en 2.5**: Desactivar explícitamente si no se necesita (ahorro de tokens)
- **Facturación**: Tokens de thinking cuentan como output tokens

### Multimodal
- **Implementación futura**: Por ahora solo texto
- **Extensión fácil**: Añadir soporte `inline_data` en `parts[]`

### Grounding
- **Google Search nativo**: Alternativa a nuestro web_search actual
- **Implementación futura**: Añadir tool `googleSearch` cuando se expanda soporte de tools

### Streaming
- **Endpoint diferente**: Requiere SSE parsing
- **Implementación futura**: Para respuestas en tiempo real

---

## 14. Referencias

- **Documentación oficial**: [https://ai.google.dev/gemini-api/docs](https://ai.google.dev/gemini-api/docs)
- **API Reference**: [https://ai.google.dev/api/generate-content](https://ai.google.dev/api/generate-content)
- **Pricing**: [https://ai.google.dev/gemini-api/docs/pricing](https://ai.google.dev/gemini-api/docs/pricing)
- **Get API Key**: [https://aistudio.google.com/apikey](https://aistudio.google.com/apikey)
- **Python SDK**: [https://googleapis.github.io/python-genai/](https://googleapis.github.io/python-genai/)
- **Cookbook**: [https://github.com/google-gemini/cookbook](https://github.com/google-gemini/cookbook)

---

**Fecha**: 2025-01-31  
**Versión API**: v1beta  
**Autor**: AIChat Development Team
