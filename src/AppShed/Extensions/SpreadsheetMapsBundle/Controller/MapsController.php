<?php

namespace AppShed\Extensions\SpreadsheetMapsBundle\Controller;


use AppShed\Extensions\SpreadsheetMapsBundle\Entity\Doc;
use AppShed\Remote\Element\Item\HTML;
use AppShed\Remote\Element\Item\Marker;
use AppShed\Remote\Element\Screen\Screen;
use AppShed\Remote\HTML\Remote;
use Google\Spreadsheet\ListEntry;
use Google\Spreadsheet\Spreadsheet;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/")
 */
class MapsController extends Controller
{

    /**
     * @Route("/edit")
     * @Template()
     * @param Request $request
     *
     * @return array
     */
    public function indexAction(Request $request)
    {
        $action = '';
        $secret = $request->get('identifier');
        $em     = $this->getDoctrine()->getManager();
        $doc    = $em->getRepository('AppShedExtensionsSpreadsheetMapsBundle:Doc')->findOneBy(['itemsecret' => $secret]);
        $errors = '';
        if (is_null($doc)) {
            $doc = new Doc();
            $doc->setKey('');
            $doc->setUrl('');
            $doc->setTitles([]);
            $doc->setFilters([]);
            $doc->setItemsecret($secret);
            $doc->setDate(new \DateTime());
        }
        if ($request->isMethod('post')) {
            $url     = $request->get('url', false);
            $address = $request->get('address');
            $action  = $request->get('action', false);
            $key     = $this->getKey($url);
            if ($url && $key) {
                $filters = $request->get('filters', []);
                $doc->setAddress($address);
                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles($this->getRowTitles($key));
                $doc->setFilters(array_values($filters));
                $em->persist($doc);
                $em->flush();
            } else {
                if ( ! $url) {
                    $errors = 'Spreadsheet url is empty';
                } else {
                    $errors = 'Spreadsheet url is not supported or broken';
                }
            }
        }

        return [
            'doc'    => $doc,
            'action' => $action,
            'error'  => $errors
        ];
    }

    /**
     * @Route("/view")
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function documentAction(Request $request)
    {
        if (Remote::isOptionsRequest()) {
            return Remote::getCORSSymfonyResponse();
        }
        $secret = $request->get('identifier');
        /** @var Doc $doc */
        $doc = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('AppShedExtensionsSpreadsheetMapsBundle:Doc')
                    ->findOneBy(['itemsecret' => $secret]);
        if ( ! $doc) {
            $screen = new Screen('Error');
            $screen->addChild(new HTML('You must setup the extension before using it'));

            return (new Remote($screen))->getSymfonyResponse();
        }

        $address = strtolower($doc->getAddress() ?: 'address');

        try {

            $document = $this->getDocument($doc->getKey());

            //This screen will have a list of the values in A column
            $screen     = new Screen($document->getTitle());
            $worksheets = $document->getWorksheets();
            $worksheet  = $worksheets[0];

            $filters = $this->getFilterString($doc->getFilters(), $request);
            if ( ! empty($filters)) {
                $lines = $worksheet->getListFeed(["sq" => $filters])->getEntries();
            } else {
                $lines = $worksheet->getListFeed()->getEntries();
            }
            $geo = $this->get('app_shed_extensions_spreadsheet.geo');

            //For each row of the table

            foreach ($lines as $lineEntry) {
                $index = true;
                /**
                 * @var ListEntry $lineEntry
                 */

                $lineColumns = $lineEntry->getValues();

                //Each of the columns of the row
                foreach ($lineColumns as $name => $value) {

                    //If the name of a column ends with a '-' then we don't show it
                    if (((strlen($name) - 1) == strpos($name, '-')) == false) {
                        if ($index == true) {
                            $innerScreen = new Screen($value);
                            //This screen will have all the values across the row
                            $index       = false;
                        } else {
                            if ( ! empty($value)) {
                                if ($name == $address) {
                                    $position = $geo->getPosition($value);

                                    if ($position) {
                                        $marker = new Marker($address, $value, $position['lng'], $position['lat']);
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
            $this->get('logger')->error(
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
        $doc    = $this->getDocument($key);
        $titles = [];
        foreach ($doc as $entry) {
            foreach ($entry->getCustom() as $customEntry) {
                $titles[] = $customEntry->getColumnName();
            }
            break;
        }

        return $titles;
    }

    /**
     * @param $key
     *
     * @return Spreadsheet
     */
    private function getDocument($key)
    {

        $spreadsheet = $this->get('app_shed_extensions_spreadsheet.spreadsheet')->getSpreadsheetById($key);

        return $spreadsheet;
    }

    private function getFilterString($filter, Request $request)
    {
        $filters = [];
        foreach ($filter as $option) {
            if ($option['filter'] == 'aroundme') {
                $filters[] = $this->getAroundMeQuery($option['value'], $request);
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

    private function getAroundMeQuery($distance, Request $request)
    {
        $center    = [
            'lat' => $request->query->get('userlat', 0),
            'lng' => $request->query->get('userlng', 0)
        ];
        $bounds    = $this->getBounds($center, $distance);
        $filters[] = 'lat > ' . $bounds['minLat'];
        $filters[] = 'lat < ' . $bounds['maxLat'];
        $filters[] = 'lng > ' . $bounds['minLng'];
        $filters[] = 'lng < ' . $bounds['maxLng'];

        return implode(' AND ', $filters);
    }

    private function getBounds($center, $radius)
    {
        $conv              = $this->getConv($center);
        $bounces           = [];
        $top               = $this->getPointPosition($conv, $center, $radius, 0);
        $right             = $this->getPointPosition($conv, $center, $radius, 90);
        $bottom            = $this->getPointPosition($conv, $center, $radius, 180);
        $left              = $this->getPointPosition($conv, $center, $radius, 270);
        $bounces['minLng'] = $left['lng'];
        $bounces['maxLng'] = $right['lng'];
        $bounces['minLat'] = $bottom['lat'];
        $bounces['maxLat'] = $top['lat'];

        return $bounces;
    }

    private function distanceOrt($position, $point, $limit = false)
    {
        $ra = M_PI / 180;
        $b  = $position['lat'] * $ra;
        $c  = $point['lat'] * $ra;
        $f  = (2 * asin(
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
        return [
            'lat' => $this->distanceOrt(
                    $center,
                    ['lat' => ($center['lat'] + 0.1), 'lng' => ($center['lng'])]
                ) / 100,
            'lng' => $this->distanceOrt($center, ['lat' => $center['lat'], 'lng' => ($center['lng'] + 0.1)]) / 100
        ];
    }

    private function getPointPosition($conv, $center, $r, $angle)
    {
        $r = $r / 1000;

        return [
            'lat'   => $center['lat'] + ($r / $conv['lat'] * cos($angle * M_PI / 180)),
            'lng'   => $center['lng'] + ($r / $conv['lng'] * sin($angle * M_PI / 180)),
            'angle' => $angle
        ];
    }


    /**
     * Finds the key query param from a url
     *
     * @param $docUrl
     *
     * @return string
     */
    protected function getKey($docUrl)
    {
        preg_match('/([a-zA-Z0-9_-]){44}/', $docUrl, $matches);
        if (isset($matches['0'])) {
            return $matches['0'];
        }

        return null;
    }

}
