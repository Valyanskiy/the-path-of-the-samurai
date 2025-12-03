FROM python:3.12-slim

RUN apt-get update && apt-get install -y --no-install-recommends     postgresql-client ca-certificates     && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/csv
COPY csv_generator.py ./

CMD ["python", "csv_generator.py"]
