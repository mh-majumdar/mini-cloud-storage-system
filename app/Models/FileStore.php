<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileStore extends Model
{
    protected $fillable = ['file_hash', 'file_size', 'ref_count'];

    public function files()
    {
        return $this->hasMany(File::class);
    }
}
