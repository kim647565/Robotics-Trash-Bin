import sys
import serial

port = sys.argv[1] if len(sys.argv) > 1 else 'COM4'
baud = 9600
try:
    with serial.Serial(port, baud, timeout=3) as s:
        line = s.readline()
        print('RAW:', line)
        try:
            print('DECODED:', line.decode('utf-8').strip())
        except Exception as e:
            print('DECODE ERROR:', e)
except Exception as e:
    print('OPEN ERROR:', e)
