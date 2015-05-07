<?php

namespace AppShed\Extensions\SpreadsheetBundle\Service;

use GuzzleHttp\Client;

/**
 * Description of GeoService
 *
 * @author Victor
 */
class GeoService {

    public function getGeo($address)
    {
        $guzzle = new Client();

        $address = urlencode($address);

        $url = "http://maps.google.com/maps/api/geocode/json?sensor=false&address={$address}";

        $res = $guzzle->get($url);

        if($res->getStatusCode()==200){

            $resp = $res->json();

            if ($resp['status']=='OK') {

                $lati = @$resp['results'][0]['geometry']['location']['lat'];
                $longi = @$resp['results'][0]['geometry']['location']['lng'];

                if($lati && $longi){
                    return [$lati, $longi];
                }

            }
        }

        return false;
    }

}
