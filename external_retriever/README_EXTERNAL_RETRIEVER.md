# MBS UniWise AI — External Retriever

Production-style retrieval service for Dify External Knowledge.

Pipeline:

```text
Query
 ├─ BM25
 └─ Dense: text-embedding-3-small
        ↓
      RRF
        ↓
   Candidate Set
        ↓
Cohere rerank-multilingual-v3.0
        ↓
     Top-K
```

## 1) Put files together

Create a folder, for example:

```text
C:\MBS-UniQA\external_retriever\
```

Put these files inside:

- `retriever_service.py`
- `requirements_external_retriever.txt`
- `.env.example`
- `run_retriever.bat`
- `smoke_test_retriever.py`
- your existing `dify_segments.csv`

## 2) Install

```bat
cd /d C:\MBS-UniQA\external_retriever
py -m pip install -r requirements_external_retriever.txt
```

## 3) Create `.env`

Copy:

```bat
copy .env.example .env
```

Generate a random API secret that Dify will use to call this service:

```bat
py -c "import secrets; print(secrets.token_urlsafe(32))"
```

Put that random value in:

```text
EXTERNAL_KB_API_KEY=...
```

Also fill:
- `OPENAI_API_KEY`
- `COHERE_API_KEY`

Do not share `.env`.

## 4) Start locally

```bat
run_retriever.bat
```

First startup may take a little longer because it creates and caches embeddings for the 6 chunks.

Open another CMD and test:

```bat
cd /d C:\MBS-UniQA\external_retriever
py smoke_test_retriever.py
```

Expected:
- HTTP 200
- `records` array
- top result should match the resignation chunk for the sample query.

Health check:

```text
http://127.0.0.1:8000/health
```

## 5) Expose to Dify Cloud for DEV test

Dify Cloud cannot call `127.0.0.1`, so expose port 8000 with a public HTTPS URL.

For a temporary DEV test with ngrok:

```bat
ngrok http 8000
```

Example public URL:

```text
https://xxxx.ngrok-free.app
```

In Dify > Knowledge > External Knowledge API:

- Name: `MBS BM25 Dense Rerank DEV`
- API Endpoint: `https://xxxx.ngrok-free.app`
  - Do NOT add `/retrieval`
- API Key: the same value as `EXTERNAL_KB_API_KEY`

Dify appends `/retrieval` automatically.

## 6) Create External Knowledge Base in Dify

Use:

```text
External Knowledge Name:
MBS Academic Guide - BM25 Dense Rerank DEV

External Knowledge ID:
mbs-academic-guide-v1

Top K:
6

Score Threshold:
Disabled / 0
```

Do not delete the current built-in Knowledge Base.

## 7) Flow safety

Do NOT replace the production DOCUMENT RETRIEVAL yet.

Use only the DEV app:

1. Duplicate the current Document Retrieval path/node.
2. Point the duplicated node to the new External Knowledge Base.
3. Keep the old retrieval path intact.
4. Test Document/Hybrid/Multi-hop regression first.
5. Only switch the main path after the DEV regression passes.

## 8) Dify External API contract

The service implements:

```text
POST /retrieval
Authorization: Bearer <EXTERNAL_KB_API_KEY>
```

Request:

```json
{
  "knowledge_id": "mbs-academic-guide-v1",
  "query": "คำถาม",
  "retrieval_setting": {
    "top_k": 6,
    "score_threshold": 0.0
  }
}
```

Response:

```json
{
  "records": [
    {
      "content": "...",
      "score": 0.98,
      "title": "ข้อมูลคู่มือการเรียน.md",
      "metadata": {
        "segment_id": "document_segment:..."
      }
    }
  ]
}
```

The returned `score` is Cohere `relevance_score`, which is already in 0–1 form and is used by Dify for ranking/threshold behavior.

## 9) After local DEV works

Deploy the same service to ReadyIDC/VPS behind HTTPS, then register that stable HTTPS base URL in Dify.

Do not use ngrok for the final production/research deployment.


## Important: compatibility with MBS UniWise AI Freeze v3-DEV

The current Flow's `DOCUMENT TOPIC SEGMENT SELECTOR` reads:

```text
metadata.segment_id
```

and the downstream `DOCUMENT EVIDENCE ID EXTRACTOR` adds:

```text
document_segment:
```

itself.

Therefore this external retriever intentionally returns:

```json
"metadata": {
  "segment_id": "be6cafd2-....",
  "evidence_id": "document_segment:be6cafd2-...."
}
```

`segment_id` must be the bare UUID. Do not change it to a prefixed
`document_segment:...` value, otherwise the current Flow would create
`document_segment:document_segment:...`.


## Dify "Failed to save/update External API"

This package accepts Dify's validation probe.

Before saving in Dify, test locally:

```bat
py test_dify_validation.py
```

Expected:

```text
HTTP 200
{"records":[]}
```

To test through ngrok, set the public base URL in the same CMD:

```bat
set PUBLIC_RETRIEVER_URL=https://YOUR-NGROK-DOMAIN
py test_dify_validation.py
```

Expected again:

```text
HTTP 200
{"records":[]}
```

Keep **both** processes running:
1. `run_retriever.bat` (FastAPI/Uvicorn on port 8000)
2. `ngrok http 8000`

In Dify's **API Endpoint**, enter the ngrok **base URL only**.
Do not add `/retrieval`; Dify appends it itself.
