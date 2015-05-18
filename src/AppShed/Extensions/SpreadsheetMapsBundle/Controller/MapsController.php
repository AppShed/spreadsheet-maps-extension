<?php

namespace AppShed\Extensions\SpreadsheetMapsBundle\Controller;

use AppShed\Remote\Element\Item\HTML;
use AppShed\Remote\Element\Item\Link;
use AppShed\Remote\Element\Item\Marker;
use AppShed\Remote\Element\Item\Text;
use AppShed\Remote\Element\Screen\Map;
use AppShed\Remote\Element\Screen\Screen;
use AppShed\Remote\HTML\Remote;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use AppShed\Extensions\SpreadsheetMapsBundle\Entity\Doc;
use ZendGData\Spreadsheets\ListQuery;

/**
 * @Route("/spreadsheet/maps", service="app_shed_extensions_spreadsheet_maps.controller.maps")
 */
class MapsController extends SpreadsheetController
{
    /**
     * @Route("/edit")
     * @Route("/edit/")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $action = '';
        $secret = $request->get('identifier');
        $em = $this->getDoctrine()->getManager();
        $doc = $em->getRepository('AppShedExtensionsSpreadsheetMapsBundle:Doc')->findOneBy(array('itemsecret' => $secret));

        $errors = '';

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

            $address = $request->get('address');

            $action = $request->get('action', false);

            $key = $this->getKey($url);
            if ($url && $key) {

                $filters = $request->get('filters', array());

                $doc->setAddress($address);
                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles($this->getRowTitles($key));
                $doc->setFilters(array_values($filters));

                $em->persist($doc);
                $em->flush();
            } else {
                if ($url == false) {
                    $errors = 'Spreadsheet url is empty';
                } else {
                    $errors = 'Spreadsheet url is not supported or broken';
                }
            }
        }

        return array(
            'doc' => $doc,
            'action' => $action,
            'error' => $errors
        );
    }

    /**
     * @Route("/document")
     * @Route("/document/")
     */
    public function documentAction(Request $request)
    {
        if (Remote::isOptionsRequest()) {
            return Remote::getCORSSymfonyResponse();
        }

        $secret = $request->get('identifier');

        $doc = $this->getDoctrine()
            ->getManager()
            ->getRepository('AppShedExtensionsSpreadsheetMapsBundle:Doc')
            ->findOneBy(array('itemsecret' => $secret));

        if (!$doc) {
            $screen = new Screen('Error');
            $screen->addChild(new HTML('You must setup the extension before using it'));
            return (new Remote($screen))->getSymfonyResponse();
        }

        $address = $doc->getAddress();
        if (!$address) {
            $address = 'Address';
        }

        try {

            $document = $this->getDocument(
                $doc->getKey(),
                $this->getFilterString($doc->getFilters())
            );

            //This screen will have a list of the markers in Address column
            $screen = new Map($document->getTitle());

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
                            $index = false;
                        } else {
                            if (!empty($value)) {
                                if ($name == strtolower($address) ) {
                                    $geo = $this->geoService->getGeo($value);

                                    if ($geo) {
                                        $marker = new Marker($address, $value, $geo['lng'], $geo['lat']);

                                        $marker->setScreenLink($innerScreen);

                                        $screen->addChild($marker);
                                    }
                                }

                                $innerScreen->addChild(new HTML($value));
                            }
                        }
                    }
                }
            }

            return (new Remote($screen))->getSymfonyResponse();
        } catch (\Exception $e) {
            $screen = new Screen('Error');
            $screen->addChild(new HTML('There was an error reading'));
            $screen->addChild(new Text($e->getMessage()));

            $this->logger->error(
                'Problem reading a spreadsheet',
                [
                    'exception' => $e
                ]
            );
            return (new Remote($screen))->getSymfonyResponse();
        }
    }

    private function getRowTitles($key)
    {
        $doc = $this->getDocument($key);
        $titles = array();

        foreach ($doc as $entry) {
            foreach ($entry->getCustom() as $customEntry) {
                $titles[] = $customEntry->getColumnName();
            }
            break;
        }

        return $titles;
    }

    private function getDocument($key, $filter = null)
    {
        $query = new ListQuery();
        $query->setSpreadsheetKey($key);
        if ($filter) {
            $query->setSpreadsheetQuery($filter);
        }
        $listFeed = $this->getSpreadsheets()->getListFeed($query);
        return $listFeed;
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

    private function getBounds($center, $radius)
    {
        $conv = $this->getConv($center);
        $bounces = array();

        $top = $this->getPointPosition($conv, $center, $radius, 0);
        $right = $this->getPointPosition($conv, $center, $radius, 90);
        $bottom = $this->getPointPosition($conv, $center, $radius, 180);
        $left = $this->getPointPosition($conv, $center, $radius, 270);
        $bounces['minLng'] = $left['lng'];
        //$bounces['centerLng']=$center['lng'];
        $bounces['maxLng'] = $right['lng'];
        $bounces['minLat'] = $bottom['lat'];
        //$bounces['centerLat']=$center['lat'];
        $bounces['maxLat'] = $top['lat'];
        return $bounces;
    }

    private function distanceOrt($position, $point, $limit = false)
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

    private function getPointPosition($conv, $center, $r, $angle)
    {
        $r = $r / 1000;
        return array(
            'lat' => $center['lat'] + ($r / $conv['lat'] * cos($angle * M_PI / 180)),
            'lng' => $center['lng'] + ($r / $conv['lng'] * sin($angle * M_PI / 180)),
            'angle' => $angle
        );
    }
}
