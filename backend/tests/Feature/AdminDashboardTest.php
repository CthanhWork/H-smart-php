<?php

namespace Tests\Feature;

use App\Modules\Auth\Services\TokenService;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'member'): User
    {
        return User::create(['username' => $email, 'email' => $email, 'password_hash' => Hash::make('violet-forest-river-2026'), 'role' => $role, 'status' => 'active']);
    }

    private function token(User $user): string { return app(TokenService::class)->accessToken($user); }

    public function test_report_submission_moderation_dashboard_stats_and_audit_log(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $reporter = $this->user('reporter@example.com');
        $seller = $this->user('seller@example.com');
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => Category::query()->value('id'), 'title' => 'Reported item', 'price' => 5000, 'condition' => 'good', 'status' => 'active']);

        $report = $this->withToken($this->token($reporter))->postJson('/api/v1/reports', [
            'target_type' => 'product', 'target_id' => $product->id, 'reason' => 'This listing appears to be fraudulent.', 'details' => 'The images and description do not match.',
        ])->assertCreated()->json('data');
        $this->withToken($this->token($admin))->getJson('/api/v1/admin/reports?status=pending')
            ->assertOk()->assertJsonPath('data.data.0.id', $report['id'])->assertJsonPath('data.data.0.reporter.email', $reporter->email)
            ->assertJsonPath('data.data.0.product.title', 'Reported item');
        $this->withToken($this->token($admin))->postJson('/api/v1/admin/reports/'.$report['id'].'/resolve', ['action' => 'hide_product'])
            ->assertOk()->assertJsonPath('data.status', 'actioned');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'hidden']);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'product_hidden', 'subject_id' => $product->id]);
        $this->withToken($this->token($admin))->getJson('/api/v1/admin/stats')
            ->assertOk()->assertJsonPath('data.users', 3)->assertJsonPath('data.products', 1)
            ->assertJsonPath('data.hidden_products', 1)->assertJsonPath('data.pending_reports', 0);
        $this->withToken($this->token($admin))->getJson('/api/v1/admin/audit-logs')
            ->assertOk()->assertJsonPath('data.data.0.admin.email', $admin->email);
    }

    public function test_category_changes_are_written_to_admin_audit_log(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $this->withToken($this->token($admin))->postJson('/api/v1/admin/categories', ['name' => 'Audio gear'])->assertCreated();
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'category_created', 'subject_type' => 'category']);
    }

    public function test_admin_can_search_orders_and_see_shipping_details(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $buyer = $this->user('buyer@example.com');
        $seller = $this->user('seller@example.com');
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => Category::query()->value('id'), 'title' => 'Desk lamp', 'price' => 20000, 'condition' => 'good', 'status' => 'reserved']);
        $order = Order::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_amount' => 20000,
            'shipping_fee' => 3000, 'platform_fee' => 0, 'total_amount' => 23000, 'status' => 'shipping',
            'shipping_address_snapshot' => ['recipient' => 'Buyer'], 'expires_at' => now()->addDay(),
            'shipping_carrier' => 'VNPost', 'tracking_number' => 'VN12345',
        ]);

        $this->withToken($this->token($admin))->getJson('/api/v1/admin/orders?q=VN12345&status=shipping')
            ->assertOk()->assertJsonPath('data.data.0.id', $order->id)
            ->assertJsonPath('data.data.0.buyer.email', $buyer->email)
            ->assertJsonPath('data.data.0.seller.email', $seller->email)
            ->assertJsonPath('data.data.0.tracking_number', 'VN12345');
    }

    public function test_admin_can_suspend_a_reported_account_and_audit_it(): void
    {
        $admin = $this->user('admin@example.com', 'admin');
        $reporter = $this->user('reporter@example.com');
        $reportedUser = $this->user('reported@example.com');
        $report = $this->withToken($this->token($reporter))->postJson('/api/v1/reports', [
            'target_type' => 'user', 'target_id' => $reportedUser->id, 'reason' => 'This account is sending repeated spam messages.',
        ])->assertCreated()->json('data');

        $this->withToken($this->token($admin))->postJson('/api/v1/admin/reports/'.$report['id'].'/resolve', ['action' => 'suspend_user'])
            ->assertOk()->assertJsonPath('data.status', 'actioned');
        $this->assertDatabaseHas('users', ['id' => $reportedUser->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'user_suspended', 'subject_id' => $reportedUser->id]);
    }
}
