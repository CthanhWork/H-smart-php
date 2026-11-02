from typing import Optional

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile

from app.application.use_cases.generate_listing_description import generate_listing_description
from app.domain.entities.detection import Detection
from app.presentation.api.schemas.listing_description import ListingDescriptionResponse
from app.presentation.api.schemas.predict import DetectionResponse, PredictResponse
from app.presentation.dependencies import get_predict_image_use_case
from app.application.use_cases.predict_image import PredictImageUseCase

router = APIRouter(prefix="/v1", tags=["listing-assistant"])


@router.post("/listing-description", response_model=ListingDescriptionResponse)
async def create_listing_description(
    title: str = Form(..., min_length=3, max_length=200),
    category: str = Form(..., min_length=1, max_length=120),
    condition: str = Form(..., min_length=1, max_length=32),
    price: str = Form("", max_length=32),
    seller_notes: str = Form("", max_length=2500),
    file: Optional[UploadFile] = File(None),
    use_case: PredictImageUseCase = Depends(get_predict_image_use_case),
) -> ListingDescriptionResponse:
    detections: list[Detection] = []
    vision = None
    if file is not None:
        if not file.content_type or file.content_type not in {"image/jpeg", "image/png", "image/webp"}:
            raise HTTPException(status_code=400, detail="Unsupported image type")
        image_bytes = await file.read(10 * 1024 * 1024 + 1)
        if not image_bytes or len(image_bytes) > 10 * 1024 * 1024:
            raise HTTPException(status_code=400, detail="Invalid image size")
        try:
            detections = use_case.execute(image_bytes)
        except ValueError as exc:
            raise HTTPException(status_code=400, detail="Invalid image") from exc
        except Exception as exc:
            raise HTTPException(status_code=503, detail={"code": "VISION_UNAVAILABLE"}) from exc

        if detections:
            best = max(detections, key=lambda detection: detection.score)
            vision = PredictResponse(
                label=best.label,
                confidence=best.score,
                translated_label=best.translated_label,
                num_detections=len(detections),
                detections=[
                    DetectionResponse(
                        label=item.label,
                        class_id=item.class_id,
                        score=item.score,
                        bbox=item.bbox,
                        translated_label=item.translated_label,
                    )
                    for item in detections
                ],
            )
        else:
            vision = PredictResponse(
                label="unknown",
                confidence=0.0,
                translated_label="Không xác định",
                num_detections=0,
                detections=[],
            )

    description = await generate_listing_description(
        title=title.strip(),
        category=category.strip(),
        condition=condition.strip(),
        seller_notes=seller_notes.strip(),
        detections=detections,
    )
    return ListingDescriptionResponse(description=description, vision=vision)
