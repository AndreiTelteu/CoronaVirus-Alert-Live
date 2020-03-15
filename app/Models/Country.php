<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $fillable = [
        'name',
        'code',
        'aliases',
    ];
    protected $casts = [
        'aliases' => 'array',
    ];
    
    public function entries() {
        return $this->hasMany(Entry::class, 'country_id', 'id');
    }
}
