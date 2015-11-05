<?php
/**
 * Created by PhpStorm.
 * User: vitaliy
 * Date: 11/5/15
 * Time: 2:43 PM
 */

namespace AppShed\Extensions\SpreadsheetMapsBundle\DependencyInjection;

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
use Google\Spreadsheet\SpreadsheetService;

class SpreadsheetFactory
{

    public static function get($google_client_id, $googleClientEmail, $keyPath)
    {

        $client = new \Google_Client();

        $client->setApplicationName('Spreadsheet');
        $client->setClientId($google_client_id); //client id

        $credentials = new \Google_Auth_AssertionCredentials(
            $googleClientEmail,
            [
                'https://spreadsheets.google.com/feeds',
                'https://docs.google.com/feeds'
            ],

            file_get_contents($keyPath)
        );

        $client->setAssertionCredentials($credentials);

        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($credentials);
        }

        $accessToken = json_decode($client->getAccessToken());

        $serviceRequest = new DefaultServiceRequest($accessToken->access_token);
        ServiceRequestFactory::setInstance($serviceRequest);

        return new SpreadsheetService();

    }


}