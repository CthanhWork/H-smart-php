<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Models\AssistantConversation;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Services\AiServiceClient;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Product\Services\ProductSearch;
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
            'search_context' => ['sometimes', 'array'],
            'search_context.product_ids' => ['sometimes', 'array', 'max:6'],
            'search_context.product_ids.*' => ['integer', 'min:1'],
        ]);
        $text = trim($data['message']);
        if (mb_strlen($text) < 2) {
            return response()->json(['status' => 'error', 'message' => 'Câu hỏi cần có ít nhất 2 ký tự.', 'data' => null, 'errors' => ['message' => ['Câu hỏi cần có ít nhất 2 ký tự.']]], 422);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $isNewConversation = $userId > 0 && ! isset($data['conversation_id']);
        $conversation = $userId === 0 ? null : (! $isNewConversation
            ? $this->ownedConversation($request, (int) $data['conversation_id'])
            : AssistantConversation::create(['user_id' => $userId, 'title' => mb_substr($text, 0, 120)]));

        $orderId = $conversation ? $this->requestedOrderId($text, $conversation) : null;
        $orderResult = $orderId === null ? null : $this->answerOrderQuestion($orderId, $userId);
        $userMessage = $conversation?->messages()->create(['role' => 'user', 'content' => $text]);
        if ($this->looksLikeProductSearch($text, $conversation, (array) ($data['search_context'] ?? []))) {
            $result = $this->searchProducts($text, $conversation, $ai, (array) ($data['search_context'] ?? []));
            $assistantMessage = $conversation?->messages()->create([
                'role' => 'assistant', 'content' => $result['content'], 'citations' => [], 'structured_data' => $result,
            ]);
            if ($conversation) $conversation->touch();
            return response()->json(['status' => 'success', 'message' => 'Đã tìm sản phẩm.', 'data' => [
                'conversation_id' => $conversation?->id,
                'user_message' => $userMessage ? $this->messageResource($userMessage) : ['id' => random_int(1, PHP_INT_MAX), 'role' => 'user', 'content' => $text, 'created_at' => now()->toIso8601String()],
                'assistant_message' => $assistantMessage ? $this->messageResource($assistantMessage) : ['id' => random_int(1, PHP_INT_MAX), 'role' => 'assistant', 'content' => $result['content'], 'structured_data' => $result, 'created_at' => now()->toIso8601String()],
                ...$result,
            ]]);
        }
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
            $userMessage?->delete();
            if ($isNewConversation && $conversation) {
                $conversation->delete();
            }
            Log::warning('Assistant request failed', ['user_id' => $userId ?: null, 'conversation_id' => $conversation?->id, 'exception' => $exception::class]);
            return response()->json(['status' => 'error', 'message' => 'Trợ lý đang tạm thời không khả dụng. Vui lòng thử lại sau.', 'data' => null, 'error' => ['code' => 'AI_UNAVAILABLE']], 503);
        }

        if (! $conversation) return response()->json(['status' => 'success', 'message' => 'Đã nhận câu trả lời.', 'data' => [
            'conversation_id' => null,
            'user_message' => ['id' => random_int(1, PHP_INT_MAX), 'role' => 'user', 'content' => $text, 'created_at' => now()->toIso8601String()],
            'assistant_message' => ['id' => random_int(1, PHP_INT_MAX), 'role' => 'assistant', 'content' => $result['answer'], 'citations' => $result['citations'], 'created_at' => now()->toIso8601String()],
            'content' => $result['answer'], 'intent' => 'general', 'products' => [], 'applied_filters' => [], 'actions' => [], 'citations' => $result['citations'],
        ]]);

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

    private function looksLikeProductSearch(string $text, ?AssistantConversation $conversation, array $clientContext = []): bool
    {
        $normalized = Str::ascii(mb_strtolower($text));
        if (preg_match('/\b(phi|thanh toan|giai ngan|chinh sach|hoan tien|van chuyen|rut tien|so du)\b/', $normalized)) return false;
        return (bool) preg_match('/\b(tim|kiem|san pham|loai|cai thu|re hon|gia thap|con tot|nhu moi|duoi|tren|nghin|trieu)\b/', $normalized)
            || (bool) $conversation?->search_context
            || (bool) ($clientContext['product_ids'] ?? []);
    }

    private function searchProducts(string $text, ?AssistantConversation $conversation, AiServiceClient $ai, array $clientContext = []): array
    {
        $previous = $conversation ? (array) ($conversation->search_context ?? []) : $this->safeGuestSearchContext($clientContext);
        try { $parsed = $ai->parseProductSearch($text, (array) ($previous['filters'] ?? [])); }
        catch (Throwable) { $parsed = $this->fallbackProductIntent($text, (array) ($previous['filters'] ?? [])); }
        $filters = (array) ($previous['filters'] ?? []);
        foreach (['min_price', 'max_price'] as $key) {
            if (isset($parsed[$key]) && is_numeric($parsed[$key]) && (int) $parsed[$key] >= 0 && (int) $parsed[$key] <= 2_000_000_000) $filters[$key] = (int) $parsed[$key];
        }
        if (in_array($parsed['condition'] ?? null, Product::CONDITIONS, true)) $filters['condition'] = $parsed['condition'];
        if (in_array($parsed['sort'] ?? null, ['newest', 'price_asc', 'price_desc'], true)) $filters['sort'] = $parsed['sort'];
        $keyword = trim(mb_substr((string) ($parsed['keyword'] ?? ''), 0, 120));
        if ($keyword === '' && ! empty($previous['keyword'])) $keyword = (string) $previous['keyword'];

        if (($parsed['intent'] ?? '') === 'select_product' && isset($parsed['reference_index'])) {
            $id = $previous['product_ids'][(int) $parsed['reference_index'] - 1] ?? null;
            $selected = $id ? Product::query()->whereKey((int) $id)->where('status', Product::STATUS_ACTIVE)
                ->with(['images', 'category', 'seller:id,username,full_name'])->first() : null;
            $products = $selected ? collect([$selected]) : collect();
        } else {
            $products = app(ProductSearch::class)->query($filters, $keyword)->limit(6)->get();
        }

        $snapshots = ProductResource::collection($products)->resolve();
        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $content = $products->isEmpty()
            ? 'Mình chưa tìm thấy sản phẩm phù hợp. Bạn có thể nới khoảng giá hoặc bỏ bớt điều kiện rồi thử lại.'
            : 'Mình tìm thấy '.count($snapshots).' sản phẩm đang được duyệt và còn bán. Giá và trạng thái được kiểm tra trực tiếp từ danh sách hiện tại.';
        if ($conversation) $conversation->update(['search_context' => ['filters' => $filters, 'keyword' => $keyword, 'product_ids' => $productIds, 'updated_at' => now()->toIso8601String()]]);
        return [
            'content' => $content,
            'intent' => (string) ($parsed['intent'] ?? 'search_products'),
            'products' => $snapshots,
            'applied_filters' => ['keyword' => $keyword, ...$filters],
            'actions' => [
                ['type' => 'view_more', 'label' => 'Xem thêm sản phẩm', 'url' => '/products?q='.urlencode($keyword)],
                ['type' => 'open_cart', 'label' => 'Xem giỏ hàng', 'url' => '/me/cart'],
            ],
            'citations' => [['source_id' => 'products', 'title' => 'Danh sách sản phẩm đang bán', 'section' => '/products']],
        ];
    }

    private function safeGuestSearchContext(array $context): array
    {
        $filters = (array) ($context['filters'] ?? []);
        $safeFilters = [];
        foreach (['min_price', 'max_price'] as $key) {
            if (isset($filters[$key]) && is_numeric($filters[$key]) && (int) $filters[$key] >= 0 && (int) $filters[$key] <= 2_000_000_000) $safeFilters[$key] = (int) $filters[$key];
        }
        if (in_array($filters['condition'] ?? null, Product::CONDITIONS, true)) $safeFilters['condition'] = $filters['condition'];
        if (in_array($filters['sort'] ?? null, ['newest', 'price_asc', 'price_desc'], true)) $safeFilters['sort'] = $filters['sort'];
        return [
            'filters' => $safeFilters,
            'keyword' => trim(mb_substr((string) ($context['keyword'] ?? ''), 0, 120)),
            'product_ids' => array_values(array_map('intval', (array) ($context['product_ids'] ?? []))),
        ];
    }

    private function fallbackProductIntent(string $text, array $context): array
    {
        $normalized = Str::ascii(mb_strtolower($text));
        $filters = $context;
        if (preg_match('/(duoi|toi da|nho hon|khong qua)\s*(\d[\d.,]*)\s*(trieu|tr|nghin|ngan|k)?/', $normalized, $match)) {
            $filters['max_price'] = (int) preg_replace('/\D/', '', $match[2]) * (in_array($match[3] ?? '', ['trieu', 'tr'], true) ? 1_000_000 : (in_array($match[3] ?? '', ['nghin', 'ngan', 'k'], true) ? 1_000 : 1));
        }
        if (preg_match('/(tren|tu|toi thieu|bat dau tu)\s*(\d[\d.,]*)\s*(trieu|tr|nghin|ngan|k)?/', $normalized, $match)) {
            $filters['min_price'] = (int) preg_replace('/\D/', '', $match[2]) * (in_array($match[3] ?? '', ['trieu', 'tr'], true) ? 1_000_000 : (in_array($match[3] ?? '', ['nghin', 'ngan', 'k'], true) ? 1_000 : 1));
        }
        if (str_contains($normalized, 'con tot')) $filters['condition'] = 'good';
        elseif (str_contains($normalized, 'nhu moi')) $filters['condition'] = 'like_new';
        if (str_contains($normalized, 're hon') || str_contains($normalized, 'gia thap')) $filters['sort'] = 'price_asc';
        $index = null;
        if (preg_match('/\b(?:cai|mon|san pham)\s+(?:thu\s+)?(\d+|nhat|nhi|hai|ba)\b/', $normalized, $match)) {
            $index = ['nhat' => 1, 'nhi' => 2, 'hai' => 2, 'ba' => 3][$match[1]] ?? (is_numeric($match[1]) ? (int) $match[1] : null);
        }
        $keyword = preg_replace('/\b(tim|kiem|cho|minh|toi|xem|san pham|loai|hang|duoi|tren|tu|toi da|gia|re hon|con tot|nhu moi|moi|nao|cai|thu|nghin|ngan|trieu)\b/', ' ', $normalized) ?? '';
        $keyword = preg_replace('/\d[\d.,]*\s*(trieu|tr|nghin|ngan|k)?/', ' ', $keyword) ?? '';
        return ['intent' => $index ? 'select_product' : 'search_products', 'reference_index' => $index, 'keyword' => trim(preg_replace('/\s+/', ' ', $keyword) ?? ''), ...$filters];
    }

    private function requestedOrderId(string $question, AssistantConversation $conversation): ?int
    {
        $normalizedQuestion = Str::ascii(mb_strtolower($question));
        if (preg_match('/don(?:\s*hang)?\s*#?\s*(\d+)/i', $normalizedQuestion, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(don nay|don do|don hang cua toi|don cua toi|vay|con don|trang thai|thu cod|nhan tien|o dau)\b/i', $normalizedQuestion)) {
            $lastAnswer = $conversation->messages()->where('role', 'assistant')->latest('id')->value('content');
            if (is_string($lastAnswer) && preg_match('/Đơn hàng #(\d+)/u', $lastAnswer, $matches)) {
                return (int) $matches[1];
            }
            return Order::query()->where(fn ($query) => $query->where('buyer_id', $conversation->user_id)->orWhere('seller_id', $conversation->user_id))
                ->latest('id')->value('id');
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
        if ($order->payment_method === 'cod') {
            $answer .= $order->cod_collected_at ? ' Đơn vị giao hàng đã thu COD.' : ' Chưa ghi nhận thu COD.';
            if ((int) $order->seller_id === $userId) {
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
            'structured_data' => $message->structured_data,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
