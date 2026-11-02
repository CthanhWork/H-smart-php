from typing import Optional

from pydantic import BaseModel, Field

from app.presentation.api.schemas.predict import PredictResponse


class ListingDescriptionRequest(BaseModel):
    title: str = Field(min_length=3, max_length=200)
    category: str = Field(min_length=1, max_length=120)
    condition: str = Field(min_length=1, max_length=32)
    price: str = Field(default="", max_length=32)
    seller_notes: str = Field(default="", max_length=2500)


class ListingDescriptionResponse(BaseModel):
    description: str
    vision: Optional[PredictResponse]
