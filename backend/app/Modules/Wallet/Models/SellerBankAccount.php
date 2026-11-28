<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

class SellerBankAccount extends Model
{
    protected $fillable = ['user_id', 'details_ciphertext'];
}
