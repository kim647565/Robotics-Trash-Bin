import streamlit as st
import serial
import serial.tools.list_ports as list_ports
from sqlalchemy import create_engine, text
from urllib.parse import quote_plus
import pandas as pd
import time
from datetime import datetime

# =====================================================
# PAGE CONFIGURATION
# =====================================================

st.set_page_config(
    page_title="IoT Smart Waste Bin Monitoring",
    page_icon="🗑️",
    layout="wide"
)

# =====================================================
# BIN CONFIGURATION
# =====================================================

BIN_HEIGHT_CM = 20

# =====================================================
# MYSQL DATABASE CONFIGURATION
# =====================================================

DB_CONFIG = {
    "host": "localhost",
    "port": 3307,
    "user": "root",
    "password": "123456789",
    "database": "robo_system"
}

# =====================================================
# SERIAL CONNECTION
# =====================================================

@st.cache_resource
def get_serial_connection():
    try:
        ser = serial.Serial(
            port='COM4',
            baudrate=9600,
            timeout=1
        )
        return {"conn": ser, "error": None}
    except Exception as e:
        return {"conn": None, "error": str(e)}

# =====================================================
# MYSQL CONNECTION
# =====================================================

@st.cache_resource
def get_database_engine():
    try:
        user = DB_CONFIG.get("user")
        password = DB_CONFIG.get("password", "123456789")
        host = DB_CONFIG.get("host", "localhost")
        port = DB_CONFIG.get("port", 3307)
        database = DB_CONFIG.get("database")

        pw_escaped = quote_plus(password)
        url = f"mysql+mysqlconnector://{user}:{pw_escaped}@{host}:{port}/{database}"

        engine = create_engine(url)
        return engine
    except Exception as e:
        # store DB error for UI display
        try:
            st.session_state['db_error'] = str(e)
        except Exception:
            pass
        st.error(f"MySQL Engine Error: {e}")
        return None

# =====================================================
# CALCULATE FILL PERCENTAGE
# =====================================================

def calculate_fill_percentage(distance):
    fill = ((BIN_HEIGHT_CM - distance) / BIN_HEIGHT_CM) * 100

    # Clamp values between 0 and 100
    fill = max(0, min(100, fill))

    return round(fill, 2)

# =====================================================
# BIN STATUS
# =====================================================

def get_bin_status(fill_percentage):

    if fill_percentage < 50:
        return "AVAILABLE"

    elif fill_percentage < 85:
        return "NEARLY FULL"

    else:
        return "COLLECTION REQUIRED"

# =====================================================
# INSERT DATA INTO MYSQL
# =====================================================

def insert_data(distance, fill_percentage, status):
    engine = get_database_engine()

    if engine is None:
        return

    try:
        query = text(
            """
            INSERT INTO waste_logs (sensor_distance_cm, fill_percentage, bin_status)
            VALUES (:distance, :fill_percentage, :status)
            """
        )

        with engine.connect() as conn:
            trans = conn.begin()
            conn.execute(query, {"distance": distance, "fill_percentage": fill_percentage, "status": status})
            trans.commit()

    except Exception as e:
        st.error(f"Database Insert Error: {e}")

# =====================================================
# FETCH DATABASE RECORDS
# =====================================================

def fetch_data():
    engine = get_database_engine()

    if engine is None:
        return pd.DataFrame()

    try:
        query = "SELECT * FROM waste_logs ORDER BY timestamp ASC"
        df = pd.read_sql(query, engine)
        return df

    except Exception as e:
        st.error(f"Database Fetch Error: {e}")
        return pd.DataFrame()

# =====================================================
# READ SERIAL DATA
# =====================================================

def read_sensor_data():

    # Prefer session-managed serial connection (set by sidebar Connect)
    ser = None
    if 'serial_conn' in st.session_state and st.session_state['serial_conn']:
        ser = st.session_state['serial_conn']

    # Ensure serial buffer exists
    if 'serial_buffer' not in st.session_state:
        st.session_state['serial_buffer'] = []
    if 'serial_buffer_max' not in st.session_state:
        st.session_state['serial_buffer_max'] = 50

    if ser is None:
        return None

    try:
        if ser.in_waiting > 0:

            raw = ser.readline()
            try:
                line = raw.decode('utf-8').strip()
            except Exception:
                line = str(raw)

            # append to rolling buffer
            buf = st.session_state.get('serial_buffer', [])
            buf.append(line)
            maxlen = st.session_state.get('serial_buffer_max', 50)
            if len(buf) > maxlen:
                buf = buf[-maxlen:]
            st.session_state['serial_buffer'] = buf

            if line.startswith("DIST="):

                value = line.replace("DIST=", "")
                try:
                    distance = float(value)
                    return distance
                except Exception:
                    return None

    except Exception as e:
        st.error(f"Serial Read Error: {e}")

    return None

