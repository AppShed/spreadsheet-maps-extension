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

            if($geoResponse->getStatusCode()==200){

                $resp = $geoResponse->json();

                if ($resp['status']=='OK' && count($resp['results'])) {

                    $lat = $resp['results'][0]['geometry']['location']['lat'];
                    $lon = $resp['results'][0]['geometry']['location']['lng'];

                    return ['lon'=>$lon, 'lat'=>$lat];
                }
            }

            return false;

        } catch(RequestException $e ) {

            return false;
        }

    }

}
