<?php

namespace Tests\Feature;

use App\Modules\Product\Actions\ApproveProductAction;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPublication;
use App\Modules\Product\Services\ListingPublicationService;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ListingPublicationTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        config(['services.marketplace.listing_free_monthly' => 5, 'services.marketplace.listing_fee' => 5000]);
    }

    public function test_first_five_approved_publications_are_free_and_sixth_waits_for_explicit_fee_payment(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $approve = app(ApproveProductAction::class);
        $products = [];
        for ($index = 1; $index <= 6; $index++) {
            $products[] = Product::create(['seller_id' => $seller->id, 'title' => 'Listing '.$index, 'price' => 100000, 'condition' => 'good', 'status' => Product::STATUS_PENDING_REVIEW]);
        }

        foreach (array_slice($products, 0, 5) as $product) {
            $this->assertSame(Product::STATUS_ACTIVE, $approve->execute($product)->status);
        }
        $sixth = $approve->execute($products[5]);
        $this->assertSame(Product::STATUS_PENDING_LISTING_PAYMENT, $sixth->status);
        $this->assertSame(5000, $sixth->publication->fee_amount);
        $this->assertSame(5, app(ListingPublicationService::class)->quote($seller->id)['used']);

        app(WalletLedger::class)->post($seller->id, 'TEST_TOPUP_LISTING', 'simulated_deposit', [
            ['user_id' => $seller->id, 'account_code' => 'available', 'amount' => 5000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -5000],
        ], 'test', null, ['amount' => 5000]);
        $published = app(ListingPublicationService::class)->pay($seller->id, $sixth->id, 5000, app(WalletLedger::class));

        $this->assertSame(Product::STATUS_ACTIVE, $published->status);
        $this->assertSame(0, app(WalletLedger::class)->balance($seller->id)['available']);
        $this->assertSame('published', ProductPublication::where('product_id', $sixth->id)->value('status'));
    }

    public function test_reapproval_and_deleting_a_published_product_do_not_restore_free_quota(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Listing', 'price' => 100000, 'condition' => 'good', 'status' => Product::STATUS_PENDING_REVIEW]);
        $approve = app(ApproveProductAction::class);
        $approve->execute($product);
        DB::table('products')->where('id', $product->id)->update(['status' => Product::STATUS_PENDING_REVIEW]);
        $approve->execute($product->refresh());
        $product->delete();

        $quote = app(ListingPublicationService::class)->quote($seller->id);
        $this->assertSame(1, $quote['used']);
        $this->assertSame(4, $quote['remaining']);
        $this->assertNull(ProductPublication::where('seller_id', $seller->id)->value('product_id'));
    }

    public function test_changed_listing_fee_quote_does_not_charge_without_new_confirmation(): void
    {
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $approve = app(ApproveProductAction::class);
        for ($index = 1; $index <= 5; $index++) {
            $product = Product::create(['seller_id' => $seller->id, 'title' => 'Free '.$index, 'price' => 100000, 'condition' => 'good', 'status' => Product::STATUS_PENDING_REVIEW]);
            $approve->execute($product);
        }
        $paidProduct = Product::create(['seller_id' => $seller->id, 'title' => 'Paid listing', 'price' => 100000, 'condition' => 'good', 'status' => Product::STATUS_PENDING_REVIEW]);
        $approve->execute($paidProduct);
        $admin = User::create(['username' => 'listing-admin', 'email' => 'listing-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);
        app(\App\Modules\Admin\Services\MarketplaceSettings::class)->update(['listing_fee' => 6000], (int) $admin->id);

        try {
            app(ListingPublicationService::class)->pay($seller->id, $paidProduct->id, 5000, app(WalletLedger::class));
            $this->fail('A stale fee quote was charged');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('Hạn mức tháng đã thay đổi', $exception->getMessage());
        }

        $this->assertSame(Product::STATUS_PENDING_LISTING_PAYMENT, $paidProduct->refresh()->status);
        $this->assertSame(0, app(WalletLedger::class)->balance($seller->id)['available']);
        $this->assertSame(0, ProductPublication::where('product_id', $paidProduct->id)->whereNotNull('paid_at')->count());
    }
}
