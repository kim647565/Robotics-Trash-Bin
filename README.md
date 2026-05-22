# Robotics---Trash-Bin

## PHP Dashboard

Open the dashboard through Apache at:

```text
http://localhost/Robotics-Trash-Bin/index.php
```

The app expects a local XAMPP MySQL database named `robo_system` with a `waste_logs` table containing `id`, `timestamp`, `sensor_distance_cm`, `fill_percentage`, and `bin_status`.

To create it, import [database.sql](database.sql) into phpMyAdmin or run it with the MySQL client from your XAMPP installation.

## Arduino Live Data Flow

`index.php` is the dashboard UI.  
`sense.php` is the backend endpoint that stores a sensor reading.

Because Arduino UNO sends values over USB Serial (not HTTP), you need a laptop bridge process:

1. Upload Arduino sketch.
2. Close Arduino Serial Monitor (it locks the COM port).
3. Start Apache + MySQL in XAMPP.
4. Run:

```powershell
powershell -ExecutionPolicy Bypass -File .\serial-bridge.ps1 -Port COM3 -BaudRate 9600 -SenseUrl "http://localhost/Robotics-Trash-Bin/sense.php"
```

5. Open dashboard:

```text
http://localhost/Robotics-Trash-Bin/index.php
```

If your board is not `COM3`, update `-Port`.
