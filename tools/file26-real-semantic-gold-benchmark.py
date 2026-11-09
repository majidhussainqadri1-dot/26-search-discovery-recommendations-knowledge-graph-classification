#!/usr/bin/env python3
"""Read-only real embedding benchmark, 7 locale groups, 0 patient data.

Only a locally running Ollama instance at 127.0.0.1 is used. The benchmark
never sends requests to external websites, modifies WordPress, or makes medical
diagnoses. This is an isolated relevance sample, not a clinical efficacy test.
"""
import json
import math
import os
import sys
import time
import urllib.request

MODEL = os.environ.get("FILE26_BENCHMARK_MODEL", "qwen3-embedding:0.6b")
if MODEL != "qwen3-embedding:0.6b":
    raise SystemExit("REFUSED: unreviewed model identifier")
ENDPOINT = "http://127.0.0.1:11434/api/embed"

DOCUMENTS = [
    {"id": "history", "text": "History of classical homeopathy and the life of Samuel Hahnemann, founder of the Organon."},
    {"id": "heart", "text": "Human heart anatomy, cardiac chambers, valves, circulation and physiology education."},
    {"id": "pdf", "text": "Digital PDF book library for reading public domain medical books and searching book catalogues."},
    {"id": "video", "text": "Educational video lectures on a video wall and live academic broadcasting channel."},
    {"id": "doctor", "text": "Verified doctor directory: find qualified homeopathic doctors and public clinic information."},
    {"id": "research", "text": "Research methods: evidence review, experiment design, scientific studies and statistics."},
    {"id": "market", "text": "Marketplace listings: search approved products for sale and compare prices of educational supplies."},
]

# Two task intents per language/script. Unambiguous pairs permit objective top-1.
QUERIES = [
    {"lang": "ur", "text": "ساموئل ہنیمن اور ہومیوپیتھی کی تاریخ", "expected": "history"},
    {"lang": "ur", "text": "پی ڈی ایف کتب کی ڈیجیٹل لائبریری", "expected": "pdf"},
    {"lang": "ar", "text": "تشريح القلب والصمامات والدورة الدموية", "expected": "heart"},
    {"lang": "ar", "text": "محاضرات فيديو تعليمية وبث مباشر", "expected": "video"},
    {"lang": "en-US", "text": "Find verified homeopathic doctors near my clinic", "expected": "doctor"},
    {"lang": "en-US", "text": "Scientific research methodology and experimental design", "expected": "research"},
    {"lang": "ur-Latn", "text": "homeopathy kay baani Hahnemann ki tareekh", "expected": "history"},
    {"lang": "ur-Latn", "text": "public pdf kitabon ki digital library", "expected": "pdf"},
    {"lang": "fa", "text": "ساختار قلب و دریچه ها و گردش خون", "expected": "heart"},
    {"lang": "fa", "text": "فروشگاه محصولات و مقایسه قیمت", "expected": "market"},
    {"lang": "hi", "text": "प्रमाणित होम्योपैथिक डॉक्टरों की सूची", "expected": "doctor"},
    {"lang": "hi", "text": "वीडियो व्याख्यान और सीधा प्रसारण", "expected": "video"},
    {"lang": "bn", "text": "বৈজ্ঞানিক গবেষণা পদ্ধতি এবং পরীক্ষা নকশা", "expected": "research"},
    {"lang": "bn", "text": "পণ্যের বাজার এবং দামের তুলনা", "expected": "market"},
]


def embed(inputs):
    request = urllib.request.Request(
        ENDPOINT,
        data=json.dumps({"model": MODEL, "input": inputs, "truncate": True}, ensure_ascii=False).encode("utf-8"),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=200) as response:
        if response.status != 200:
            raise RuntimeError("Ollama HTTP status %s" % response.status)
        raw = response.read(5_000_001)
    if len(raw) > 5_000_000:
        raise RuntimeError("Unexpectedly large model response")
    parsed = json.loads(raw)
    vectors = parsed.get("embeddings")
    if not isinstance(vectors, list) or len(vectors) != len(inputs):
        raise RuntimeError("Embedding count does not match batch size")
    dim = 0
    normalized = []
    for vector in vectors:
        if not isinstance(vector, list):
            raise RuntimeError("Embedding must be an array")
        if dim == 0:
            dim = len(vector)
            if not 128 <= dim <= 2048:
                raise RuntimeError("Embedding dimensions outside expected model range")
        if len(vector) != dim:
            raise RuntimeError("Inconsistent dimensions")
        if not all(type(x) in (float, int) and math.isfinite(x) for x in vector):
            raise RuntimeError("Nonfinite/invalid embedding data")
        mag = math.sqrt(sum(x*x for x in vector))
        if mag <= 0:
            raise RuntimeError("Zero vector")
        normalized.append([x/mag for x in vector])
    return normalized, dim


def run():
    text = [doc["text"] for doc in DOCUMENTS] + [q["text"] for q in QUERIES]
    start = time.monotonic()
    vectors, dims = embed(text)
    sec = round(time.monotonic() - start, 3)
    doc_vec = vectors[:len(DOCUMENTS)]
    query_vec = vectors[len(DOCUMENTS):]
    records = []
    for q, v in zip(QUERIES, query_vec):
        similarity = [
            (sum(a*b for a,b in zip(v, candidate)), doc["id"])
            for candidate, doc in zip(doc_vec, DOCUMENTS)
        ]
        ordered = [x[1] for x in sorted(similarity, reverse=True)]
        records.append({
            "lang": q["lang"],
            "expected": q["expected"],
            "predicted": ordered[0],
            "top1": ordered[0] == q["expected"],
            "top3": q["expected"] in ordered[:3],
        })
    langs = sorted({r["lang"] for r in records})
    group = {
        lang: {
            "top1": sum(r["top1"] for r in records if r["lang"] == lang) / sum(r["lang"] == lang for r in records),
            "top3": sum(r["top3"] for r in records if r["lang"] == lang) / sum(r["lang"] == lang for r in records),
        } for lang in langs
    }
    report = {
        "test_type": "real_isolated_ollama_qwen3_embedding_relevance",
        "model": MODEL,
        "documents": len(DOCUMENTS),
        "queries": len(QUERIES),
        "languages": langs,
        "dimensions": dims,
        "elapsed_seconds": sec,
        "top1_accuracy": round(sum(r["top1"] for r in records)/len(records),4),
        "top3_recall": round(sum(r["top3"] for r in records)/len(records),4),
        "by_language": group,
        "details": records,
        "live_website_accepted": False,
        "clinical_validation": False,
        "model_only_runs_in_ephemeral_runner": True,
    }
    os.makedirs("benchmark-evidence", exist_ok=True)
    with open("benchmark-evidence/file26-qwen3-embedding-0.6b-seven-language.json","w",encoding="utf-8") as f:
        json.dump(report,f,ensure_ascii=False,indent=2)
    print(json.dumps(report,ensure_ascii=False,indent=2))
    # Require an objectively useful screening baseline; formal release quality
    # still requires the Founder's accepted real-world gold datasets and SLOs.
    if report["top1_accuracy"] < 0.5 or report["top3_recall"] < 0.75:
        raise SystemExit("FAIL: sampled retrieval quality below provisional screening floor")
    print("PASS: real local model returns usable cross-language retrieval on synthetic education fixtures")


if __name__ == "__main__":
    run()
