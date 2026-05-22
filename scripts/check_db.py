from sqlalchemy import create_engine, inspect, text
from urllib.parse import quote_plus

user='root'
password='123456789'
host='localhost'
port=3307
db='robo_system'

url = f"mysql+mysqlconnector://{user}:{quote_plus(password)}@{host}:{port}/{db}"
engine = create_engine(url)
inspector = inspect(engine)
print('Tables:', inspector.get_table_names())

if 'waste_logs' in inspector.get_table_names():
    with engine.connect() as conn:
        res = conn.execute(text('SELECT COUNT(*) as cnt FROM waste_logs'))
        row = res.fetchone()
        print('waste_logs rows:', row[0])
else:
    print('waste_logs table not found')
