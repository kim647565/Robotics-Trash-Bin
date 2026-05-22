param(
	[string]$Port = "COM3",
	[int]$BaudRate = 9600,
	[string]$SenseUrl = "http://localhost/Robotics-Trash-Bin/sense.php"
)

Add-Type -AssemblyName System

$serial = New-Object System.IO.Ports.SerialPort $Port, $BaudRate, "None", 8, "One"
$serial.ReadTimeout = 1500
$serial.NewLine = "`n"

try {
	$serial.Open()
	Write-Host "Listening on $Port @ $BaudRate baud..."
	Write-Host "Forwarding numeric lines to: $SenseUrl"
	Write-Host "Press Ctrl+C to stop."

	while ($true) {
		try {
			$line = $serial.ReadLine().Trim()
		} catch {
			continue
		}

		if ($line -match '^-?\d+(\.\d+)?$') {
			$distance = [double]$line
			$payload = @{ sensor_distance_cm = $distance } | ConvertTo-Json -Compress

			try {
				$response = Invoke-RestMethod -Uri $SenseUrl -Method Post -ContentType "application/json" -Body $payload
				$status = if ($response.data.bin_status) { $response.data.bin_status } else { "UNKNOWN" }
				$fill = if ($response.data.fill_percentage -ne $null) { "$($response.data.fill_percentage)%" } else { "N/A" }
				Write-Host "OK  distance=${distance}cm  fill=$fill  status=$status"
			} catch {
				Write-Warning "POST failed for distance=${distance}cm :: $($_.Exception.Message)"
			}
		} else {
			Write-Host "Ignored non-numeric serial line: $line"
		}
	}
}
finally {
	if ($serial.IsOpen) {
		$serial.Close()
	}
}
