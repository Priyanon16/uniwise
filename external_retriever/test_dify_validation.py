#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import requests
from dotenv import load_dotenv

load_dotenv()

base = os.getenv(
    "PUBLIC_RETRIEVER_URL",
    "http://127.0.0.1:8000",
).rstrip("/")

key = os.getenv("EXTERNAL_KB_API_KEY", "").strip()

if not key:
    raise SystemExit("Missing EXTERNAL_KB_API_KEY.")

r = requests.post(
    base + "/retrieval",
    headers={
        "Authorization": f"Bearer {key}",
        "Content-Type": "application/json",
    },
    json={
        "knowledge_id": "",
        "query": "",
        "retrieval_setting": {
            "top_k": 1,
            "score_threshold": 0.0,
        },
    },
    timeout=30,
)

print("HTTP", r.status_code)
print(r.text)
