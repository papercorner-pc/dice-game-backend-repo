<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'game_status', 'user_id', 'is_publishable', 'countdown'];
}
