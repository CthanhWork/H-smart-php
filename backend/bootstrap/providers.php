<?php

use App\Modules\Admin\Providers\AdminServiceProvider;
use App\Modules\Offer\Providers\OfferServiceProvider;
use App\Modules\Order\Providers\OrderServiceProvider;
use App\Modules\Review\Providers\ReviewServiceProvider;
use App\Modules\Message\Providers\MessageServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\Product\Providers\ProductServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    ProductServiceProvider::class,
    AdminServiceProvider::class,
    OfferServiceProvider::class,
    OrderServiceProvider::class,
    ReviewServiceProvider::class,
    MessageServiceProvider::class,
    NotificationServiceProvider::class,
];
