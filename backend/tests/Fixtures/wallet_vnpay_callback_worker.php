<?php

use App\Modules\Admin\Http\Controllers\AdminDisputeController;
use App\Modules\Admin\Http\Controllers\AdminWithdrawalController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

$payload = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => $payload['database_default'],
    'database.connections.'.$payload['database_default'] => $payload['database_connection'],
]);
DB::purge($payload['database_default']);

if (($payload['mode'] ?? null) === 'race') {
    if (($argv[2] ?? '') === 'release') {
        echo app(ReleaseEligibleOrderFundsAction::class)->execute(app(WalletLedger::class), app(NotificationService::class));
    } else {
        $request = Request::create('/api/v1/admin/disputes/'.$payload['dispute_id'].'/resolve', 'POST', [
            'resolution' => 'manual_reconciliation', 'note' => 'Tiếp tục chờ đối soát trong test khóa MySQL.',
        ]);
        $request->setUserResolver(fn () => User::findOrFail($payload['admin_id']));
        $response = app(AdminDisputeController::class)->resolve(
            $request, (int) $payload['dispute_id'], app(WalletLedger::class), app(AdminAuditLogger::class), app(NotificationService::class),
        );
        echo $response->getData(true)['data']['status'];
    }
} elseif (($payload['mode'] ?? null) === 'withdrawal_outcome') {
    $request = Request::create('/api/v1/admin/withdrawals/'.$payload['withdrawal_id'].'/outcome', 'POST', [
        'outcome' => 'succeeded', 'note' => 'Xác nhận kết quả demo đồng thời trong test MySQL.',
        'sandbox_reference' => 'MYSQL-DEMO-'.$payload['withdrawal_id'],
    ]);
    $request->setUserResolver(fn () => User::findOrFail($payload['admin_id']));
    try {
        $response = app(AdminWithdrawalController::class)->confirmOutcome(
            $request, (int) $payload['withdrawal_id'], app(WalletLedger::class), app(AdminAuditLogger::class),
        );
        echo $response->getData(true)['data']['status'];
    } catch (ConflictHttpException) {
        echo 'conflict';
    }
} else {
    config([
        'services.vnpay.mode' => 'gateway',
        'services.vnpay.tmn_code' => $payload['tmn_code'],
        'services.vnpay.hash_secret' => $payload['hash_secret'],
    ]);
    echo app(WalletVnPayService::class)->processCallback($payload['query']);
}
