FROM python:3.11-slim

WORKDIR /app

# Install system dependencies including curl for healthcheck
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install python dependencies
COPY backend/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

# Copy application files
COPY backend /app/backend
COPY storage /app/storage

ENV PYTHONPATH=/app/backend

EXPOSE 8000

CMD ["python", "-m", "uvicorn", "app.main:app", "--app-dir", "backend", "--host", "0.0.0.0", "--port", "8000"]
