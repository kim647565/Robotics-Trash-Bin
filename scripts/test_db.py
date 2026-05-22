from sqlalchemy import create_engine
from urllib.parse import quote_plus
import pandas as pd

user='root'
password='123456789'
host='localhost'
port=3307
db='robo_system'

url = f"mysql+mysqlconnector://{user}:{quote_plus(password)}@{host}:{port}/{db}"
print('Using URL:', url)
engine = create_engine(url)
try:
    df = pd.read_sql('SELECT 1 as ok', engine)
    print(df)
except Exception as e:
    print('ERROR', e)
