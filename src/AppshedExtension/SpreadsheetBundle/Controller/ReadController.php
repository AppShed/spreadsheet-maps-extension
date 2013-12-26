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
 * @Route("/spreadsheet/read")
 */
class ReadController extends Controller {

    
    private $errors = array();
    
    private $filterTypes = array('<', '>', '=', '!=', '<=', '>=',
            'aroundme'
    );

    /**
     * @Route("/test/")
     */
    public function testAction() {
        
        
        phpinfo();
        
        
        $request = $this->getRequest();

        $secret = $request->get('identifier');

        $screentwo = new AppShedScreen\Screen('My Screen 2');
        $screentwo->addChild(new AppShedItem\Text('Hi there again'));

        $screen = new AppShedScreen\Screen('My Screen');
        $screen->addChild(new AppShedItem\Text('Hi there'));
        $link = new AppShedItem\Link('The link');
        $screen->addChild($link);
        $link->setScreenLink($screentwo);

        $remote = new AppShed\HTML\Remote($screen);
        $remote->getResponse();


         
    }

    /**
     * @Route("/edit/")
     * @Template()
     */
    public function indexAction() {
        $action = '';
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

            $url = $request->get('url',false);
            
            $action = $request->get('action',false);
            
            $key = $this->getKey($url);
            if($url && $key){
                
                $filters = $request->get('filters', array());


                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles($this->getTitles($key));
                $doc->setFilters(array_values($filters));

                $em->persist($doc);
                $em->flush();
            }else{
                if($url==false){
                    $this->errors[] = 'Spreadsheet url is empty';
                }else{
                    $this->errors[] = 'Spreadsheet url is not supported or broken';
                }
            }
        }

        return array(
            'doc' => $doc,
            'action'=>$action,
            'error'=> $this->getErrors(),
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
        
        if($request->isMethod('options')){
            header('Access-Control-Max-Age: 86400');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: accept, origin, x-requested-with, x-request');
            exit;
        }
        $secret = $request->get('identifier');
        $type = $request->get('type' , 'normal');
        
        
        
        $doc = $this->getDoctrine()
                ->getManager()
                ->getRepository('AppshedExtensionSpreadsheetBundle:Doc')
                ->findOneBy(array('itemsecret' => $secret));

        $document = $this->getDocument(
                $this->getSpreadsheetAdapter(), $doc->getKey(), $this->getFilterString($doc->getFilters())
        );
 
        $screen = new AppShedScreen\Screen($document->getTitle());

        foreach ($document as $entry) {
            
            $index = true;
            $lines = $entry->getCustom();

            foreach ($lines as $customEntry) {

                $name = $customEntry->getColumnName();
                $value = $customEntry->getText();

                if (((strlen($name) - 1) == strpos($name, '-')) == false) {
                    if ($index == true) {
                        $screentwo = new AppShedScreen\Screen($value);
                        $link = new AppShedItem\Link($value);
                        $screen->addChild($link);
                        $index = false;
                        $link->setScreenLink($screentwo);
                    } else {
                        if(!empty($value)){
                            $screentwo->addChild(new AppShedItem\HTML($value));
                        }
                    }
                }
            }
        }
        
        $remote = new AppShed\HTML\Remote($screen);
        
        if($type=='jsonp'){
            $response = new Response($remote->getResponse(null , false, true));
        }else{
            $response = new Response($remote->getResponse(null , false, true));
            
        }
        
        
        
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }

    private function getTitles($key) {
        $adapter = $this->getSpreadsheetAdapter();
        $doc = $this->getDocument($adapter, $key);
        $titles = array();

        foreach ($doc as $entry) {
            foreach ($entry->getCustom() as $customEntry) {
                $titles[] = $customEntry->getColumnName();
            }
            break;
        }
 
        return $titles;
    }
    
    
    private function getErrors() {
        return implode('<br>', $this->errors);
        
    }

