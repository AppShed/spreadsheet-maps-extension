<?php
/**
 * Created by Victor on 26/11/14 09:23
 */
namespace AppShed\Extensions\SpreadsheetMapsBundle\DependencyInjection;

use Guzzle\Http\Client;

class GuzzleClientFactory
{
    public static function get(array $options)
    {
        $client = new Client($options['base_url'],$options['headers'], $options['query']);

        return $client;
    }
}
