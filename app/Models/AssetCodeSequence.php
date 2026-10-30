<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AssetCodeSequence extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];
}