    private function getDocument($adapter, $key, $filter = null) {
        $listFeed = array();
        if ($adapter instanceof \ZendGData\Spreadsheets) {
            try {
                $query = new \ZendGData\Spreadsheets\ListQuery();
                $query->setSpreadsheetKey($key);
                if ($filter) {
                    $query->setSpreadsheetQuery($filter);
                }
                $listFeed = $adapter->getListFeed($query);
                
//               echo  $listFeed->getTitleValue().PHP_EOL;
//               echo  $listFeed->getTitle().PHP_EOL;
                
            } catch (HttpException $exc) {
                $this->errors[] = 'No read premissoin or other error';
            }
        }
        return $listFeed;
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

    private function getFilterString($filter) {
        $filters = array();

        foreach ($filter as $option) {

            if ($option['filter'] == 'aroundme') {
                $filters[] = $this->getAroundMeQuery($option['value']);
            } else {
                if (ctype_digit($option['value'])) {
                    $filters[] = $option['name'] . " " . $option['filter'] . " " . $option['value'] . ' ';
                } else {
                    if ($option['filter'] == 'like') {
                        $filters[] = $option['name'] . " " . $option['filter'] . ' %' . $option['value'] . '% ';
                    } else {
                        $filters[] = $option['name'] . " " . $option['filter'] . ' "' . $option['value'] . '" ';
                    }
                }
            }
        }

        $str = implode(' AND ', $filters);
        return $str;
    }

    private function googleLogin() {
        $service = \ZendGData\Spreadsheets::AUTH_SERVICE_NAME;
        $clientAdapter = new \Zend\Http\Client\Adapter\Curl();
        $clientAdapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
        $clientAdapter->setCurlOption(CURLOPT_SSL_VERIFYPEER, false);
        $httpClient = new \ZendGData\HttpClient();
        $httpClient->setAdapter($clientAdapter);

        
        try {
          $client = \ZendGData\ClientLogin::getHttpClient("appshed.docs@gmail.com", "password", $service, $httpClient);
        } catch (Zend_Gdata_App_CaptchaRequiredException $e) {
          echo '<a href="' . $e->getCaptchaUrl() . '">CAPTCHA answer required to login</a>';
        } catch (\ZendGData\Zend_Gdata_App_AuthException $e) {
          echo 'Error: ' . $e->getMessage();
          if ($e->getResponse() != null) {
            echo 'Body: ' . $e->getResponse()->getBody();
          }
        }
        
        return $client;
    }

    private function getAroundMeQuery($distance) {
        $this->aroundme = $distance;
        $center = array(
            'lat' => isset($_GET['userlat'])?$_GET['userlat']:0,
            'lng' => isset($_GET['userlng'])?$_GET['userlng']:0
        );
        $bounds = $this->getBounds($center, $distance);
        $filters[] = 'lat > ' . $bounds['minLat'];
        $filters[] = 'lat < ' . $bounds['maxLat'];
        $filters[] = 'lng > ' . $bounds['minLng'];
        $filters[] = 'lng < ' . $bounds['maxLng'];

        return implode(' AND ', $filters);
    }
    public function distanceOrt($position, $point, $limit = false) {
        $ra = M_PI / 180;
        $b = $position['lat'] * $ra;
        $c = $point['lat'] * $ra;
        $f = (2 * asin(sqrt(pow(sin(($b - $c) / 2), 2) + cos($b) * cos($c) * pow(sin(($position['lng'] * $ra - $point['lng'] * $ra) / 2), 2)))) * 6378137;

        if ($limit) {
            return $f <= $limit;
        } else {
            return $f;
        }
    }

    private function getConv($center) {
        return array('lat' => $this->distanceOrt($center, array('lat' => ($center['lat'] + 0.1), 'lng' => ($center['lng']))) / 100, 'lng' => $this->distanceOrt($center, array('lat' => $center['lat'], 'lng' => ($center['lng'] + 0.1))) / 100);
    }

    public function pointPosion($conv, $center, $r, $angle) {
        $r = $r / 1000;
        return array('lat' => $center['lat'] + ($r / $conv['lat'] * cos($angle * M_PI / 180)), 'lng' => $center['lng'] + ($r / $conv['lng'] * sin($angle * M_PI / 180)), 'angle' => $angle);
    }

    public function getTextLength($d) {
        if ($d > 1000) {
            $d = $d / 1000;
            return round($d, 2) . 'Km';
        } else {
            return round($d) . 'm';
        }
    }

    public function getBounds($center, $radius) {
        $conv = $this->getConv($center);
        $bounces = array();

        $top = $this->pointPosion($conv, $center, $radius, 0);
        $right = $this->pointPosion($conv, $center, $radius, 90);
        $bottom = $this->pointPosion($conv, $center, $radius, 180);
        $left = $this->pointPosion($conv, $center, $radius, 270);
        $bounces['minLng'] = $left['lng'];
        //$bounces['centerLng']=$center['lng'];
        $bounces['maxLng'] = $right['lng'];
        $bounces['minLat'] = $bottom['lat'];
        //$bounces['centerLat']=$center['lat'];
        $bounces['maxLat'] = $top['lat'];
        return $bounces;
    }

}
