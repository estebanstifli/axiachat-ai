# Test complete flow with Smithery server to verify session ID capture and reuse
$headers = @{
    "Content-Type" = "application/json"
    "Accept" = "application/json, text/event-stream"
    "MCP-Protocol-Version" = "2025-06-18"
}

$url = "https://server.smithery.ai/@isdaniel/mcp_weather_server/mcp?api_key=9e3e9bef-f4be-4b1b-bfd6-141e83cff393&profile=distant-quelea-3cFVdv"

Write-Host "=== Step 1: Initialize ===" -ForegroundColor Cyan
$initBody = @{
    jsonrpc = "2.0"
    id = 1
    method = "initialize"
    params = @{
        protocolVersion = "2025-06-18"
        capabilities = @{
            roots = @{
                listChanged = $false
            }
        }
        clientInfo = @{
            name = "AxiaChat-MCP-Test"
            version = "1.0.0"
        }
    }
} | ConvertTo-Json -Depth 10

try {
    $response = Invoke-WebRequest -Uri $url -Method POST -Headers $headers -Body $initBody -UseBasicParsing
    Write-Host "Status: $($response.StatusCode)" -ForegroundColor Green
    
    # Extract session ID from headers
    $sessionId = $response.Headers['mcp-session-id']
    if ($sessionId) {
        Write-Host "Session ID captured: $sessionId" -ForegroundColor Yellow
    } else {
        Write-Host "WARNING: No mcp-session-id header in response" -ForegroundColor Red
        Write-Host "Response Headers:" -ForegroundColor Gray
        $response.Headers.GetEnumerator() | ForEach-Object { Write-Host "  $($_.Key): $($_.Value)" }
    }
    
    Write-Host "`nResponse body (raw):" -ForegroundColor Gray
    Write-Host $response.Content
    
    # Parse SSE format (extract JSON from "data: " lines)
    Write-Host "`nParsed JSON from SSE:" -ForegroundColor Gray
    $response.Content -split "`n" | ForEach-Object {
        if ($_ -match '^data:\s*(.+)$') {
            $jsonData = $matches[1]
            try {
                $jsonData | ConvertFrom-Json | ConvertTo-Json -Depth 10
            } catch {
                Write-Host "Could not parse: $jsonData" -ForegroundColor Red
            }
        }
    }
    
    if ($sessionId) {
        Write-Host "`n=== Step 2: Send notifications/initialized ===" -ForegroundColor Cyan
        $headers['mcp-session-id'] = $sessionId
        
        $notifyBody = @{
            jsonrpc = "2.0"
            method = "notifications/initialized"
            params = @{}
        } | ConvertTo-Json -Depth 10
        
        $notifyResponse = Invoke-WebRequest -Uri $url -Method POST -Headers $headers -Body $notifyBody -UseBasicParsing
        Write-Host "Status: $($notifyResponse.StatusCode)" -ForegroundColor Green
        Write-Host "Response (raw): $($notifyResponse.Content)" -ForegroundColor Gray
        
        Write-Host "`n=== Step 3: List tools ===" -ForegroundColor Cyan
        $toolsBody = @{
            jsonrpc = "2.0"
            id = 2
            method = "tools/list"
            params = @{}
        } | ConvertTo-Json -Depth 10
        
        $toolsResponse = Invoke-WebRequest -Uri $url -Method POST -Headers $headers -Body $toolsBody -UseBasicParsing
        Write-Host "Status: $($toolsResponse.StatusCode)" -ForegroundColor Green
        Write-Host "`nTools response (raw):" -ForegroundColor Gray
        Write-Host $toolsResponse.Content
        
        # Parse SSE format for tools
        Write-Host "`nParsed tools from SSE:" -ForegroundColor Yellow
        $toolsResponse.Content -split "`n" | ForEach-Object {
            if ($_ -match '^data:\s*(.+)$') {
                $jsonData = $matches[1]
                try {
                    $parsed = $jsonData | ConvertFrom-Json
                    if ($parsed.result.tools) {
                        Write-Host "`nFound $($parsed.result.tools.Count) tools:" -ForegroundColor Green
                        $parsed.result.tools | ForEach-Object {
                            Write-Host "  - $($_.name): $($_.description)" -ForegroundColor Cyan
                        }
                    }
                    $jsonData | ConvertFrom-Json | ConvertTo-Json -Depth 10
                } catch {
                    Write-Host "Could not parse: $jsonData" -ForegroundColor Red
                }
            }
        }
    }
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
        $body = $reader.ReadToEnd()
        Write-Host "Response body: $body" -ForegroundColor Gray
    }
}
