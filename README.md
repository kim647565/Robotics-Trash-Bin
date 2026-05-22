# Robotics---Trash-Bin

## PHP Dashboard

Open the dashboard through Apache at:

```text
http://localhost/Robotics-Trash-Bin/index.php
```

The app expects a local XAMPP MySQL database named `robo_system` with a `waste_logs` table containing `id`, `timestamp`, `sensor_distance_cm`, `fill_percentage`, and `bin_status`.

To create it, import [database.sql](database.sql) into phpMyAdmin or run it with the MySQL client from your XAMPP installation.