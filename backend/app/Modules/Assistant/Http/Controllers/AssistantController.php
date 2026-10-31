<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Models\AssistantConversation;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Services\AiServiceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantController
{
    public function generateListingDescription(Request $request, AiServiceClient $ai): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', 'string', 'min:1', 'max:120'],
            'condition' => ['required', 'in:new,like_new,good,fair,poor'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'seller_notes' => ['nullable', 'string', 'max:2500'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $result = $ai->generateListingDescription($data, $data['image'] ?? null);
        } catch (Throwable $exception) {
            Log::warning('Listing description generation failed', ['user_id' => (int) $request->user()->id, 'exception' => $exception::class]);
            return response()->json(['status' => 'error', 'message' => 'Không tạo được mô tả lúc này. Vui lòng thử lại sau.', 'data' => null, 'error' => ['code' => 'AI_UNAVAILABLE']], 503);
        }

        return response()->json(['status' => 'success', 'message' => 'Đã tạo bản nháp mô tả.', 'data' => $result]);
    }

    public function analyzeImage(Request $request, AiServiceClient $ai): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $result = $ai->detectImage($data['image']);
        } catch (Throwable $exception) {
            Log::warning('Assistant vision request failed', ['user_id' => (int) $request->user()->id, 'exception' => $exception::class]);
            return response()->json(['status' => 'error', 'message' => 'Không phân tích được ảnh lúc này. Vui lòng thử lại sau.', 'data' => null, 'error' => ['code' => 'AI_UNAVAILABLE']], 503);
        }

        return response()->json(['status' => 'success', 'message' => 'Đã nhận diện ảnh.', 'data' => $result]);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = AssistantConversation::query()->where('user_id', $request->user()->id)
            ->withCount('messages')->latest('updated_at')->limit(50)->get()
            ->map(fn (AssistantConversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at->toIso8601String(),
                'messages_count' => $conversation->messages_count,
            ]);

        return response()->json(['status' => 'success', 'message' => 'Các cuộc trò chuyện.', 'data' => $conversations]);
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->ownedConversation($request, $conversationId);
        return response()->json(['status' => 'success', 'message' => 'Lịch sử trò chuyện.', 'data' => [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn (AssistantMessage $message): array => $this->messageResource($message)),
        ]]);
    }

    public function chat(Request $request, AiServiceClient $ai): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['sometimes', 'nullable', 'integer'],
            'message' => ['required', 'string', 'min:2', 'max:1200'],
        ]);
        $text = trim($data['message']);
        if (mb_strlen($text) < 2) {
            return response()->json(['status' => 'error', 'message' => 'Câu hỏi cần có ít nhất 2 ký tự.', 'data' => null, 'errors' => ['message' => ['Câu hỏi cần có ít nhất 2 ký tự.']]], 422);
        }

        $userId = (int) $request->user()->id;
        $isNewConversation = ! isset($data['conversation_id']);
        $conversation = ! $isNewConversation
            ? $this->ownedConversation($request, (int) $data['conversation_id'])
            : AssistantConversation::create(['user_id' => $userId, 'title' => mb_substr($text, 0, 120)]);

        // Persist the user's question before the network call, but never hold a DB transaction open during inference.
        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $text]);
        try {
            $result = $ai->answer($text);
        } catch (Throwable $exception) {
            $userMessage->delete();
            if ($isNewConversation) {
                $conversation->delete();
            }
            Log::warning('Assistant request failed', ['user_id' => $userId, 'conversation_id' => $conversation->id, 'exception' => $exception::class]);
            return response()->json(['status' => 'error', 'message' => 'Trợ lý đang tạm thời không khả dụng. Vui lòng thử lại sau.', 'data' => null, 'error' => ['code' => 'AI_UNAVAILABLE']], 503);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant', 'content' => $result['answer'], 'citations' => $result['citations'],
        ]);
        $conversation->touch();

        return response()->json(['status' => 'success', 'message' => 'Đã nhận câu trả lời.', 'data' => [
            'conversation_id' => $conversation->id,
            'user_message' => $this->messageResource($userMessage),
            'assistant_message' => $this->messageResource($assistantMessage),
        ]]);
    }

    public function destroy(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->ownedConversation($request, $conversationId);
        $conversation->delete();
        return response()->json(['status' => 'success', 'message' => 'Đã xóa cuộc trò chuyện.', 'data' => null]);
    }

    private function ownedConversation(Request $request, int $id): AssistantConversation
    {
        return AssistantConversation::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function messageResource(AssistantMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'citations' => $message->citations ?? [],
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
