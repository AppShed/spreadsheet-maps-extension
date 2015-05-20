<?php

namespace AppShed\Extensions\SpreadsheetMapsBundle\Service;

use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Cache\ApcCache;

/**
 * Description of GeoService
 *
 * @author Victor
 */
class GeoService {

    private $geoClient;

    private $logger;

    public function __construct(Client $geoClient, LoggerInterface $logger )
    {
        $this->geoClient = $geoClient;
        $this->logger = $logger;
    }

    public function getGeo($address)
    {

        try {
            $cachePlugin = new CachePlugin(array(
                'storage' => new DefaultCacheStorage(
                    new DoctrineCacheAdapter(
                        new ApcCache(__DIR__.'/../../../../../app/cache')
                    )
                )
            ));

            $this->geoClient->addSubscriber($cachePlugin);

            $geoResponse = $this->geoClient->get(
                '',
                [],
                [
                    'query'=>[
                        'sensor' => false,
                        'address' => $address
                    ]
                ]
            )->send();

            if ($geoResponse->getStatusCode() != 200) {
                return false;
            }

            $resp = $geoResponse->json();


            if ($resp['status'] != 'OK' || !count($resp['results'])) {
                return false;
            }

            return $resp['results'][0]['geometry']['location'];

        } catch(RequestException $e ) {
            $this->logger->error(
                'Problem reading a spreadsheet',
                [
                    'exception' => $e
                ]
            );
        }

        return false;

    }

}
