<?php

namespace AppShed\Extensions\SpreadsheetMapsBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class GeoService
{

    private $geoClient;

    private $logger;

    public function __construct(Client $geoClient, LoggerInterface $logger)
    {
        $this->geoClient = $geoClient;

        $this->logger = $logger;
    }


    public function getPosition($address)
    {

        try {

            $geoResponse = $this->geoClient->get(
                'http://maps.google.com/maps/api/geocode/json',
                [
                    'query' => [
                        'sensor'  => false,
                        'address' => $address
                    ]
                ]
            );



            if ($geoResponse->getStatusCode() != 200) {
                return false;
            }

            $resp = $geoResponse->json();

            if ($resp['status'] != 'OK' || ! count($resp['results'])) {
                return false;
            }

            return $resp['results'][0]['geometry']['location'];

        } catch (RequestException $e) {

            $this->logger->error(
                'Can\'t geocode address  ' . $address,
                [
                    'exception' => $e
                ]
            );
        }

        return false;

    }

}
