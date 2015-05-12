<?php

namespace AppShed\Extensions\SpreadsheetBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Description of GeoService
 *
 * @author Victor
 */
class GeoService {

    private $geoClient;

    public function __construct(Client $geoClient)
    {
        $this->geoClient = $geoClient;
    }

    public function getGeo($address)
    {

        try {
            $geoResponse = $this->geoClient->get(
                '',
                [
                    'query' => [
                        'address' => $address
                    ]
                ]
            );

            if ($geoResponse->getStatusCode() != 200) {
                return false;
            }

            $resp = $geoResponse->json();

            if ($resp['status'] != 'OK' || !count($resp['results'])) {
                return false;
            }

            return $resp['results'][0]['geometry']['location'];

        } catch(RequestException $e ) {

            return false;
        }

    }

}
