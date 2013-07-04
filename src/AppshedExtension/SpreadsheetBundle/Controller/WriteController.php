<?php

namespace AppshedExtension\SpreadsheetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Zend;
use AppShed;
use AppShed\Element\Screen as AppShedScreen;
use AppShed\Element\Item as AppShedItem;
use AppshedExtension\SpreadsheetBundle\Entity\Doc as Doc;

/**
 * @Route("/spreadsheet/write")
 */
class WriteController extends Controller {

    var $filterTypes = array('<', '>', '=', '!=', '<=', '>=',
            //'aroundme'
    );

    /**
     * @Route("/edit")
     * @Template()
     */
    public function indexAction() {
        $request = $this->getRequest();

        $secret = $request->get('identifier');

        $em = $this->getDoctrine()->getManager();
        $doc = $em->getRepository('AppshedExtensionSpreadsheetBundle:Doc')->findOneBy(array('itemsecret' => $secret));


        if (is_null($doc)) {
            $doc = new Doc();
            $doc->setKey('');
            $doc->setUrl('');
            $doc->setTitles(array());
            $doc->setFilters(array());
            $doc->setItemsecret($secret);
            $doc->setDate(new \DateTime());
        }


        if ($request->isMethod('post')) {

            $url = $request->get('url');
            $key = $this->getKey($url);
            $filters = $request->get('filters', array());
            $titles = array();

            $worksheet = $this->getDocument($this->getSpreadsheetAdapter(), $key);
            if ($worksheet != null) {

                $lines = $worksheet->getContentsAsRows();
                if (is_array($lines) && isset($lines['0']) && is_array($lines['0'])) {
                    $titles = array_keys($lines['0']);
                }

                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles($titles);
                $doc->setFilters(array_values($filters));

                $em->persist($doc);
                $em->flush();
            }
        }

        return array(
            'doc' => $doc,
            'filterTypes' => $this->filterTypes
        );
    }

    private function getSpreadsheetAdapter() {

        $client = $this->googleLogin();
        $adapter = new \ZendGData\Spreadsheets($client);
        return $adapter;
    }

    /**
     * @Route("/document/")
     */
    public function documentAction() {
        $request = $this->getRequest();

        if ($request->isMethod('options')) {
            header('Access-Control-Max-Age: 86400');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: accept, origin, x-requested-with, x-request');
            exit;
        }

        $rowData = $this->getData($_REQUEST['fetchURL']);

        
        unset($rowData['clientId']);
        unset($rowData['clientSecret']);
        unset($rowData['appId']);
        unset($rowData['appSecret']);
        unset($rowData['itemid']);
        unset($rowData['identifier']);
                 
 
        $secret = $request->get('identifier');
        $type = $request->get('type' , 'normal');

        $em = $this->getDoctrine()->getManager();
        $doc = $em->getRepository('AppshedExtensionSpreadsheetBundle:Doc')->findOneBy(array('itemsecret' => $secret));
        if (!is_null($doc)) {

            $adapter = $this->getSpreadsheetAdapter();
            $existingTitles = $doc->getTitles();

            $store = false;
            foreach ($rowData as $titlename => $value) {
                if(strlen($value)==''){
                    unset($rowData[$titlename]);
                }
                if (!in_array($titlename, $existingTitles)) {
                    $store = true;
                    $this->addTitle($titlename, $adapter, $doc->getKey());
                    $existingTitles[] = $titlename;
                }
            }

            if ($store) {
                $doc->setTitles($existingTitles);
                $em->persist($doc);
                $em->flush();
            }

            if ($adapter instanceof \ZendGData\Spreadsheets) {
                try {
                    if(count($rowData)>0){
                        $entry = $adapter->insertRow($rowData, $doc->getKey(), 1);
                        if ($entry instanceof Zend_Gdata_Spreadsheets_ListEntry) {
                            return true;
                        }
                    }
                } catch (Exception $exc) {
                    $this->errors[] = 'No write premissoin';
                }
            }
        }

        $screen = new AppShedScreen\Screen('Saved');
        $screen->addChild(new AppShedItem\HTML("Your record has been saved"));
        $remote = new AppShed\HTML\Remote($screen);

        if ($type == 'jsonp') {
            $response = new Response($remote->getResponse(null, false, true));
        } else {
            $response = new Response($remote->getResponse(null, false, true));
        }



        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }

    private function addTitle($name, $adapter, $key) {

        $worksheet = $this->getDocument($adapter, $key);
        $index = $this->findEmptyColumn($worksheet->getContentsAsCells());
        $adapter->updateCell('1', $index, $name, $key);
    }

    private function getTitles($key) {
        $titles = array();
        $adapter = $this->getSpreadsheetAdapter();
        $worksheet = $this->getDocument($adapter, $key);
        if ($worksheet != null) {
            foreach ($worksheet as $entry) {
                foreach ($entry->getCustom() as $customEntry) {
                    $titles[] = $customEntry->getColumnName();
                }
                break;
            }
        }
        return $titles;
    }

    private function getDocument($adapter, $key) {
        if ($adapter instanceof \ZendGData\Spreadsheets) {

            $query = new \ZendGData\Spreadsheets\DocumentQuery();
            $query->setSpreadsheetKey($key);
            $feed = $adapter->getWorksheetFeed($query);

            if (isset($feed['0'])) {
                return $feed['0'];
            }
            return null;
        }
    }

    private $alphabet = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');

    private function findEmptyColumn($param) {

        $emptyColums = array();

        foreach ($this->alphabet as $index => $char) {
            if (!isset($param[$char . '1'])) {
                return $index + 1;
                $emptyColums[] = $index + 1;
            } else {
                //$titles ''
            }
        }
        return $emptyColums;
    }

    private function getKey($docurl) {
        $params = array();
        $urloptios = parse_url($docurl);
        if (isset($urloptios['query'])) {
            parse_str($urloptios['query'], $params);
            $docurlparams = $params;
        }
        return isset($docurlparams['key']) ? $docurlparams['key'] : null;
    }

    private function getData($url) {
        $params = array();
        $urloptios = parse_url($url);
        if (isset($urloptios['query'])) {
            parse_str($urloptios['query'], $params);
            $docurlparams = $params;
        }
        return  $params;
    }
    
    private function googleLogin() {
        $service = \ZendGData\Spreadsheets::AUTH_SERVICE_NAME;
        $clientAdapter = new \Zend\Http\Client\Adapter\Curl();
        $clientAdapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
        $clientAdapter->setCurlOption(CURLOPT_SSL_VERIFYPEER, false);
        $httpClient = new \ZendGData\HttpClient();
        $httpClient->setAdapter($clientAdapter);
        $client = \ZendGData\ClientLogin::getHttpClient("appshed.docs@gmail.com", "password", $service, $httpClient);


        return $client;
    }

    private function getAroundMeQuery($distance) {
        $this->aroundme = $distance;
        $mapModel = AppBuilderHelper::getMapModel();
        $center = array(
            'lat' => JRequest::getVar('userlat', null),
            'lng' => JRequest::getVar('userlng', null)
        );
        $bounds = $mapModel->getBounds($center, $distance);
        $filters[] = 'lat > ' . $bounds['minLat'];
        $filters[] = 'lat < ' . $bounds['maxLat'];
        $filters[] = 'lng > ' . $bounds['minLng'];
        $filters[] = 'lng < ' . $bounds['maxLng'];

        return implode(' AND ', $filters);
    }

}
