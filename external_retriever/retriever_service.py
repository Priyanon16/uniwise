#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
MBS UniWise AI — External Retriever for Dify
Pipeline: BM25 + Dense Vector + RRF + Cohere Rerank

Implements Dify External Knowledge API:
    POST /retrieval

Read-only: does not modify Dify Knowledge or your CSV.

Required local data:
    dify_segments.csv

Environment variables:
    EXTERNAL_KB_API_KEY
    EXTERNAL_KNOWLEDGE_ID=mbs-academic-guide-v1
    OPENAI_API_KEY
    COHERE_API_KEY

Optional:
    SEGMENTS_CSV=dify_segments.csv
    OPENAI_EMBED_MODEL=text-embedding-3-small
    COHERE_RERANK_MODEL=rerank-multilingual-v3.0
    CANDIDATE_K=6
    RRF_K=60
    BM25_K1=1.5
    BM25_B=0.75
"""

import hashlib
import json
import math
import os
import re
import secrets
import threading
import time
import unicodedata
from collections import Counter
from functools import lru_cache
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

import numpy as np
import pandas as pd
import requests
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field
from dotenv import load_dotenv

try:
    from pythainlp.tokenize import word_tokenize
    HAVE_THAI = True
except Exception:
    HAVE_THAI = False


load_dotenv()

DOC_PREFIX = "document_segment:"
OPENAI_EMBED_URL = "https://api.openai.com/v1/embeddings"
COHERE_RERANK_URL = "https://api.cohere.com/v2/rerank"

EXTERNAL_KB_API_KEY = os.getenv("EXTERNAL_KB_API_KEY", "").strip()
EXTERNAL_KNOWLEDGE_ID = os.getenv(
    "EXTERNAL_KNOWLEDGE_ID",
    "mbs-academic-guide-v1",
).strip()

OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "").strip()
COHERE_API_KEY = os.getenv("COHERE_API_KEY", "").strip()

SEGMENTS_CSV = Path(
    os.getenv("SEGMENTS_CSV", "dify_segments.csv").strip()
)

OPENAI_EMBED_MODEL = os.getenv(
    "OPENAI_EMBED_MODEL",
    "text-embedding-3-small",
).strip()

COHERE_RERANK_MODEL = os.getenv(
    "COHERE_RERANK_MODEL",
    "rerank-multilingual-v3.0",
).strip()

CANDIDATE_K = max(
    1,
    int(os.getenv("CANDIDATE_K", "6")),
)

RRF_K = max(
    1,
    int(os.getenv("RRF_K", "60")),
)

BM25_K1 = float(
    os.getenv("BM25_K1", "1.5")
)

BM25_B = float(
    os.getenv("BM25_B", "0.75")
)

AUTO_RELOAD_SECONDS = max(
    0.0,
    float(os.getenv("AUTO_RELOAD_SECONDS", "2")),
)

CACHE_DIR = Path(
    os.getenv("RETRIEVER_CACHE_DIR", ".retriever_cache")
)

CACHE_DIR.mkdir(
    parents=True,
    exist_ok=True,
)


# ============================================================
# Dify request / response models
# ============================================================

class RetrievalSetting(BaseModel):
    top_k: int = Field(default=6, ge=1)
    score_threshold: float = Field(default=0.0, ge=0.0, le=1.0)


class RetrievalRequest(BaseModel):
    # Dify validates an External Knowledge API by POSTing to /retrieval.
    # Depending on Dify version, the probe can contain an empty/blank payload.
    # Keep these fields optional/defaulted so the validation probe returns 200
    # instead of FastAPI 422.
    knowledge_id: str = ""
    query: str = ""
    retrieval_setting: RetrievalSetting = Field(
        default_factory=RetrievalSetting
    )
    metadata_condition: Optional[Dict[str, Any]] = None


class RetrievalRecord(BaseModel):
    content: str
    score: float
    title: str
    metadata: Dict[str, Any]


class RetrievalResponse(BaseModel):
    records: List[RetrievalRecord]


# ============================================================
# Text / BM25 helpers
# ============================================================

def normalize_text(value: Any) -> str:
    s = unicodedata.normalize(
        "NFKC",
        str(value or ""),
    ).lower()

    return re.sub(
        r"\s+",
        " ",
        s,
    ).strip()


def tokenize(value: Any) -> List[str]:
    s = normalize_text(value)

    if not s:
        return []

    if HAVE_THAI:
        toks = word_tokenize(
            s,
            engine="newmm",
            keep_whitespace=False,
        )

        out: List[str] = []

        for token in toks:
            token = token.strip()

            if not token:
                continue

            parts = re.findall(
                r"[ก-๙]+|[a-z]+(?:[-_.][a-z0-9]+)*|\d+",
                token,
            )

            out.extend(
                parts or [token]
            )

        return out

    base = re.findall(
        r"[a-z]+(?:[-_.][a-z0-9]+)*|\d+|[ก-๙]+",
        s,
    )

    out: List[str] = []

    for token in base:
        if (
            re.fullmatch(
                r"[ก-๙]+",
                token,
            )
            and len(token) > 3
        ):
            out.extend(
                token[i:i+3]
                for i
                in range(
                    len(token) - 2
                )
            )
        else:
            out.append(token)

    return out


class BM25:
    def __init__(
        self,
        corpus_tokens: List[List[str]],
        k1: float = 1.5,
        b: float = 0.75,
    ):
        self.k1 = k1
        self.b = b
        self.n = len(corpus_tokens)

        self.doc_len = [
            len(doc)
            for doc in corpus_tokens
        ]

        self.avgdl = (
            sum(self.doc_len) / self.n
            if self.n
            else 0.0
        )

        self.tf = [
            Counter(doc)
            for doc in corpus_tokens
        ]

        df = Counter()

        for doc in corpus_tokens:
            for term in set(doc):
                df[term] += 1

        self.idf = {
            term: math.log(
                1.0
                + (
                    self.n - freq + 0.5
                )
                / (
                    freq + 0.5
                )
            )
            for term, freq
            in df.items()
        }

    def scores(
        self,
        query_tokens: List[str],
    ) -> List[float]:

        scores = [0.0] * self.n

        if (
            not query_tokens
            or self.n == 0
        ):
            return scores

        q_terms = Counter(
            query_tokens
        )

        for i, tf in enumerate(
            self.tf
        ):
            dl = self.doc_len[i]

            norm = self.k1 * (
                1.0
                - self.b
                + self.b
                * (
                    dl / self.avgdl
                    if self.avgdl
                    else 0.0
                )
            )

            score = 0.0

            for term, qf in q_terms.items():
                f = tf.get(
                    term,
                    0,
                )

                if not f:
                    continue

                score += (
                    self.idf.get(
                        term,
                        0.0,
                    )
                    * (
                        (
                            f
                            * (
                                self.k1 + 1.0
                            )
                        )
                        / (
                            f + norm
                        )
                    )
                    * qf
                )

            scores[i] = score

        return scores


# ============================================================
# Provider calls
# ============================================================

def post_with_retry(
    url: str,
    headers: Dict[str, str],
    payload: Dict[str, Any],
    timeout: int = 120,
    max_retries: int = 6,
) -> requests.Response:

    wait = 3

    for attempt in range(
        1,
        max_retries + 1,
    ):
        r = requests.post(
            url,
            headers=headers,
            json=payload,
            timeout=timeout,
        )

        if r.ok:
            return r

        if (
            r.status_code
            in {
                429,
                500,
                502,
                503,
                504,
            }
            and attempt
            < max_retries
        ):
            time.sleep(wait)
            wait = min(
                wait * 2,
                60,
            )
            continue

        return r

    raise RuntimeError(
        "Unexpected retry loop exit."
    )


def embed_texts(
    texts: List[str],
) -> np.ndarray:

    r = post_with_retry(
        OPENAI_EMBED_URL,
        headers={
            "Authorization":
                f"Bearer {OPENAI_API_KEY}",
            "Content-Type":
                "application/json",
        },
        payload={
            "model":
                OPENAI_EMBED_MODEL,
            "input":
                texts,
            "encoding_format":
                "float",
        },
    )

    if not r.ok:
        raise RuntimeError(
            f"OpenAI embeddings HTTP "
            f"{r.status_code}: "
            f"{r.text[:1000]}"
        )

    data = sorted(
        r.json().get(
            "data"
        ) or [],
        key=lambda x:
            int(
                x.get(
                    "index",
                    0,
                )
            ),
    )

    return np.asarray(
        [
            item["embedding"]
            for item
            in data
        ],
        dtype=np.float32,
    )


def cohere_rerank(
    query: str,
    docs: List[str],
) -> List[dict]:

    if not docs:
        return []

    r = post_with_retry(
        COHERE_RERANK_URL,
        headers={
            "Authorization":
                f"Bearer {COHERE_API_KEY}",
            "Content-Type":
                "application/json",
            "X-Client-Name":
                "MBS-UniWise-AI-External-Retriever",
        },
        payload={
            "model":
                COHERE_RERANK_MODEL,
            "query":
                query,
            "documents":
                docs,
            "top_n":
                len(docs),
        },
    )

    if not r.ok:
        raise RuntimeError(
            f"Cohere rerank HTTP "
            f"{r.status_code}: "
            f"{r.text[:1000]}"
        )

    return (
        r.json().get(
            "results"
        )
        or []
    )


# ============================================================
# Corpus load + automatic reload + dense cache
# ============================================================

def corpus_signature(
    texts: List[str],
) -> str:

    raw = (
        OPENAI_EMBED_MODEL
        + "\n"
        + "\n---\n".join(
            texts
        )
    )

    return hashlib.sha256(
        raw.encode(
            "utf-8"
        )
    ).hexdigest()[:20]


def file_sha256(
    path: Path,
) -> str:
    h = hashlib.sha256()

    with path.open("rb") as f:
        for chunk in iter(
            lambda: f.read(1024 * 1024),
            b"",
        ):
            h.update(chunk)

    return h.hexdigest()


def load_segments() -> pd.DataFrame:
    if not SEGMENTS_CSV.exists():
        raise RuntimeError(
            f"Segments CSV not found: "
            f"{SEGMENTS_CSV.resolve()}"
        )

    df = pd.read_csv(
        SEGMENTS_CSV,
        encoding="utf-8-sig",
    )

    required = {
        "segment_id",
        "content",
    }

    missing = (
        required
        - set(df.columns)
    )

    if missing:
        raise RuntimeError(
            "Segments CSV missing columns: "
            + ", ".join(
                sorted(missing)
            )
        )

    df = df.fillna("")

    # segment_id must be present and unique so evidence provenance
    # remains deterministic after an automatic reload.
    segment_ids = [
        str(x).strip()
        for x in df["segment_id"].tolist()
    ]

    if any(not sid for sid in segment_ids):
        raise RuntimeError(
            "Segments CSV contains an empty segment_id."
        )

    if len(set(segment_ids)) != len(segment_ids):
        raise RuntimeError(
            "Segments CSV contains duplicate segment_id values."
        )

    return df


def get_doc_embeddings(
    texts: List[str],
) -> np.ndarray:
    sig = corpus_signature(
        texts
    )

    cache_path = (
        CACHE_DIR
        / (
            "doc_embeddings_"
            + sig
            + ".npy"
        )
    )

    if cache_path.exists():
        vectors = np.load(
            cache_path
        )

        if vectors.shape[0] == len(texts):
            return vectors

    vectors = embed_texts(
        texts
    )

    np.save(
        cache_path,
        vectors,
    )

    return vectors


# Live corpus state.  The object references are replaced only after a
# complete new index has been built successfully, so an invalid CSV or
# provider error does not corrupt the currently serving corpus.
CORPUS_LOCK = threading.RLock()
SEGMENTS_DF = pd.DataFrame()
SEGMENT_UUIDS: List[str] = []
SEGMENT_EVIDENCE_IDS: List[str] = []
SEGMENT_TEXTS: List[str] = []
SEGMENT_TITLES: List[str] = []
BM25_INDEX = BM25([], k1=BM25_K1, b=BM25_B)
DOC_EMBEDDINGS = np.empty((0, 0), dtype=np.float32)
CORPUS_FILE_HASH = ""
CORPUS_VERSION = 0
CORPUS_LOADED_AT = 0.0
LAST_RELOAD_CHECK = 0.0
LAST_RELOAD_ERROR = ""


def _build_corpus_state():
    df = load_segments()

    uuids = [
        str(x).strip()
        for x in df["segment_id"].tolist()
    ]

    evidence_ids = [
        DOC_PREFIX + sid
        for sid in uuids
    ]

    texts = [
        str(x)
        for x in df["content"].tolist()
    ]

    titles = [
        str(x).strip()
        or "ข้อมูลคู่มือการเรียน"
        for x in (
            df["document_name"].tolist()
            if "document_name" in df.columns
            else [
                "ข้อมูลคู่มือการเรียน"
            ] * len(df)
        )
    ]

    bm25 = BM25(
        [
            tokenize(text)
            for text in texts
        ],
        k1=BM25_K1,
        b=BM25_B,
    )

    embeddings = get_doc_embeddings(
        texts
    )

    if embeddings.shape[0] != len(texts):
        raise RuntimeError(
            "Dense embedding count does not match segment count."
        )

    return (
        df,
        uuids,
        evidence_ids,
        texts,
        titles,
        bm25,
        embeddings,
    )


def reload_corpus_if_changed(
    force: bool = False,
    raise_on_error: bool = False,
) -> bool:
    global SEGMENTS_DF
    global SEGMENT_UUIDS
    global SEGMENT_EVIDENCE_IDS
    global SEGMENT_TEXTS
    global SEGMENT_TITLES
    global BM25_INDEX
    global DOC_EMBEDDINGS
    global CORPUS_FILE_HASH
    global CORPUS_VERSION
    global CORPUS_LOADED_AT
    global LAST_RELOAD_CHECK
    global LAST_RELOAD_ERROR

    now = time.monotonic()

    # A tiny throttle avoids hashing the CSV on every burst request.
    if (
        not force
        and AUTO_RELOAD_SECONDS > 0
        and (now - LAST_RELOAD_CHECK) < AUTO_RELOAD_SECONDS
    ):
        return False

    with CORPUS_LOCK:
        now = time.monotonic()

        if (
            not force
            and AUTO_RELOAD_SECONDS > 0
            and (now - LAST_RELOAD_CHECK) < AUTO_RELOAD_SECONDS
        ):
            return False

        LAST_RELOAD_CHECK = now

        try:
            if not SEGMENTS_CSV.exists():
                raise RuntimeError(
                    f"Segments CSV not found: {SEGMENTS_CSV.resolve()}"
                )

            new_file_hash = file_sha256(
                SEGMENTS_CSV
            )

            if (
                not force
                and new_file_hash == CORPUS_FILE_HASH
            ):
                LAST_RELOAD_ERROR = ""
                return False

            (
                new_df,
                new_uuids,
                new_evidence_ids,
                new_texts,
                new_titles,
                new_bm25,
                new_embeddings,
            ) = _build_corpus_state()

            # Atomic-at-request-level swap: retrieval takes a snapshot of
            # these references under the same lock before ranking.
            SEGMENTS_DF = new_df
            SEGMENT_UUIDS = new_uuids
            SEGMENT_EVIDENCE_IDS = new_evidence_ids
            SEGMENT_TEXTS = new_texts
            SEGMENT_TITLES = new_titles
            BM25_INDEX = new_bm25
            DOC_EMBEDDINGS = new_embeddings
            CORPUS_FILE_HASH = new_file_hash
            CORPUS_VERSION += 1
            CORPUS_LOADED_AT = time.time()
            LAST_RELOAD_ERROR = ""

            return True

        except Exception as exc:
            LAST_RELOAD_ERROR = (
                f"{type(exc).__name__}: {exc}"
            )

            if raise_on_error or CORPUS_VERSION == 0:
                raise

            # Keep serving the previous known-good corpus.
            return False


# Initial startup must succeed. Subsequent file edits are hot-reloaded.
reload_corpus_if_changed(
    force=True,
    raise_on_error=True,
)

# ============================================================
# Ranking
# ============================================================

def unit_normalize(
    vector: np.ndarray,
) -> np.ndarray:
    norm = np.linalg.norm(
        vector
    )

    if norm == 0:
        return vector

    return vector / norm


def cosine_scores(
    query_vec: np.ndarray,
    doc_embeddings: np.ndarray,
) -> List[float]:

    q = unit_normalize(
        query_vec.astype(
            np.float32
        )
    )

    docs = doc_embeddings.astype(
        np.float32
    )

    norms = np.linalg.norm(
        docs,
        axis=1,
        keepdims=True,
    )

    norms[
        norms == 0
    ] = 1.0

    docs = docs / norms

    return (
        docs @ q
    ).astype(
        float
    ).tolist()


def rank_from_scores(
    scores: List[float],
) -> List[int]:

    return sorted(
        range(
            len(scores)
        ),
        key=lambda i: (
            scores[i],
            -i,
        ),
        reverse=True,
    )


def rrf_fuse(
    bm25_rank: List[int],
    dense_rank: List[int],
) -> List[int]:

    fused = Counter()

    for ranking in (
        bm25_rank,
        dense_rank,
    ):
        for rank, idx in enumerate(
            ranking,
            start=1,
        ):
            fused[idx] += (
                1.0
                / (
                    RRF_K + rank
                )
            )

    return sorted(
        fused.keys(),
        key=lambda i: (
            fused[i],
            -i,
        ),
        reverse=True,
    )


@lru_cache(
    maxsize=2048
)
def embed_query_cached(
    query: str,
) -> Tuple[float, ...]:

    vector = embed_texts(
        [query]
    )[0]

    return tuple(
        float(x)
        for x
        in vector
    )


def hybrid_candidates(
    query: str,
    candidate_k: int,
    bm25_index: BM25,
    doc_embeddings: np.ndarray,
) -> List[int]:

    bm25_scores = bm25_index.scores(
        tokenize(
            query
        )
    )

    bm25_rank = rank_from_scores(
        bm25_scores
    )

    query_vec = np.asarray(
        embed_query_cached(
            query
        ),
        dtype=np.float32,
    )

    dense_rank = rank_from_scores(
        cosine_scores(
            query_vec,
            doc_embeddings,
        )
    )

    fused = rrf_fuse(
        bm25_rank,
        dense_rank,
    )

    return fused[
        :candidate_k
    ]


def retrieve(
    query: str,
    top_k: int,
    score_threshold: float,
) -> List[RetrievalRecord]:

    if not query.strip():
        return []

    # Detect a changed dify_segments.csv before every retrieval burst.
    # BM25 is rebuilt automatically; Dense is synchronized from cache or
    # recomputed only when the document text signature changed.
    reload_corpus_if_changed()

    with CORPUS_LOCK:
        segment_df = SEGMENTS_DF
        segment_uuids = SEGMENT_UUIDS
        segment_evidence_ids = SEGMENT_EVIDENCE_IDS
        segment_texts = SEGMENT_TEXTS
        segment_titles = SEGMENT_TITLES
        bm25_index = BM25_INDEX
        doc_embeddings = DOC_EMBEDDINGS
        corpus_version = CORPUS_VERSION

    n_docs = len(
        segment_texts
    )

    if n_docs == 0:
        return []

    candidate_k = min(
        n_docs,
        max(
            top_k,
            CANDIDATE_K,
        ),
    )

    candidate_indices = (
        hybrid_candidates(
            query,
            candidate_k,
            bm25_index,
            doc_embeddings,
        )
    )

    rerank_results = (
        cohere_rerank(
            query,
            [
                segment_texts[i]
                for i
                in candidate_indices
            ],
        )
    )

    output: List[
        RetrievalRecord
    ] = []

    for item in rerank_results:
        local_index = int(
            item.get(
                "index",
                -1,
            )
        )

        if (
            local_index < 0
            or local_index
            >= len(
                candidate_indices
            )
        ):
            continue

        corpus_index = (
            candidate_indices[
                local_index
            ]
        )

        score = float(
            item.get(
                "relevance_score",
                0.0,
            )
        )

        if (
            score_threshold > 0
            and score
            < score_threshold
        ):
            continue

        row = segment_df.iloc[
            corpus_index
        ]

        metadata = {
            "segment_id":
                segment_uuids[
                    corpus_index
                ],
            "evidence_id":
                segment_evidence_ids[
                    corpus_index
                ],
            "document_id":
                str(
                    row.get(
                        "document_id",
                        "",
                    )
                ),
            "document_name":
                segment_titles[
                    corpus_index
                ],
            "position":
                str(
                    row.get(
                        "position",
                        "",
                    )
                ),
            "retrieval_pipeline":
                "BM25+Dense_RRF+Cohere_Rerank",
            "embedding_model":
                OPENAI_EMBED_MODEL,
            "rerank_model":
                COHERE_RERANK_MODEL,
            "corpus_version":
                corpus_version,
        }

        output.append(
            RetrievalRecord(
                content=(
                    segment_texts[
                        corpus_index
                    ]
                ),
                score=max(
                    0.0,
                    min(
                        1.0,
                        score,
                    ),
                ),
                title=(
                    segment_titles[
                        corpus_index
                    ]
                ),
                metadata=metadata,
            )
        )

        if len(output) >= top_k:
            break

    return output


# ============================================================
# API
# ============================================================

app = FastAPI(
    title="MBS UniWise AI External Retriever",
    version="1.0.0",
)


def verify_bearer(
    authorization: Optional[str],
):
    if not EXTERNAL_KB_API_KEY:
        raise HTTPException(
            status_code=500,
            detail=(
                "Server EXTERNAL_KB_API_KEY "
                "is not configured."
            ),
        )

    expected = (
        "Bearer "
        + EXTERNAL_KB_API_KEY
    )

    if (
        not authorization
        or not secrets.compare_digest(
            authorization,
            expected,
        )
    ):
        raise HTTPException(
            status_code=401,
            detail="Unauthorized",
        )


@app.get("/health")
def health():
    # Docker health checks also keep the corpus synchronized even when
    # no user retrieval request arrives.
    reload_corpus_if_changed()

    with CORPUS_LOCK:
        return {
            "status": "ok",
            "knowledge_id":
                EXTERNAL_KNOWLEDGE_ID,
            "chunks":
                len(
                    SEGMENT_TEXTS
                ),
            "pipeline":
                "BM25+Dense_RRF+Cohere_Rerank",
            "embedding_model":
                OPENAI_EMBED_MODEL,
            "rerank_model":
                COHERE_RERANK_MODEL,
            "auto_reload_seconds":
                AUTO_RELOAD_SECONDS,
            "corpus_version":
                CORPUS_VERSION,
            "corpus_hash":
                CORPUS_FILE_HASH[:12],
            "loaded_at_epoch":
                CORPUS_LOADED_AT,
            "last_reload_error":
                LAST_RELOAD_ERROR,
        }


@app.post(
    "/retrieval",
    response_model=RetrievalResponse,
)
def retrieval(
    payload: Optional[RetrievalRequest] = None,
    authorization: Optional[str] = Header(
        default=None
    ),
):
    verify_bearer(
        authorization
    )

    # --------------------------------------------------------
    # Dify External Knowledge API validation probe
    # --------------------------------------------------------
    # Current Dify appends "/retrieval" to the API Endpoint and
    # validates it with a minimal request whose knowledge_id/query
    # may be blank. Older versions may send no JSON body at all.
    # Return HTTP 200 so Dify can save the External API.
    if payload is None:
        return RetrievalResponse(
            records=[]
        )

    if (
        not str(payload.knowledge_id or "").strip()
        and not str(payload.query or "").strip()
    ):
        return RetrievalResponse(
            records=[]
        )

    # --------------------------------------------------------
    # Real retrieval request
    # --------------------------------------------------------
    if (
        payload.knowledge_id
        != EXTERNAL_KNOWLEDGE_ID
    ):
        raise HTTPException(
            status_code=404,
            detail={
                "error_code": 2001,
                "error_msg":
                    "Knowledge base not found",
            },
        )

    try:
        records = retrieve(
            query=payload.query,
            top_k=(
                payload
                .retrieval_setting
                .top_k
            ),
            score_threshold=(
                payload
                .retrieval_setting
                .score_threshold
            ),
        )

        return RetrievalResponse(
            records=records
        )

    except HTTPException:
        raise

    except Exception as exc:
        raise HTTPException(
            status_code=500,
            detail=(
                f"Retrieval failed: "
                f"{type(exc).__name__}: "
                f"{exc}"
            ),
        )
