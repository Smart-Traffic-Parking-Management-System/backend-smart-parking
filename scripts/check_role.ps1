param(
  [string]$GatewayUrl = 'http://localhost:3000',
  [string]$username = 'admin',
  [string]$password = 'admin123'
)

# Request access token (password grant)
$body = @{ grant_type = 'password'; username = $username; password = $password }
$response = Invoke-RestMethod -Method Post -Uri "$GatewayUrl/oauth/token" -Body $body -ContentType 'application/x-www-form-urlencoded' -ErrorAction Stop

$token = $response.access_token
if (-not $token) {
  Write-Error "No access_token returned"
  exit 2
}

# Decode JWT payload (base64url)
function Decode-JwtPayload($jwt) {
  $parts = $jwt -split '\.'
  if ($parts.Length -lt 2) { return $null }
  $payload = $parts[1]
  # pad base64
  $padding = 4 - ($payload.Length % 4)
  if ($padding -lt 4) { $payload += '=' * $padding }
  $payload = $payload.Replace('-','+').Replace('_','/')
  $bytes = [System.Convert]::FromBase64String($payload)
  return [System.Text.Encoding]::UTF8.GetString($bytes) | ConvertFrom-Json
}

$payload = Decode-JwtPayload $token
Write-Host "Access token obtained. Role: $($payload.role) | user_id/citizen_id: $($payload.user_id)"
Write-Host "Full JWT payload:`n$($payload | ConvertTo-Json -Depth 5)" 
