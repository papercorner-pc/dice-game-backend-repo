<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealerWalletRequest extends Model
{

    use HasFactory;

    protected $fillable = [
        'request_from',
        'request_to',
        'wallet_for',
        'amount',
        'status',
        'approved_at',
        'wallet_status'
    ];

}
