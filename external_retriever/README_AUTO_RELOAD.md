# Automatic Document Reload

This build automatically watches `data/dify_segments.csv`.

## What happens after the CSV changes

1. `/health` or the next `/retrieval` request checks the CSV SHA-256 hash.
2. If the hash did not change, nothing is rebuilt.
3. If the hash changed, the new CSV is validated first.
4. BM25 is rebuilt in memory from the latest `content` values.
5. Dense document embeddings are synchronized:
   - if document text is unchanged, the existing embedding cache is reused;
   - if document text changed, a new embedding cache is created with OpenAI.
6. Only after all steps succeed does the service swap to the new corpus.
7. If the new CSV is invalid or embedding refresh fails, the previous known-good corpus remains active.

Default check interval: `AUTO_RELOAD_SECONDS=2`.
Docker health checks call `/health` every 30 seconds, so the service also detects changes even when no user is asking a question.

## Edit workflow

Edit or replace only:

`data/dify_segments.csv`

No retriever restart is required after a normal document-data edit.

Check the active version:

```bash
docker exec mbs-retriever python -c "import requests; print(requests.get('http://127.0.0.1:8000/health',timeout=10).json())"
```

Relevant health fields:

- `chunks`
- `corpus_version`
- `corpus_hash`
- `last_reload_error`
- `auto_reload_seconds`
