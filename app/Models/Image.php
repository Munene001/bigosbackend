<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;
    protected $table = 'Images';
    protected $primaryKey = 'image_id';

    protected $fillable = [
        'property_id',
        'image_url',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
    ];
    public $timestamps = false;

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

}
