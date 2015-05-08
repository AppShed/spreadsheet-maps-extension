<?php
namespace AppShed\Extensions\SpreadsheetBundle\Controller;

use AppShed\Extensions\SpreadsheetBundle\Service\GeoService;
use Doctrine\Bundle\DoctrineBundle\Registry;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ZendGData\Spreadsheets;

abstract class SpreadsheetController extends Controller
{
    /**
     * @var Spreadsheets
     */
    protected $spreadsheets;

    /**
     * @var Registry
     */
    protected $doctrine;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var GeoService
     */
    protected $geoClient;


    public function __construct(Spreadsheets $spreadsheets, Registry $doctrine, LoggerInterface $logger, Client $geoClient )
    {
        $this->spreadsheets = $spreadsheets;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->geoClient = $geoClient;
    }

    /**
     * @return \Doctrine\Bundle\DoctrineBundle\Registry
     */
    public function getDoctrine()
    {
        return $this->doctrine;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return \ZendGData\Spreadsheets
     */
    protected function getSpreadsheets()
    {
        return $this->spreadsheets;
    }

    /**
     * Finds the key query param from a url
     *
     * @param $docUrl
     * @return string
     */
    protected function getKey($docUrl) 
	{
        preg_match('/([a-zA-Z0-9_-]){44}/',$docUrl,$matches);
        if (isset($matches['0'])) {
            return $matches['0'];
        }
        return null;
    }
}