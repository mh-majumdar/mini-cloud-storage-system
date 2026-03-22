<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function activeFiles()
    {
        return $this->hasMany(File::class)->whereNull('deleted_at');
    }
}
