<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['product_id', 'url', 'sort_order'];
}
