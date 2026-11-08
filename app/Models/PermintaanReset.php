<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanReset extends Model
{
    protected $table = 'permintaan_reset';

    protected $fillable = ['user_id', 'username', 'catatan', 'ip', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}