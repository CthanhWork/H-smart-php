<?php

use App\Modules\Wallet\Services\WalletVnPayService;
use App\Modules\Admin\Http\Controllers\AdminDisputeController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$payload = json_decode(base64_decode($argv[1] ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => $payload['database_default'],
    'database.connections.'.$payload['database_default'] => $payload['database_connection'],
]);
\Illuminate\Support\Facades\DB::purge($payload['database_default']);

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
} else {
    config([
        'services.vnpay.mode' => 'gateway',
        'services.vnpay.tmn_code' => $payload['tmn_code'],
        'services.vnpay.hash_secret' => $payload['hash_secret'],
    ]);
    echo app(WalletVnPayService::class)->processCallback($payload['query']);
}
