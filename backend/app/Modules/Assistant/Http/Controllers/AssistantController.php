<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Models\AssistantConversation;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Services\AiServiceClient;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $orderId = $this->requestedOrderId($text, $conversation);
        $orderResult = $orderId === null ? null : $this->answerOrderQuestion($orderId, $userId);
        // Persist the user's question before the model call, but never hold a DB transaction open during inference.
        $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $text]);
        if ($orderId !== null) {
            $result = $orderResult;
            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant', 'content' => $result['answer'], 'citations' => $result['citations'],
            ]);
            $conversation->touch();

            return response()->json(['status' => 'success', 'message' => 'Đã tra cứu đơn hàng.', 'data' => [
                'conversation_id' => $conversation->id,
                'user_message' => $this->messageResource($userMessage),
                'assistant_message' => $this->messageResource($assistantMessage),
            ]]);
        }

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

    private function requestedOrderId(string $question, AssistantConversation $conversation): ?int
    {
        $normalizedQuestion = Str::ascii(mb_strtolower($question));
        if (preg_match('/don(?:\s*hang)?\s*#?\s*(\d+)/i', $normalizedQuestion, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(don nay|don do|vay|con don|trang thai|thu cod|nhan tien)\b/i', $normalizedQuestion)) {
            $lastAnswer = $conversation->messages()->where('role', 'assistant')->latest('id')->value('content');
            if (is_string($lastAnswer) && preg_match('/Đơn hàng #(\d+)/u', $lastAnswer, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function answerOrderQuestion(int $orderId, int $userId): array
    {
        $order = Order::with('product:id,title')->findOrFail($orderId);
        abort_unless(in_array($userId, [$order->buyer_id, $order->seller_id], true), 404);

        $statusLabels = [
            'pending_payment' => 'chờ thanh toán hoặc người bán xác nhận',
            'paid' => 'đã thanh toán, chờ người bán xác nhận',
            'processing' => 'người bán đang chuẩn bị hàng',
            'shipping' => 'đang vận chuyển',
            'completed' => 'đã hoàn tất',
            'cancelled' => 'đã hủy',
        ];
        $answer = 'Đơn hàng #'.$order->id.' ('.$order->product->title.') hiện '.($statusLabels[$order->status] ?? $order->status).'.';
        if ($order->shipping_carrier) $answer .= ' Đơn vị vận chuyển: '.$order->shipping_carrier.'.';
        if ($order->tracking_number) $answer .= ' Mã vận đơn: '.$order->tracking_number.'.';
        if ($order->payment_provider === 'cod') {
            $answer .= $order->cod_collected_at ? ' Đơn vị giao hàng đã thu COD.' : ' Chưa ghi nhận thu COD.';
            if ($order->seller_id === $userId) {
                $answer .= ' Hoa hồng sàn: '.number_format($order->platform_fee, 0, ',', '.').'đ. Người bán dự kiến nhận: '.number_format($order->product_amount - $order->platform_fee, 0, ',', '.').'đ.';
                $answer .= $order->carrier_settled_at ? ' Tiền COD đã được đối soát về sàn.' : ' Tiền COD chưa được đối soát về sàn.';
                $answer .= $order->seller_paid_at ? ' Khoản tiền người bán đã được ghi nhận chi trả.' : ' Chưa ghi nhận chi trả cho người bán.';
            } else {
                $answer .= ' Tổng tiền đơn: '.number_format($order->total_amount, 0, ',', '.').'đ.';
            }
        } elseif ($order->payment_status === 'paid') {
            $answer .= ' Thanh toán VNPay đã được xác nhận.';
        }

        return ['answer' => $answer, 'citations' => [[
            'source_id' => 'order',
            'title' => 'Mở danh sách đơn hàng',
            'section' => '/me/orders',
        ]]];
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
