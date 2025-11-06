# Test with gemini-2.0-flash instead of 2.5-pro
# Maybe multi-tool use is only in specific models

$apiKey = "AIzaSyBXiNvcus-VBaL9C3JZx5SkzrY0U9TYvoI"
$model = "gemini-2.0-flash-exp"  # Changed model
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model`:generateContent"

Write-Host "=== Gemini Multi-Tool Test (gemini-2.0-flash-exp) ===" -ForegroundColor Cyan
Write-Host "Model: $model" -ForegroundColor Yellow
Write-Host ""

# SEPARATE objects approach
$requestBody = @{
    contents = @(
        @{
            role = "user"
            parts = @(
                @{
                    text = "What's the weather in Madrid tomorrow?"
                }
            )
        }
    )
    tools = @(
        @{
            functionDeclarations = @(
                @{
                    name = "get_weather"
                    description = "Get current weather for a location"
                    parameters = @{
                        type = "object"
                        properties = @{
                            location = @{
                                type = "string"
                                description = "City name"
                            }
                        }
                        required = @("location")
                    }
                }
            )
        },
        @{
            google_search = @{}
        }
    )
    generationConfig = @{
        temperature = 0.7
        maxOutputTokens = 1024
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
    
} catch {
    Write-Host "❌ ERROR!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Status Code: $($_.Exception.Response.StatusCode.Value__)" -ForegroundColor Red
    Write-Host "Status Description: $($_.Exception.Response.StatusDescription)" -ForegroundColor Red
    Write-Host ""
    
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
