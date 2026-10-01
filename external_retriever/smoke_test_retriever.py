#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import os
import requests
from dotenv import load_dotenv

load_dotenv()

key = os.getenv("EXTERNAL_KB_API_KEY", "").strip()
knowledge_id = os.getenv(
    "EXTERNAL_KNOWLEDGE_ID",
    "mbs-academic-guide-v1",
).strip()

if not key:
    raise SystemExit("Missing EXTERNAL_KB_API_KEY.")

payload = {
    "knowledge_id": knowledge_id,
    "query": "ถ้าจะลาออกจากการเป็นนิสิต ต้องเริ่มทำเรื่องยังไง",
    "retrieval_setting": {
        "top_k": 3,
        "score_threshold": 0.0
    }
}

r = requests.post(
    "http://127.0.0.1:8000/retrieval",
    headers={
        "Authorization": f"Bearer {key}",
        "Content-Type": "application/json",
    },
    json=payload,
    timeout=120,
)

print("HTTP", r.status_code)
print(
    json.dumps(
        r.json(),
        ensure_ascii=False,
        indent=2,
    )
)
