<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Modules\Wallet\Services\WalletVnPayService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('make:admin {email} {password}', function (string $email, string $password) {
    $user = \App\Modules\User\Models\User::where('email', $email)->first();
    if ($user) {
        $user->role = 'admin';
        $user->status = 'active';
        $user->save();
        $this->info("Đã nâng cấp tài khoản [{$email}] thành Admin thành công!");
    } else {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;
        while (\App\Modules\User\Models\User::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $counter++;
        }

        $user = \App\Modules\User\Models\User::create([
            'email' => $email,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => 'admin',
            'status' => 'active',
            'full_name' => 'Quản trị viên',
        ]);
        $this->info("Tạo mới tài khoản Admin thành công!");
        $this->line("• Email: {$email}");
        $this->line("• Mật khẩu: {$password}");
    }
})->purpose('Tạo mới hoặc nâng cấp tài khoản Admin');

Artisan::command('orders:expire-pending', function (\App\Modules\Order\Actions\ExpirePendingOrdersAction $expire, \App\Modules\Notification\Services\NotificationService $notifications) {
    $count = $expire->execute($notifications);
    $this->info("Đã giải phóng {$count} đơn chờ thanh toán hết hạn.");
})->purpose('Giải phóng sản phẩm đã hết thời gian chờ thanh toán');

Schedule::command('orders:expire-pending')->everyMinute()->withoutOverlapping();

Artisan::command('orders:release-eligible', function (\App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction $release, \App\Modules\Wallet\Services\WalletLedger $ledger, \App\Modules\Notification\Services\NotificationService $notifications) {
    $count = $release->execute($ledger, $notifications);
    $this->info("Đã ghi có số dư cho {$count} đơn đủ điều kiện.");
})->purpose('Ghi có tiền thuần vào số dư người bán sau xác nhận giao và hết hạn khiếu nại');

Schedule::command('orders:release-eligible')->everyMinute()->withoutOverlapping();

Artisan::command('wallet:expire-vnpay-deposits', function (WalletVnPayService $deposits) {
    $count = $deposits->expirePending();
    $this->info("Đã hết hạn {$count} yêu cầu nạp VNPay chưa thanh toán.");
})->purpose('Đánh dấu yêu cầu nạp VNPay Sandbox đã quá hạn');

Schedule::command('wallet:expire-vnpay-deposits')->everyMinute()->withoutOverlapping();

Artisan::command('products:expire-promotions', function (\App\Modules\Product\Services\ProductPromotionService $promotions) {
    $count = $promotions->expire();
    $this->info("Đã hết hạn {$count} gói ưu tiên.");
})->purpose('Tự hết hạn gói đẩy và VIP');

Schedule::command('products:expire-promotions')->everyMinute()->withoutOverlapping();
