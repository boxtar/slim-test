<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

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
        'postcode',
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

    /**
     * Find a resource by or throw a Not Found Exception
     * 
     * @param ServerRequestInterface $request The server request
     * @param string $id The id of the Property to be found
     * 
     * @return Property The property that you seek
     * @throws HttpNotFoundException Throws if Property not found
     */
    public static function findOrThrow(ServerRequestInterface $request, $id)
    {
        if (!$property = self::find($id)) {
            throw new HttpNotFoundException($request, "Property $id Not Found");
        }

        return $property;
    }
}
