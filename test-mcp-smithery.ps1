# Test MCP Smithery Server
# This script tests the exact same request our plugin makes

$url = "https://server.smithery.ai/@isdaniel/mcp_weather_server/mcp?api_key=9e3e9bef-f4be-4b1b-bfd6-141e83cff393&profile=distant-quelea-3cFVdv"

$body = @{
    jsonrpc = "2.0"
    id = "test-123"
    method = "initialize"
    params = @{
        protocolVersion = "2025-06-18"
        capabilities = @{
            elicitation = @{}
            sampling = @{}
        }
        clientInfo = @{
            name = "axiachat-ai-mcp"
            version = "1.0.0"
        }
    }
} | ConvertTo-Json -Depth 10

Write-Host "=== MCP Smithery Test ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "URL: $url" -ForegroundColor Yellow
Write-Host ""
Write-Host "Request Body:" -ForegroundColor Yellow
Write-Host $body
Write-Host ""
Write-Host "Sending request..." -ForegroundColor Green
Write-Host ""

try {
    $response = Invoke-WebRequest -Uri $url `
        -Method POST `
        -ContentType "application/json" `
        -Headers @{
            "Accept" = "application/json, text/event-stream"
            "MCP-Protocol-Version" = "2025-06-18"
        } `
        -Body $body `
        -UseBasicParsing

    Write-Host "=== RESPONSE ===" -ForegroundColor Green
    Write-Host "Status Code: $($response.StatusCode)" -ForegroundColor Cyan
    Write-Host "Content-Type: $($response.Headers['Content-Type'])" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Raw Response:" -ForegroundColor Yellow
    Write-Host $response.Content
    Write-Host ""
    
    # If SSE format, try to extract JSON
    if ($response.Headers['Content-Type'] -like "*text/event-stream*") {
        Write-Host "Detected SSE format, extracting data..." -ForegroundColor Magenta
        $lines = $response.Content -split "`n"
        foreach ($line in $lines) {
            if ($line -like "data: *") {
                $jsonData = $line.Substring(6).Trim()
                Write-Host "Extracted JSON:" -ForegroundColor Green
                Write-Host $jsonData
                
                # Try to parse and pretty-print
                try {
                    $parsed = $jsonData | ConvertFrom-Json
                    Write-Host ""
                    Write-Host "Parsed (pretty):" -ForegroundColor Green
                    $parsed | ConvertTo-Json -Depth 10
                } catch {
                    Write-Host "Could not parse JSON: $_" -ForegroundColor Red
                }
            }
        }
    }
    
} catch {
    Write-Host "=== ERROR ===" -ForegroundColor Red
    Write-Host $_.Exception.Message
    Write-Host ""
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response Body:" -ForegroundColor Yellow
        Write-Host $responseBody
    }
}

Write-Host ""
Write-Host "=== END TEST ===" -ForegroundColor Cyan
