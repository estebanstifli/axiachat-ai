# Test Gemini Google Search Only (without function declarations)
# This tests if google_search works alone without function declarations

$apiKey = "AIzaSyBXiNvcus-VBaL9C3JZx5SkzrY0U9TYvoI"
$model = "gemini-2.5-pro"
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model`:generateContent"

Write-Host "=== Gemini Google Search ONLY Test ===" -ForegroundColor Cyan
Write-Host "Model: $model" -ForegroundColor Yellow
Write-Host "Testing: google_search without function declarations" -ForegroundColor Yellow
Write-Host ""

# Build the request body with ONLY google_search
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
    
    # Check for grounding metadata
    if ($response.candidates[0].groundingMetadata) {
        Write-Host ""
        Write-Host "=== Grounding Metadata Found ===" -ForegroundColor Green
        Write-Host "Web Search Queries:" -ForegroundColor Yellow
        $response.candidates[0].groundingMetadata.webSearchQueries
        Write-Host ""
        Write-Host "Grounding Chunks:" -ForegroundColor Yellow
        $response.candidates[0].groundingMetadata.groundingChunks | ForEach-Object {
            Write-Host "  - $($_.web.title): $($_.web.uri)"
        }
    }
    
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
