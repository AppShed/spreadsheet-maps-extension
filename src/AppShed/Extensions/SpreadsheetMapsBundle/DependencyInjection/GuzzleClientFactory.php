<?php
/**
 * Created by Victor on 26/11/14 09:23
 */
namespace AppShed\Extensions\SpreadsheetMapsBundle\DependencyInjection;

use GuzzleHttp\Client;
use GuzzleHttp\Event\SubscriberInterface;

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