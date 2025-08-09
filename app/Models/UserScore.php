<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserScore extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'score', 'level'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
