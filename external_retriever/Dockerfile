FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

COPY requirements_external_retriever.txt /app/
RUN pip install --no-cache-dir -r /app/requirements_external_retriever.txt

COPY retriever_service.py /app/

RUN mkdir -p /app/.retriever_cache /app/data

EXPOSE 8000

CMD ["uvicorn", "retriever_service:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
