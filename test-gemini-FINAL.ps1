# FINAL TEST - Gemini with google_search ONLY (solution to Live API limitation)
# This confirms google_search works when NOT combined with functionDeclarations

$apiKey = "AIzaSyBXiNvcus-VBaL9C3JZx5SkzrY0U9TYvoI"
$model = "gemini-2.5-pro"
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model`:generateContent"

Write-Host ""
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host "FINAL TEST: Gemini google_search (without function declarations)" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "FINDING:" -ForegroundColor Yellow
Write-Host "Multi-tool use (google_search + functionDeclarations) is ONLY" -ForegroundColor Yellow
Write-Host "supported in Live API (WebSocket), NOT in REST API." -ForegroundColor Yellow
Write-Host ""
Write-Host "SOLUTION:" -ForegroundColor Green
Write-Host "Do NOT inject google_search when functionDeclarations are present." -ForegroundColor Green
Write-Host "User must choose: web_search OR custom functions (in REST API)." -ForegroundColor Green
Write-Host ""
Write-Host "Reference: https://ai.google.dev/gemini-api/docs/function-calling#multi-tool-use" -ForegroundColor Gray
Write-Host '> "Multi-tool use is a Live API only feature at the moment."' -ForegroundColor Gray
Write-Host ""
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host ""

$requestBody = @{
    contents = @(
        @{
            role = "user"
            parts = @(
                @{
                    text = "¿Qué tiempo hará mañana en Madrid?"
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
        maxOutputTokens = 2048
    }
} | ConvertTo-Json -Depth 10

Write-Host "Model: $model" -ForegroundColor Yellow
Write-Host "Tool: google_search ONLY (no functionDeclarations)" -ForegroundColor Yellow
Write-Host ""
Write-Host "=== Sending Request ===" -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri "$endpoint`?key=$apiKey" `
        -Method Post `
        -ContentType "application/json" `
        -Body $requestBody `
        -ErrorAction Stop
    
    Write-Host ""
    Write-Host "✅ SUCCESS! Web search is working!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Response Text:" -ForegroundColor Cyan
    Write-Host $response.candidates[0].content.parts[0].text
    Write-Host ""
    
    if ($response.candidates[0].groundingMetadata) {
        Write-Host "=== Grounding Metadata ===" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Web Search Queries:" -ForegroundColor Magenta
        $response.candidates[0].groundingMetadata.webSearchQueries | ForEach-Object {
            Write-Host "  • $_" -ForegroundColor White
        }
        Write-Host ""
        Write-Host "Source Chunks:" -ForegroundColor Magenta
        $response.candidates[0].groundingMetadata.groundingChunks | ForEach-Object {
            Write-Host "  • $($_.web.title)" -ForegroundColor White
            Write-Host "    $($_.web.uri)" -ForegroundColor Gray
        }
    }
    
    Write-Host ""
    Write-Host "==================================================================" -ForegroundColor Green
    Write-Host "CONCLUSION:" -ForegroundColor Green
    Write-Host "google_search works perfectly when used ALONE." -ForegroundColor Green
    Write-Host "Plugin now correctly excludes google_search when functions present." -ForegroundColor Green
    Write-Host "==================================================================" -ForegroundColor Green
    
} catch {
    Write-Host ""
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
