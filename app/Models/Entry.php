<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $table = 'entries';
    protected $fillable = [
        'country_id',
        'cases',
        'deaths',
        'recovered',
        'active',
        'critical',
    ];
    protected $casts = [];
    
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
}
