# Test Gemini Multi-Tool Use (SEPARATE Tool objects approach)
# This tests if Gemini expects separate Tool objects in the array
# vs. one combined Tool object with multiple properties

$apiKey = "AIzaSyBXiNvcus-VBaL9C3JZx5SkzrY0U9TYvoI"
$model = "gemini-2.5-pro"
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model`:generateContent"

Write-Host "=== Gemini Multi-Tool Use Test (SEPARATE OBJECTS) ===" -ForegroundColor Cyan
Write-Host "Model: $model" -ForegroundColor Yellow
Write-Host "Testing: Separate Tool objects in array" -ForegroundColor Yellow
Write-Host ""

# Build the request body with SEPARATE Tool objects
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
                },
                @{
                    name = "get_forecast"
                    description = "Get weather forecast for a location"
                    parameters = @{
                        type = "object"
                        properties = @{
                            location = @{
                                type = "string"
                                description = "City name"
                            }
                            days = @{
                                type = "integer"
                                description = "Number of days"
                            }
                        }
                        required = @("location", "days")
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
    
    # Try to get error details from response body
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
