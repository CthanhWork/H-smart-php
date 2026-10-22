<?php

namespace App\Modules\Order\Models;

use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => null, 'to_status' => $order->status,
                'changed_by' => Auth::id(), 'changed_at' => $order->created_at ?? now(),
            ]);
        });

        static::updated(function (Order $order): void {
            if (! $order->wasChanged('status')) return;
            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => $order->getOriginal('status'), 'to_status' => $order->status,
                'changed_by' => Auth::id(), 'changed_at' => now(),
            ]);
        });
    }

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const HOLDING_STATUSES = ['pending_payment', 'paid', 'processing', 'shipping', 'completed'];

    protected $fillable = [
        'product_id', 'buyer_id', 'seller_id', 'offer_id', 'product_amount',
        'shipping_fee', 'platform_fee', 'total_amount', 'status',
        'shipping_address_snapshot', 'expires_at', 'paid_at', 'shipped_at',
        'confirmed_at', 'shipping_carrier', 'tracking_number', 'completed_at', 'cancelled_at',
        'payment_txn_ref', 'payment_provider', 'payment_amount', 'payment_status', 'payment_transaction_no',
        'payment_response_code', 'payment_bank_code', 'payment_paid_at', 'review_rating',
        'review_comment', 'review_created_at', 'review_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'product_amount' => 'integer',
            'shipping_fee' => 'integer',
            'platform_fee' => 'integer',
            'total_amount' => 'integer',
            'shipping_address_snapshot' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_paid_at' => 'datetime',
            'payment_amount' => 'integer',
            'review_rating' => 'integer',
            'review_created_at' => 'datetime',
            'review_updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

}