# =====================================================
# HEADER SECTION
# =====================================================

st.title("🗑️ IoT Smart Waste Bin Level Monitoring Station")

st.caption(
    "Real-Time Urban Sanitation & Environmental Analytics Dashboard"
)

# =====================================================
# SIDEBAR
# =====================================================

st.sidebar.title("System Connection Status")

# --- Serial COM selector and connection controls ---
def available_ports():
    try:
        return [p.device for p in list_ports.comports()]
    except Exception:
        return []

ports = available_ports()
selected_port = st.sidebar.selectbox("COM Port", options=ports if ports else ["<No ports found>"])

if 'serial_port' not in st.session_state:
    st.session_state['serial_port'] = selected_port

# Baud rate selector
baud_options = [300, 1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200]
selected_baud = st.sidebar.selectbox("Baud Rate", options=baud_options, index=baud_options.index(9600))
if 'baudrate' not in st.session_state:
    st.session_state['baudrate'] = selected_baud
else:
    # keep previously saved baud if user didn't change selection
    selected_baud = st.session_state['baudrate']

# Auto-retry checkbox and last-retry timestamp
auto_retry = st.sidebar.checkbox("Auto-retry connect", value=False)
if 'last_retry' not in st.session_state:
    st.session_state['last_retry'] = 0

def try_open_port(port, baud):
    try:
        ser = serial.Serial(port=port, baudrate=baud, timeout=1)
        return ser, None
    except Exception as e:
        return None, str(e)

if st.sidebar.button("Connect"):
    st.session_state['serial_port'] = selected_port
    st.session_state['baudrate'] = selected_baud
    ser, err = try_open_port(st.session_state['serial_port'], st.session_state['baudrate'])
    st.session_state['serial_conn'] = ser
    st.session_state['serial_error'] = err

# Auto-retry logic: attempt every 2 seconds when enabled
if auto_retry and (not st.session_state.get('serial_conn')):
    now = time.time()
    if now - st.session_state['last_retry'] > 2:
        port_to_try = selected_port if selected_port != "<No ports found>" else None
        baud_to_try = st.session_state.get('baudrate', selected_baud)
        ser, err = try_open_port(port_to_try, baud_to_try)
        st.session_state['serial_conn'] = ser
        st.session_state['serial_error'] = err
        st.session_state['last_retry'] = now

# Manual retry button
if st.sidebar.button("Retry"): 
    port_to_try = st.session_state.get('serial_port', selected_port)
    baud_to_try = st.session_state.get('baudrate', selected_baud)
    ser, err = try_open_port(port_to_try, baud_to_try)
    st.session_state['serial_conn'] = ser
    st.session_state['serial_error'] = err

# Disconnect button
if st.sidebar.button("Disconnect"):
    ser = st.session_state.get('serial_conn')
    if ser:
        try:
            ser.close()
        except Exception:
            pass
    st.session_state['serial_conn'] = None
    st.session_state['serial_error'] = 'Disconnected by user'

# Show connection status / friendly instructions
if st.session_state.get('serial_conn'):
    st.sidebar.success(f"🟢 Arduino Connected on {st.session_state.get('serial_port')}")
else:
    serr = st.session_state.get('serial_error')
    st.sidebar.error(f"🔴 Arduino Connection Error: {serr}")
    with st.sidebar.expander("Troubleshooting steps"):
        st.write("1. Close Arduino Serial Monitor, VSCode Serial Monitor, or other programs using the port.")
        st.write("2. Unplug and replug the Arduino USB cable.")
        st.write("3. If still failing, try running Streamlit as Administrator or change the COM port in Device Manager.")
        st.write("4. Use the 'Retry' button or enable 'Auto-retry connect'.")

# Serial log controls
show_log = st.sidebar.checkbox("Show serial log", value=True)
if 'serial_buffer' not in st.session_state:
    st.session_state['serial_buffer'] = []
