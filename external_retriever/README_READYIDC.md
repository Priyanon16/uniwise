# MBS External Retriever on ReadyIDC (same VPS as Dify)

Recommended staging architecture:

Dify containers -> internal Docker network -> `mbs-retriever:8000`

The retriever is NOT published on a public port. Dify talks to it over the shared Docker network.

## Required files before deployment

Put these in the same directory on ReadyIDC:

- `retriever_service.py`
- `requirements_external_retriever.txt`
- `Dockerfile`
- `docker-compose.readyidc.yml`
- `.env` (copied from `.env.example` and filled with real secrets)
- `data/dify_segments.csv` (your real segment export used by Document RAG)

Do not upload `.env` to GitHub or share it.

## 1. After Dify is running, find its Docker network

```bash
cd /var/www/html/uniwise/external_retriever
./check_dify_network.sh
```

You can also inspect a Dify API/worker container directly:

```bash
docker ps --format 'table {{.Names}}\t{{.Networks}}'
```

Set the exact network name in `.env`:

```env
DIFY_NETWORK=<actual-dify-network-name>
```

## 2. Build and start

```bash
cd /var/www/html/uniwise/external_retriever
docker compose -f docker-compose.readyidc.yml up -d --build
```

## 3. Check status

```bash
docker ps --filter name=mbs-retriever
docker logs --tail=100 mbs-retriever
```

Health from inside the container:

```bash
docker exec mbs-retriever python -c "import requests; print(requests.get('http://127.0.0.1:8000/health',timeout=5).json())"
```

Expected fields include:

- `status: ok`
- `chunks: 6`
- `pipeline: BM25+Dense_RRF+Cohere_Rerank`

## 4. Test connectivity from Dify's Docker network

First identify a Dify API/worker container name:

```bash
docker ps --format 'table {{.Names}}\t{{.Networks}}'
```

Then test DNS/connectivity from that container if it has Python/curl available, or use a disposable curl container on the same network:

```bash
docker run --rm --network "$DIFY_NETWORK" curlimages/curl:latest http://mbs-retriever:8000/health
```

## 5. Dify External Knowledge API setting

Use this as the API Endpoint (base URL only):

```text
http://mbs-retriever:8000
```

Do NOT add `/retrieval` because Dify appends it.

API Key must be exactly the value of `EXTERNAL_KB_API_KEY` in the retriever `.env`, without `Bearer` and without the variable name.

External Knowledge ID remains:

```text
mbs-academic-guide-v1
```

Top K: 6
Score Threshold: off

## 6. Updating Document RAG data automatically

Edit or replace only:

```text
data/dify_segments.csv
```

No container restart is required. The retriever checks the CSV hash automatically. When content changes it validates the new CSV, rebuilds BM25, synchronizes Dense embeddings, and only then switches to the new corpus. If the update fails, the previous known-good corpus stays active.

Check the active corpus:

```bash
docker exec mbs-retriever python -c "import requests; print(requests.get('http://127.0.0.1:8000/health',timeout=10).json())"
```

Look at `corpus_version`, `corpus_hash`, `chunks`, and `last_reload_error`.

If you change `retriever_service.py` itself, rebuild the service:

```bash
cd /var/www/html/uniwise/external_retriever
docker compose -f docker-compose.readyidc.yml up -d --build mbs-retriever
```

## 7. Logs

```bash
docker logs -f mbs-retriever
```

## 8. Stop / start

```bash
docker compose -f docker-compose.readyidc.yml stop
docker compose -f docker-compose.readyidc.yml start
```

## Important

- Keep one retriever instance during staging (`--workers 1`).
- OpenAI and Cohere still have API costs/rate limits.
- The `.retriever_cache` Docker volume persists across container restarts/rebuilds.
- No ngrok is needed once Dify and retriever share the same Docker network.

## Automatic reload settings

Default:

```env
AUTO_RELOAD_SECONDS=2
SEGMENTS_CSV=/app/data/dify_segments.csv
```

The Docker Compose file mounts the whole `data/` directory instead of a single file so normal edits and file replacements (including many Git update workflows) are visible inside the container.
