<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{

    /**
     * Datasource table name
     * 
     * @param string $table
     */
    protected $table = 'properties';

    /**
     * Fields that can be mass assigned.
     * 
     * @param array $fillable
     */
    protected $fillable = [
        'api_id',
        'county',
        'country',
        'town',
        'description',
        'address',
        'image_full',
        'image_thumbnail',
        'latitude',
        'longitude',
        'num_bedrooms',
        'num_bathrooms',
        'price',
        'property_type',
        'type',
    ];

    /**
     * Not going to bother with timestamps as not requested.
     * 
     * @param bool $timestamps
     */
    public $timestamps = false;

}
