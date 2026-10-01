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
# Corpus load + dense cache
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

    return df


SEGMENTS_DF = load_segments()

SEGMENT_UUIDS = [
    str(x).strip()
    for x
    in SEGMENTS_DF[
        "segment_id"
    ].tolist()
]

SEGMENT_EVIDENCE_IDS = [
    DOC_PREFIX + sid
    for sid in SEGMENT_UUIDS
]

SEGMENT_TEXTS = [
    str(x)
    for x
    in SEGMENTS_DF[
        "content"
    ].tolist()
]

SEGMENT_TITLES = [
    str(x).strip()
    or "ข้อมูลคู่มือการเรียน"
    for x
    in (
        SEGMENTS_DF[
            "document_name"
        ].tolist()
        if "document_name"
        in SEGMENTS_DF.columns
        else [
            "ข้อมูลคู่มือการเรียน"
        ]
        * len(
            SEGMENTS_DF
        )
    )
]

BM25_INDEX = BM25(
    [
        tokenize(text)
        for text
        in SEGMENT_TEXTS
    ],
    k1=BM25_K1,
    b=BM25_B,
)


def get_doc_embeddings() -> np.ndarray:
    sig = corpus_signature(
        SEGMENT_TEXTS
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
        return np.load(
            cache_path
        )

    vectors = embed_texts(
        SEGMENT_TEXTS
    )

    np.save(
        cache_path,
        vectors,
    )

    return vectors


DOC_EMBEDDINGS = get_doc_embeddings()


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
) -> List[float]:

    q = unit_normalize(
        query_vec.astype(
            np.float32
        )
    )

    docs = DOC_EMBEDDINGS.astype(
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
) -> List[int]:

    bm25_scores = BM25_INDEX.scores(
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
            query_vec
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

    n_docs = len(
        SEGMENT_TEXTS
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
        )
    )

    rerank_results = (
        cohere_rerank(
            query,
            [
                SEGMENT_TEXTS[i]
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

        row = SEGMENTS_DF.iloc[
            corpus_index
        ]

        metadata = {
            "segment_id":
                SEGMENT_UUIDS[
                    corpus_index
                ],
            "evidence_id":
                SEGMENT_EVIDENCE_IDS[
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
                SEGMENT_TITLES[
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
        }

        output.append(
            RetrievalRecord(
                content=(
                    SEGMENT_TEXTS[
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
                    SEGMENT_TITLES[
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
