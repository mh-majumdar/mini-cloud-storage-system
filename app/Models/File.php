<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'user_id',
        'file_store_id',
        'file_name',
        'file_size',
        'uploaded_at',
        'deleted_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fileStore()
    {
        return $this->belongsTo(FileStore::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}
