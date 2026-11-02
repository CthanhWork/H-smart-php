from pydantic import BaseModel, Field


class ChatRequest(BaseModel):
    schema_version: int = Field(ge=1, le=1)
    request_id: str = Field(min_length=1, max_length=80)
    question: str = Field(min_length=2, max_length=1200)


class CitationResponse(BaseModel):
    source_id: str
    title: str
    section: str


class ChatResponse(BaseModel):
    schema_version: int
    request_id: str
    answer: str
    citations: list[CitationResponse]
