<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentWalletRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_from',
        'request_to',
        'wallet_for',
        'dealer_request_id',
        'amount',
        'status',
        'approved_at',
        'wallet_status'
    ];


    public function getDealerReq()
    {
        return $this->hasOne(DealerWalletRequest::class, 'id', 'dealer_request_id');
    }

    public function requestUser(){
        return $this->hasOne(User::class, 'id', 'request_from');
    }

    public function forUser(){
        return $this->hasOne(User::class, 'id', 'wallet_for');
    }
}
