# Test Gemini Multi-Tool Use (google_search + functionDeclarations)
# FIXED: google_search and functionDeclarations must be in separate tool objects

$apiKey = "AIzaSyBXiNvcus-VBaL9C3JZx5SkzrY0U9TYvoI"
$model = "gemini-2.0-flash-exp"  # Modelo con soporte multi-tool
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model`:generateContent"

Write-Host "=== Gemini Multi-Tool Use Test (Nov 2025) ===" -ForegroundColor Cyan
Write-Host "Model: $model" -ForegroundColor Yellow
Write-Host ""

# ESTRUCTURA CORRECTA: googleSearch y functionDeclarations en objetos separados
$requestBody = @{
    contents = @(
        @{
            role = "user"
            parts = @(
                @{
                    text = "What's the weather forecast in Madrid for tomorrow? Use real-time search if needed."
                }
            )
        }
    )
    tools = @(
        # Tool 1: Function Declarations
        @{
            functionDeclarations = @(
                @{
                    name = "get_weather_forecast"
                    description = "Get detailed weather forecast for a specific location and number of days"
                    parameters = @{
                        type = "object"
                        properties = @{
                            location = @{
                                type = "string"
                                description = "City name and country code (e.g., 'Madrid, ES')"
                            }
                            days = @{
                                type = "integer"
                                description = "Number of forecast days (1-7)"
                            }
                        }
                        required = @("location", "days")
                    }
                }
            )
        },
        # Tool 2: Google Search (objeto separado)
        @{
            googleSearch = @{}
        }
    )
    generationConfig = @{
        temperature = 0.7
        maxOutputTokens = 2048
    }
} | ConvertTo-Json -Depth 10

Write-Host "Request Body:" -ForegroundColor Green
Write-Host $requestBody
Write-Host ""
Write-Host "=== Sending Request ===" -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$endpoint`?key=$apiKey" `
        -Method Post `
        -ContentType "application/json" `
        -Body $requestBody `
        -ErrorAction Stop
    
    Write-Host "✅ SUCCESS!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Response:" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 10
    
    # Verificar si hay function calls
    if ($response.candidates[0].content.parts | Where-Object { $_.functionCall }) {
        Write-Host ""
        Write-Host "🔧 Model requested function call:" -ForegroundColor Yellow
        $response.candidates[0].content.parts | 
            Where-Object { $_.functionCall } | 
            ForEach-Object { $_.functionCall | ConvertTo-Json -Depth 5 }
    }
    
    # Verificar si usó Google Search
    if ($response.candidates[0].groundingMetadata) {
        Write-Host ""
        Write-Host "🔍 Model used Google Search:" -ForegroundColor Cyan
        $response.candidates[0].groundingMetadata | ConvertTo-Json -Depth 5
    }
    
} catch {
    Write-Host "❌ ERROR!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Status Code: $($_.Exception.Response.StatusCode.Value__)" -ForegroundColor Red
    
    try {
        $errorStream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($errorStream)
        $errorBody = $reader.ReadToEnd()
        $reader.Close()
        
        Write-Host "Error Details:" -ForegroundColor Yellow
        $errorBody | ConvertFrom-Json | ConvertTo-Json -Depth 10
    } catch {
        Write-Host "Could not parse error details" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Cyan