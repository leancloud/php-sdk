<?php

namespace LeanCloud;

/**
 * GeoPoint type representation
 *
 * It represents a geographic point, and supports computing geo
 * distance from point to point. It can also be used in Query to
 * build proximity-based queries.
 *
 * @see Query
 */
class GeoPoint {
    /**
     * @var number
     */
    private $latitude;

    /**
     * @var number
     */
    private $longitude;

    /**
     * Initialize a geo point
     *
     * @param number $latitude
     * @param number $longitude
     * @throws InvalidArgumentException
     */
    public function __construct($latitude=0.0, $longitude=0.0) {
        if ($latitude <= 90.0 && $latitude >= -90.0 &&
            $longitude <= 180.0 && $longitude >= -180.0) {
            $this->latitude  = $latitude;
            $this->longitude = $longitude;
        } else {
            throw new \InvalidArgumentException("Invalid latitude or " .
                                                "longitude for geo point");
        }
    }

    /**
     * @return number
     */
    public function getLatitude() {
        return $this->latitude;
    }

    /**
     * @return number
     */
    public function getLongitude() {
        return $this->longitude;
    }

    /**
     * Compute distance (in radians) to a geo point
     *
     * @param GeoPoint $point Other geo point
     * @return number
     */
    public function radiansTo(GeoPoint $point) {
        $d2r = M_PI / 180.0;
        $lat1rad = $this->getLatitude() * $d2r;
        $lon1rad = $this->getLongitude() * $d2r;
        $lat2rad = $point->getLatitude() * $d2r;
        $lon2rad = $point->getLongitude() * $d2r;
        $deltaLat = $lat1rad - $lat2rad;
        $deltaLon = $lon1rad - $lon2rad;
        $sinLat = sin($deltaLat / 2);
        $sinLon = sin($deltaLon / 2);
        $a = $sinLat * $sinLat +
           cos($lat1rad) * cos($lat2rad) * $sinLon * $sinLon;
        $a = min(1.0, $a);
        return 2 * asin(sqrt($a));
    }

    /**
     * Compute distance (in kilometers) to other geo point
     *
     * @param GeoPoint $point Other geo point
     * @return number
     */
    public function kilometersTo(GeoPoint $point) {
        return $this->radiansTo($point) * 6371.0;
    }

    /**
     * Compute distance (in miles) to other geo point
     *
     * @param GeoPoint $point Other geo point
     * @return number
     */
    public function milesTo(GeoPoint $point) {
        return $this->radiansTo($point) * 3958.8;
    }

    public function encode() {
        return array(
            '__type'    => 'GeoPoint',
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude
        );
    }
}
