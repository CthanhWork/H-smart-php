# H-Smart AI Service

FastAPI service for household object detection and the H-Smart RAG assistant.

## Models

Place the trained YOLO checkpoint in `models/`:

- `household_yolo26n_best.pt`

The service uses the 20-class Open Images household model trained with Ultralytics YOLO.

## Endpoints

- `GET /health`: service and detector readiness.
- `POST /api/v1/predict`: detect household objects in an uploaded image (multipart field `file`).
- `POST /v1/chat`: answer a H-Smart help question using approved Markdown mounted at `/knowledge`.
- `POST /v1/listing-description`: generate a Vietnamese product-description draft from seller-provided facts and optional YOLO detections (multipart fields `title`, `category`, `condition`, `price`, `seller_notes`, and optional `file`).

The chat and listing-description endpoints call an OpenAI-compatible model when `OPENAI_BASE_URL`, `OPENAI_API_KEY`, and `OPENAI_CHAT_MODEL` are configured. A Gemini OpenAI-compatible endpoint can be used with these settings. Without a model, chat returns matching approved excerpts and listing-description generation is unavailable. For listing descriptions, the optional image is analyzed by local YOLO; Gemini receives only seller-provided product facts and YOLO labels, not the raw image. It returns an editable draft and never saves a listing.

For first-time setup, see the [Vietnamese guide to creating a Gemini API key and configuring Docker](../docs/HUONG_DAN_LAY_API_KEY_GEMINI.md). Listing-description generation targets 5–8 sentences in two short paragraphs when sufficient facts are available, and stays shorter when facts are sparse. The prompt prioritizes preserving disclosed defects and limitations over length; seller review is still required.

## Local run

```powershell
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

## Health check

- `GET /health`

## Predict

- `POST /api/v1/predict`
- form-data key: `file`
