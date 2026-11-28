<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These account/category changes are local fixture cleanup, not production migration work.
        if (app()->environment('production')) {
            return;
        }

        $legacyAccounts = DB::table('users')
            ->whereIn('email', [
                'qa.admin.20261015@hsmart.local',
                'qa.seller.20261015@hsmart.local',
                'qa.buyer.20261015@hsmart.local',
            ])
            ->get(['id']);

        foreach ($legacyAccounts as $account) {
            DB::table('users')->where('id', $account->id)->update([
                'username' => 'archived-account-'.$account->id,
                'email' => 'archived-account-'.$account->id.'@hsmart.local',
                'full_name' => null,
                'status' => 'suspended',
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->where('email', 'demo-seller@hsmart.local')->update([
            'username' => 'seller-hsmart',
            'email' => 'seller@hsmart.local',
            'full_name' => 'Cửa hàng H-Smart',
            'updated_at' => now(),
        ]);
        DB::table('users')->where('email', 'demo-buyer@hsmart.local')->update([
            'username' => 'buyer-hsmart',
            'email' => 'buyer@hsmart.local',
            'full_name' => 'Khách hàng H-Smart',
            'updated_at' => now(),
        ]);

        $legacyCategories = DB::table('categories')->where('slug', 'like', 'qa-%')->pluck('id');
        if ($legacyCategories->isNotEmpty()) {
            DB::table('products')->whereIn('category_id', $legacyCategories)->update(['category_id' => null]);
            DB::table('categories')->whereIn('id', $legacyCategories)->delete();
        }
    }

    public function down(): void
    {
        // Archived accounts and legacy category assignments are intentionally not restored.
    }
};
