<?php
/**
 * Created by Victor on 26/11/14 09:23
 */
namespace AppShed\Extensions\SpreadsheetMapsBundle\DependencyInjection;

use Doctrine\Common\Cache\ApcCache;
use GuzzleHttp\Client;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;

class GuzzleClientFactory
{
    public static function get(array $options, array $subscribers)
    {

        $client = new Client($options);

        foreach ($subscribers as $subscriber) {
            $client->getEmitter()->attach($subscriber);
        }

        return $client;
    }
}
