<?php

namespace AppShed\Extensions\SpreadsheetBundle\Controller;

use AppShed\Remote\Element\Item\HTML;
use AppShed\Remote\Element\Item\Link;
use AppShed\Remote\Element\Screen\Screen;
use AppShed\Remote\HTML\Remote;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppShed\Extensions\SpreadsheetBundle\Entity\Doc;

/**
 * @Route("/spreadsheet/read", service="app_shed_extensions_spreadsheet.controller.read")
 */
class ReadController extends SpreadsheetController
{


    private $errors = array();

    /**
     * @Route("/edit/")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $action = '';
        $secret = $request->get('identifier');
        $em = $this->getDoctrine()->getManager();
        $doc = $em->getRepository('AppShedExtensionsSpreadsheetBundle:Doc')->findOneBy(array('itemsecret' => $secret));


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

            $url = $request->get('url', false);

            $action = $request->get('action', false);

            $key = $this->getKey($url);
            if ($url && $key) {

                $filters = $request->get('filters', array());


                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles($this->getRowTitles($key));
                $doc->setFilters(array_values($filters));

                $em->persist($doc);
                $em->flush();
            } else {
                if ($url == false) {
                    $this->errors[] = 'Spreadsheet url is empty';
                } else {
                    $this->errors[] = 'Spreadsheet url is not supported or broken';
                }
            }
        }

        return array(
            'doc' => $doc,
            'action' => $action,
            'error' => $this->getErrors()
        );
    }

    /**
     * @Route("/document/")
     */
    public function documentAction(Request $request)
    {
        if(Remote::isOptionsRequest()) {
            return Remote::getCORSSymfonyResponse();
        }

        $secret = $request->get('identifier');
        $type = $request->get('type', 'normal');


        $doc = $this->getDoctrine()
            ->getManager()
            ->getRepository('AppShedExtensionsSpreadsheetBundle:Doc')
            ->findOneBy(array('itemsecret' => $secret));

        $document = $this->getDocument(
            $this->getSpreadsheets(),
            $doc->getKey(),
            $this->getFilterString($doc->getFilters())
        );

        //This screen will have a list of the values in A column
        $screen = new Screen($document->getTitle());

        //For each row of the table
        foreach ($document as $entry) {

            $index = true;
            $lines = $entry->getCustom();

            //Each of the columns of the row
            foreach ($lines as $customEntry) {

                $name = $customEntry->getColumnName();
                $value = $customEntry->getText();

                //If the name of a column ends with a '-' then we dont show it
                if (((strlen($name) - 1) == strpos($name, '-')) == false) {
                    if ($index == true) {
                        //This screen will have all the values across the row
                        $innerScreen = new Screen($value);

                        $link = new Link($value);
                        $screen->addChild($link);
                        $index = false;
                        $link->setScreenLink($innerScreen);
                    } else {
                        if (!empty($value)) {
                            $innerScreen->addChild(new HTML($value));
                        }
                    }
                }
            }
        }

        return (new Remote($screen))->getSymfonyResponse();
    }

    private function getRowTitles($key)
    {
        $adapter = $this->getSpreadsheets();
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


    private function getErrors()
    {
        return implode('<br>', $this->errors);

    }

    private function getDocument($adapter, $key, $filter = null)
    {
        $listFeed = array();
        if ($adapter instanceof \ZendGData\Spreadsheets) {
            try {
                $query = new \ZendGData\Spreadsheets\ListQuery();
                $query->setSpreadsheetKey($key);
                if ($filter) {
                    $query->setSpreadsheetQuery($filter);
                }
                $listFeed = $adapter->getListFeed($query);

            } catch (HttpException $exc) {
                $this->errors[] = 'No read premissoin or other error';
            }
        }
        return $listFeed;
    }

    private function getKey($docUrl)
    {
        $params = array();
        $urlParts = parse_url($docUrl);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $params);
            $urlParams = $params;
        }
        return isset($urlParams['key']) ? $urlParams['key'] : null;
    }

    private function getFilterString($filter)
    {
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

    private function getAroundMeQuery($distance)
    {
        $this->aroundme = $distance;
        $center = array(
            'lat' => isset($_GET['userlat']) ? $_GET['userlat'] : 0,
            'lng' => isset($_GET['userlng']) ? $_GET['userlng'] : 0
        );
        $bounds = $this->getBounds($center, $distance);
        $filters[] = 'lat > ' . $bounds['minLat'];
        $filters[] = 'lat < ' . $bounds['maxLat'];
        $filters[] = 'lng > ' . $bounds['minLng'];
        $filters[] = 'lng < ' . $bounds['maxLng'];

        return implode(' AND ', $filters);
    }

    public function distanceOrt($position, $point, $limit = false)
    {
        $ra = M_PI / 180;
        $b = $position['lat'] * $ra;
        $c = $point['lat'] * $ra;
        $f = (2 * asin(
                    sqrt(
                        pow(sin(($b - $c) / 2), 2) + cos($b) * cos($c) * pow(
                            sin(($position['lng'] * $ra - $point['lng'] * $ra) / 2),
                            2
                        )
                    )
                )) * 6378137;

        if ($limit) {
            return $f <= $limit;
        } else {
            return $f;
        }
    }

    private function getConv($center)
    {
        return array(
            'lat' => $this->distanceOrt(
                    $center,
                    array('lat' => ($center['lat'] + 0.1), 'lng' => ($center['lng']))
                ) / 100,
            'lng' => $this->distanceOrt($center, array('lat' => $center['lat'], 'lng' => ($center['lng'] + 0.1))) / 100
        );
    }

    public function pointPosion($conv, $center, $r, $angle)
    {
        $r = $r / 1000;
        return array(
            'lat' => $center['lat'] + ($r / $conv['lat'] * cos($angle * M_PI / 180)),
            'lng' => $center['lng'] + ($r / $conv['lng'] * sin($angle * M_PI / 180)),
            'angle' => $angle
        );
    }

    public function getTextLength($d)
    {
        if ($d > 1000) {
            $d = $d / 1000;
            return round($d, 2) . 'Km';
        } else {
            return round($d) . 'm';
        }
    }

    public function getBounds($center, $radius)
    {
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
