import json
import os
import re
import unicodedata
from typing import Any

def _fallback(message: str, context: dict[str, Any]) -> dict[str, Any]:
    normalized = unicodedata.normalize("NFD", message.lower())
    normalized = "".join(char for char in normalized if unicodedata.category(char) != "Mn").replace("đ", "d")
    ordinal = re.search(r"(?:cai|mon|san pham)?\s*(?:thu\s*)?(\d+|nhat|nhi|hai|ba)\b", normalized)
    index = None
    if re.search(r"\b(cai|mon|san pham)\b", normalized) and ordinal:
        index = {"nhat": 1, "nhi": 2, "hai": 2, "ba": 3}.get(ordinal.group(1))
        if ordinal.group(1).isdigit():
            index = int(ordinal.group(1))

    filters = dict(context) if isinstance(context, dict) else {}
    price_range = re.search(r"\btu\s*(\d[\d.,]*)\s*(?:den|toi)\s*(\d[\d.,]*)\s*(trieu|tr|nghin|ngan|k)?\b", normalized)
    if price_range:
        multiplier = 1_000_000 if price_range.group(3) in {"trieu", "tr"} else (1_000 if price_range.group(3) in {"nghin", "ngan", "k"} else 1)
        filters["min_price"] = int(re.sub(r"\D", "", price_range.group(1)) or "0") * multiplier
        filters["max_price"] = int(re.sub(r"\D", "", price_range.group(2)) or "0") * multiplier
    for match in re.finditer(r"(\d[\d.,]*)\s*(trieu|tr|nghin|ngan|k)\b", normalized):
        amount = int(re.sub(r"\D", "", match.group(1)) or "0")
        value = amount * (1_000_000 if match.group(2) in {"trieu", "tr"} else 1_000)
        if re.search(r"(duoi|toi da|nho hon|khong qua)\s*$", normalized[:match.start()]):
            filters["max_price"] = value
        elif re.search(r"(tren|tu|toi thieu|bat dau tu)\s*$", normalized[:match.start()]):
            filters["min_price"] = value
        else:
            filters["max_price"] = value

    if "re hon" in normalized or "gia thap" in normalized:
        filters["sort"] = "price_asc"
    if "dat hon" in normalized or "gia cao" in normalized:
        filters["sort"] = "price_desc"
    if "con tot" in normalized:
        filters["condition"] = "good"
    elif "nhu moi" in normalized:
        filters["condition"] = "like_new"
    elif "moi" in normalized and "nhu moi" not in normalized:
        filters["condition"] = "new"

    keyword = re.sub(r"\b(tim|kiem|cho|minh|toi|xem|mua|san pham|loai|hang|duoi|tren|tu|toi da|gia|re hon|dat hon|con tot|nhu moi|moi|nhe|nao|voi|co|cai|thu|mot|hai|ba)\b", " ", normalized)
    keyword = re.sub(r"\d[\d.,]*\s*(?:trieu|tr|nghin|ngan|k)\b", " ", keyword)
    keyword = " ".join(keyword.split())[:120]
    search_signal = any(term in normalized for term in ("tim", "kiem", "san pham", "co cai", "loai", "re hon", "con tot", "nhu moi", "duoi", "tren"))
    intent = "select_product" if index else ("refine_search" if filters.get("sort") != context.get("sort") or filters.get("condition") != context.get("condition") else "search_products")
    if not search_signal and index is None and not any(key in filters for key in ("min_price", "max_price", "condition")):
        intent = "general"
    return {"intent": intent, "keyword": keyword, "reference_index": index, **filters}


async def parse_product_search(message: str, context_filters: dict[str, Any]) -> dict[str, Any]:
    api_key = os.getenv("OPENAI_API_KEY", "").strip()
    base_url = os.getenv("OPENAI_BASE_URL", "").strip().rstrip("/")
    model = os.getenv("OPENAI_CHAT_MODEL", "").strip()
    if not (api_key and base_url and model):
        return _fallback(message, context_filters)
    import httpx

    system = (
        "Phân tích yêu cầu tìm đồ cũ tiếng Việt thành JSON duy nhất với các khóa intent, keyword, min_price, "
        "max_price, condition, sort, reference_index. intent là search_products, select_product hoặc refine_search. "
        "condition chỉ là new, like_new, good, fair hoặc null. sort chỉ là newest, price_asc hoặc price_desc. "
        "Giá là số VND nguyên. Không tạo dữ liệu sản phẩm, URL, SQL, quyền truy cập hoặc nội dung ngoài JSON. "
        "Kế thừa bộ lọc cũ khi người dùng nói tiếp như rẻ hơn hoặc còn tốt. Không suy đoán bộ lọc không được nói tới."
    )
    try:
        async with httpx.AsyncClient(timeout=8) as client:
            response = await client.post(
                f"{base_url}/chat/completions",
                headers={"Authorization": f"Bearer {api_key}"},
                json={"model": model, "temperature": 0, "max_tokens": 220, "response_format": {"type": "json_object"}, "messages": [
                    {"role": "system", "content": system},
                    {"role": "user", "content": json.dumps({"message": message, "context_filters": context_filters}, ensure_ascii=False)},
                ]},
            )
            response.raise_for_status()
            parsed = json.loads(response.json()["choices"][0]["message"]["content"])
            if not isinstance(parsed, dict):
                raise ValueError("invalid intent")
            result = _fallback(message, context_filters)
            for key in ("intent", "keyword", "min_price", "max_price", "condition", "sort", "reference_index"):
                if key in parsed:
                    result[key] = parsed[key]
            return result
    except (httpx.HTTPError, KeyError, IndexError, TypeError, ValueError, json.JSONDecodeError):
        return _fallback(message, context_filters)
