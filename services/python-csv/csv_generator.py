import os
import time
import random
from datetime import datetime
from pathlib import Path
import subprocess

def get_env(name, default):
    return os.getenv(name, default)

def generate_and_copy():
    out_dir = get_env('CSV_OUT_DIR', '/data/csv')
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')
    fn = f'telemetry_{ts}.csv'
    fullpath = Path(out_dir) / fn
    
    Path(out_dir).mkdir(parents=True, exist_ok=True)
    
    with open(fullpath, 'w') as f:
        f.write('recorded_at,voltage,temp,source_file\n')
        recorded_at = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        voltage = f"{random.uniform(3.2, 12.6):.2f}"
        temp = f"{random.uniform(-50.0, 80.0):.2f}"
        f.write(f'{recorded_at},{voltage},{temp},{fn}\n')
    
    pghost = get_env('PGHOST', 'db')
    pgport = get_env('PGPORT', '5432')
    pguser = get_env('PGUSER', 'monouser')
    pgpass = get_env('PGPASSWORD', 'monopass')
    pgdb = get_env('PGDATABASE', 'monolith')
    
    copy_cmd = f"\\copy telemetry_legacy(recorded_at, voltage, temp, source_file) FROM '{fullpath}' WITH (FORMAT csv, HEADER true)"
    
    env = os.environ.copy()
    env['PGPASSWORD'] = pgpass
    
    result = subprocess.run(
        ['psql', '-h', pghost, '-p', pgport, '-U', pguser, '-d', pgdb, '-c', copy_cmd],
        env=env,
        capture_output=True
    )
    
    if result.returncode != 0:
        raise Exception(f'psql exited with status {result.returncode}')

if __name__ == '__main__':
    period = int(get_env('GEN_PERIOD_SEC', '300'))
    while True:
        try:
            generate_and_copy()
        except Exception as e:
            print(f'Legacy error: {e}')
        time.sleep(period)
