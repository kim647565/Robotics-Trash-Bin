# Robotics---Trash-Bin

This repository contains two dashboards and helper scripts for an IoT smart waste bin project:

- A PHP + Apache dashboard (legacy) served from XAMPP: `index.php` + `sense.php`.
- A Python Streamlit monitoring app: `app.py` (real-time local UI that reads serial and writes to the same MySQL `robo_system` database).

Overview
--------
Both dashboards expect a local MySQL database named `robo_system` with a table `waste_logs` that has the columns: `id`, `timestamp`, `sensor_distance_cm`, `fill_percentage`, `bin_status`.

Create the database and table by importing the provided SQL file into phpMyAdmin or using the MySQL client:

```text
Import database.sql (or run it from your XAMPP MySQL client)
```

Windows setup (Python Streamlit app)
----------------------------------
1. Install Python 3.10+ (or the version you prefer).
2. Create and activate a virtual environment in the repo root:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1    # PowerShell
# or: .\.venv\Scripts\activate  # cmd
```

3. Install Python dependencies:

```powershell
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

4. Verify DB connectivity (uses values from `app.py` by default — host: `localhost`, port: `3307`, user: `root`, password: `123456789`).

```powershell
.\.venv\Scripts\python.exe scripts/test_db.py
.\.venv\Scripts\python.exe scripts/check_db.py
```

5. List COM ports and test serial reading:

```powershell
.\.venv\Scripts\python.exe scripts/list_ports.py
.\.venv\Scripts\python.exe scripts/read_serial.py COM4   # replace COM4 with your port
```

6. Run the Streamlit app:

```powershell
.\.venv\Scripts\python.exe -m streamlit run app.py
```

Streamlit app notes
-------------------
- The Streamlit UI includes a sidebar COM port selector, `Baud Rate` selector (default 9600), `Connect`, `Retry`, and `Disconnect` controls.
- There is an `Auto-retry` option (attempts to connect every 2s when enabled) and a Serial Log expander that shows recent raw serial lines.
- If the database has no rows, you can enable `Insert sample DB row when empty` or click `Insert sample now` to add a test entry that makes the UI render.

Typical serial permission error (Windows)
-------------------------------------
If you see a PermissionError like `could not open port 'COM4': PermissionError(13, 'Access is denied.')`, do the following:

1. Close Arduino IDE Serial Monitor (it locks the COM port).
2. Close VSCode Serial Monitor or any other process that may be holding the port.
3. Unplug and replug the Arduino USB cable.
4. If still failing, reboot or change the COM port in Device Manager (right-click the device → Port Settings → Advanced → COM Port Number).
5. Run the app as Administrator if you suspect a permissions issue.

Alternative serial bridge (for PHP dashboard)
-------------------------------------------
The legacy PHP dashboard expects an HTTP POST endpoint (`sense.php`). If you want to forward serial data to the PHP backend instead of using Streamlit, use a small bridge script/powershell like:

```powershell
# Example (adjust Port and BaudRate):
powershell -ExecutionPolicy Bypass -File .\serial-bridge.ps1 -Port COM3 -BaudRate 9600 -SenseUrl "http://localhost/Robotics-Trash-Bin/sense.php"
```

Troubleshooting checklist
-------------------------
- Confirm MySQL/XAMPP is running and the `robo_system` DB exists.
- Confirm the `waste_logs` table exists and is writable.
- If Streamlit shows no data, test with `scripts/read_serial.py` to verify the Arduino is sending lines like `DIST=NN.N`.
- Use the Streamlit sidebar to pick the correct COM port and baud rate, click `Connect`, and watch the Serial Log for raw lines.
- Use `Insert sample now` to populate the DB if you only need to test the UI.

Useful scripts
--------------
- `scripts/list_ports.py` — lists available serial ports.
- `scripts/read_serial.py` — attempts to read one line from a specified port and decode it.
- `scripts/test_db.py` — quick DB connectivity test.
- `scripts/check_db.py` — lists tables and counts rows in `waste_logs`.

If you want, I can also add a `serial-bridge.ps1` example implementation to this repo and a one-click script to launch Streamlit with the recommended environment.

