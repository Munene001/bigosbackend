<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $table = "Properties";
    protected $fillable = [
        'title',
        'location',
        'location_url',
        'unit_type',
        'furnished',
        'price_ksh',
        'bedroom_count',
        'bathroom_count',
        'garage_count',
        'description',
        'features',
        'amenities',
        'listing_type',
        'construction_status',

    ];
    protected $casts = [
        'furnished' => 'string',
        'price_ksh' => 'decimal:2',
        'bedroom_count' => 'integer',
        'bathroom_count' => 'integer',
        'garage_count' => 'integer',
        'created_at' => 'datetime',
    ];
    public $timestamps = false;

    public function images()
    {
        return $this->hasMany(Image::class, 'property_id');
    }

}
