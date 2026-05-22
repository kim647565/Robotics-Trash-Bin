import serial.tools.list_ports as lp
ports = [p.device for p in lp.comports()]
for p in ports:
    print(p)
