from fastapi import APIRouter

from app.application.use_cases.answer_question import answer_question
from app.presentation.api.schemas.assistant import ChatRequest, ChatResponse, CitationResponse

router = APIRouter(prefix="/v1", tags=["assistant"])


@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest) -> ChatResponse:
    answer, citations = await answer_question(request.question)
    return ChatResponse(
        schema_version=1,
        request_id=request.request_id,
        answer=answer,
        citations=[CitationResponse(**citation) for citation in citations],
    )