if 'serial_buffer_max' not in st.session_state:
    st.session_state['serial_buffer_max'] = 50
with st.sidebar.expander("Serial Log"):
    if show_log:
        buf = st.session_state.get('serial_buffer', [])
        if buf:
            for ln in reversed(buf[-20:]):
                st.write(ln)
        else:
            st.write("(no serial data yet)")

# --- Database status ---
db_engine = get_database_engine()
if db_engine:
    st.sidebar.success("🟢 MySQL Connected")
else:
    db_err = st.session_state.get('db_error') if 'db_error' in st.session_state else None
    st.sidebar.error(f"🔴 MySQL Connection Error: {db_err}")

# Sample row insertion controls
st.sidebar.markdown("---")
insert_when_empty = st.sidebar.checkbox("Insert sample DB row when empty", value=False)
if st.sidebar.button("Insert sample now"):
    # immediate insert of a sample row
    sample_distance = 5.0
    sample_fill = calculate_fill_percentage(sample_distance)
    sample_status = get_bin_status(sample_fill)
    insert_data(sample_distance, sample_fill, sample_status)
    st.sidebar.success("Inserted sample row into DB")
    st.session_state['sample_inserted'] = True

# =====================================================
# SENSOR DATA INGESTION
# =====================================================

distance = read_sensor_data()

if distance is not None:

    fill_percentage = calculate_fill_percentage(distance)

    status = get_bin_status(fill_percentage)

    insert_data(distance, fill_percentage, status)

# =====================================================
# LOAD DATA
# =====================================================

df = fetch_data()

# =====================================================
# NO DATA MESSAGE
# =====================================================

# If no data, optionally insert a sample row (once) or show waiting message
if df.empty:
    # auto-insert sample row if user enabled
    if insert_when_empty and not st.session_state.get('sample_inserted'):
        sample_distance = 5.0
        sample_fill = calculate_fill_percentage(sample_distance)
        sample_status = get_bin_status(sample_fill)
        insert_data(sample_distance, sample_fill, sample_status)
        st.session_state['sample_inserted'] = True
        # re-fetch data
        df = fetch_data()

    if df.empty:
        st.warning("Waiting for live sensor data from Arduino...")
        st.stop()

# =====================================================
# CURRENT VALUES
# =====================================================

latest = df.iloc[-1]

current_fill = latest['fill_percentage']
max_fill = df['fill_percentage'].max()
avg_fill = round(df['fill_percentage'].mean(), 2)

current_status = latest['bin_status']

# =====================================================
# LIVE STATUS ALERT BANNERS
# =====================================================

if current_fill < 50:
    st.success("🟢 AVAILABLE (Optimal Capacity)")

elif current_fill < 85:
    st.warning("🟡 NEARLY FULL (Schedule Collection Soon)")

else:
    st.error("🔴 COLLECTION REQUIRED (Urgent Waste Overflow Risk!)")

# =====================================================
# KPI SECTION
# =====================================================

col1, col2, col3 = st.columns(3)

with col1:
    st.metric(
        "Current Fill Level",
        f"{current_fill:.2f}%"
    )

with col2:
    st.metric(
        "Maximum Fill Recorded",
        f"{max_fill:.2f}%"
    )

with col3:
    st.metric(
        "Average Bin Fullness",
        f"{avg_fill:.2f}%"
    )

# =====================================================
# LIVE TREND GRAPH
# =====================================================

st.subheader("📈 Trash Accumulation Over Time")

chart_data = df[['timestamp', 'fill_percentage']].copy()

chart_data['timestamp'] = pd.to_datetime(chart_data['timestamp'])

chart_data = chart_data.set_index('timestamp')

st.line_chart(
    chart_data,
    y='fill_percentage',
    height=400
)

# =====================================================
# DATABASE TABLE
# =====================================================

st.subheader("🗃️ Live Waste Bin Database Logs")

display_df = df.rename(columns={
    "id": "Log ID",
    "timestamp": "Timestamp",
    "sensor_distance_cm": "Sensor Distance (cm)",
    "fill_percentage": "Fill Percentage (%)",
    "bin_status": "Bin Status"
})

st.dataframe(
    display_df,
    width='stretch'
)

# =====================================================
# AUTO REFRESH
# =====================================================

time.sleep(2)
st.rerun()