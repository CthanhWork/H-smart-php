<?php

namespace Tests\Feature;

use App\Modules\Admin\Services\MarketplaceSettings;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class MarketplaceSettingsTest extends TestCase
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
        $this->app->instance('env', 'testing');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_admin_settings_override_defaults_and_persist_for_new_requests(): void
    {
        $admin = User::create(['username' => 'settings-admin', 'email' => 'settings-admin@example.test', 'password_hash' => 'unused']);
        $settings = app(MarketplaceSettings::class);
        $this->assertSame((int) config('services.marketplace.commission_rate'), $settings->get('commission_rate'));

        $result = $settings->update(['commission_rate' => 4, 'cod_deposit_percent' => 30, 'listing_fee' => 7000], (int) $admin->id);
        $this->assertSame(4, $result['commission_rate']);
        $this->assertSame(30, $result['cod_deposit_percent']);
        $this->assertSame(7000, app(MarketplaceSettings::class)->get('listing_fee'));
        $this->assertDatabaseHas('marketplace_settings', ['key' => 'cod_deposit_percent', 'value' => 30, 'updated_by' => $admin->id]);
    }
}
