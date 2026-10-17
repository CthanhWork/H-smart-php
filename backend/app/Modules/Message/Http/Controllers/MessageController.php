<?php

namespace App\Modules\Message\Http\Controllers;

use App\Modules\Message\Models\Message;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MessageController
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $messages = Message::with(['product:id,title', 'sender:id,full_name', 'recipient:id,full_name'])
            ->where(fn ($query) => $query->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest('id')->limit(1000)->get();
        $threads = $messages->groupBy(fn (Message $message) => $message->product_id.':'.($message->sender_id === $userId ? $message->recipient_id : $message->sender_id))
            ->map(function ($thread) use ($userId): array {
                /** @var Message $latest */
                $latest = $thread->first();
                $other = $latest->sender_id === $userId ? $latest->recipient : $latest->sender;
                return ['product_id' => $latest->product_id, 'product_title' => $latest->product->title,
                    'user_id' => $other->id, 'user_name' => $other->full_name ?: 'Thành viên',
                    'last_message' => $latest->body, 'updated_at' => $latest->created_at->toIso8601String(),
                    'unread_count' => $thread->where('recipient_id', $userId)->whereNull('read_at')->count()];
            })->values();
        return response()->json(['status' => 'success', 'message' => 'Hộp thư của bạn.', 'data' => $threads]);
    }

    public function show(Request $request, int $productId, int $userId): JsonResponse
    {
        $currentId = (int) $request->user()->id;
        abort_unless($currentId !== $userId, 404);
        $product = Product::findOrFail($productId);
        $other = User::findOrFail($userId);
        $isSellerThread = $currentId === (int) $product->seller_id && $userId !== (int) $product->seller_id;
        $isBuyerThread = $userId === (int) $product->seller_id && $currentId !== (int) $product->seller_id;
        abort_unless($isSellerThread || $isBuyerThread, 404);
        $query = $this->pairQuery($productId, $currentId, $userId);
        abort_unless($isBuyerThread || $query->exists(), 404);
        $messages = $query->with(['sender:id,full_name', 'recipient:id,full_name'])->orderBy('id')->get();
        Message::whereIn('id', $messages->where('recipient_id', $currentId)->whereNull('read_at')->pluck('id'))
            ->update(['read_at' => now()]);
        return response()->json(['status' => 'success', 'message' => 'Tin nhắn cuộc trò chuyện.', 'data' => [
            'product' => ['id' => $product->id, 'title' => $product->title],
            'user' => ['id' => $other->id, 'full_name' => $other->full_name ?: 'Thành viên'],
            'messages' => $messages->map(fn (Message $message) => $this->resource($message))->values(),
        ]]);
    }

    public function store(Request $request, int $productId, NotificationService $notifications): JsonResponse
    {
        $request->merge(['body' => trim((string) $request->input('body'))]);
        $data = $request->validate(['recipient_id' => ['required', 'integer', 'exists:users,id'], 'body' => ['required', 'string', 'min:1', 'max:2000']]);
        $senderId = (int) $request->user()->id;
        $recipientId = (int) $data['recipient_id'];
        abort_unless($senderId !== $recipientId, 404);
        $product = Product::findOrFail($productId);
        if ($senderId === (int) $product->seller_id) {
            abort_unless($this->pairQuery($productId, $senderId, $recipientId)->exists(), 404);
        } else {
            abort_unless($recipientId === (int) $product->seller_id, 404);
        }
        if (in_array($product->status, [Product::STATUS_HIDDEN, Product::STATUS_PENDING_REVIEW, Product::STATUS_DRAFT], true)) {
            throw new ConflictHttpException('Không thể nhắn tin về sản phẩm này.');
        }
        $message = Message::create(['product_id' => $productId, 'sender_id' => $senderId, 'recipient_id' => $recipientId, 'body' => trim($data['body'])])
            ->load(['sender:id,full_name', 'recipient:id,full_name']);
        $notifications->send($message->recipient, $message->sender, 'new_message', 'Tin nhắn mới', 'Bạn có tin nhắn mới về “'.$product->title.'”.', '/me/messages?productId='.$productId.'&userId='.$senderId, ['product_id' => $productId, 'message_id' => $message->id]);
        return response()->json(['status' => 'success', 'message' => 'Đã gửi tin nhắn.', 'data' => $this->resource($message)], 201);
    }

    private function pairQuery(int $productId, int $firstId, int $secondId)
    {
        return Message::where('product_id', $productId)->where(fn ($query) => $query
            ->where(fn ($pair) => $pair->where('sender_id', $firstId)->where('recipient_id', $secondId))
            ->orWhere(fn ($pair) => $pair->where('sender_id', $secondId)->where('recipient_id', $firstId)));
    }

    private function resource(Message $message): array
    {
        return ['id' => $message->id, 'sender_id' => $message->sender_id, 'recipient_id' => $message->recipient_id,
            'body' => $message->body, 'read_at' => $message->read_at?->toIso8601String(), 'created_at' => $message->created_at->toIso8601String(),
            'sender_name' => $message->sender->full_name ?: 'Thành viên'];
    }
}
