<?php

namespace AppShed\Extensions\SpreadsheetBundle\Controller;

use AppShed\Extensions\SpreadsheetBundle\Exceptions\SpreadsheetNotFoundException;
use AppShed\Remote\Element\Item\HTML;
use AppShed\Remote\Element\Item\Text;
use AppShed\Remote\Element\Screen\Screen;
use AppShed\Remote\HTML\Remote;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppShed\Extensions\SpreadsheetBundle\Entity\Doc;
use ZendGData\Spreadsheets\DocumentQuery;

/**
 * @Route("/spreadsheet/write", service="app_shed_extensions_spreadsheet.controller.write")
 */
class WriteController extends SpreadsheetController
{

    /**
     * @Route("/edit")
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

            $url = $request->get('url');
            $key = $this->getKey($url);
            $action = $request->get('action', false);

            try {
                $worksheet = $this->getDocument($key);

                $lines = $worksheet->getContentsAsRows();
                if (is_array($lines) && isset($lines['0']) && is_array($lines['0'])) {
                    $titles = array_keys($lines['0']);
                }

                $doc->setUrl($url);
                $doc->setKey($key);
                $doc->setTitles(array_unique($titles));

                $em->persist($doc);
                $em->flush();
            } catch (SpreadsheetNotFoundException $e) {
                $this->logger->error(
                    'Spreadsheet not found',
                    [
                        'exception' => $e
                    ]
                );

                return array(
                    'doc' => $doc,
                    'action' => $action,
                    'error' => 'Could not access the document'
                );
            }
        }

        return array(
            'doc' => $doc,
            'action' => $action
        );
    }

    /**
     * @Route("/document/")
     */
    public function documentAction(Request $request)
    {
        if (Remote::isOptionsRequest()) {
            return Remote::getCORSSymfonyResponse();
        }

        $rowData = Remote::getRequestVariables();

        $secret = $request->get('identifier');

        $em = $this->getDoctrine()->getManager();
        $doc = $em->getRepository('AppShedExtensionsSpreadsheetBundle:Doc')->findOneBy(array('itemsecret' => $secret));

        if (!$doc) {
            $screen = new Screen('Error');
            $screen->addChild(new HTML('You must setup the extension before using it'));
            return (new Remote($screen))->getSymfonyResponse();
        }

        try {
            $existingTitles = $this->getColumnTitles($doc->getKey());

            $store = false;
            foreach ($rowData as $titleName => $value) {
                if (strlen($value) == '') {
                    unset($rowData[$titleName]);
                }
                if (!in_array($titleName, $existingTitles)) {
                    $store = true;
                    $this->addTitle($titleName, $doc->getKey());
                    $existingTitles[] = $titleName;
                }
            }

            if ($store) {
                $doc->setTitles(array_unique($existingTitles));
                $em->persist($doc);
                $em->flush();
            }


            if (count($rowData) > 0) {
                $this->getSpreadsheets()->insertRow($rowData, $doc->getKey(), 1);
            }
        } catch (\Exception $e) {
            $screen = new Screen('Error');
            $screen->addChild(new HTML('There was an error storing'));
            $screen->addChild(new Text($e->getMessage()));

            $this->logger->error(
                'Problem accessing a spreadsheet',
                [
                    'exception' => $e
                ]
            );
            return (new Remote($screen))->getSymfonyResponse();
        }

        $screen = new Screen('Saved');
        $screen->addChild(new HTML("Your record has been saved"));
        return (new Remote($screen))->getSymfonyResponse();
    }

    private function getColumnTitles($key)
    {

        $titles = array();

        $worksheet = $this->getDocument($key);
        if ($worksheet != null) {

            $lines = $worksheet->getContentsAsRows();
            if (is_array($lines) && isset($lines['0']) && is_array($lines['0'])) {
                $titles = array_keys($lines['0']);
            }
        }
        return $titles;
    }

    /**
     * @param $key
     * @throws SpreadsheetNotFoundException
     * @return \ZendGData\Spreadsheets\WorksheetEntry
     */
    protected function getDocument($key)
    {
        $query = new DocumentQuery();
        $query->setSpreadsheetKey($key);
        $feed = $this->getSpreadsheets()->getWorksheetFeed($query);

        if (!isset($feed[0])) {
            throw new SpreadsheetNotFoundException("Failed to find spreadsheet $key");
        }

        return $feed[0];
    }

    /**
     * Add a new title to the spreadsheet
     *
     * @param $name
     * @param $key
     */
    private function addTitle($name, $key)
    {
        $index = $this->findEmptyColumn($key);
        $this->getSpreadsheets()->updateCell('1', $index, $name, $key);
    }

    /**
     * Find the next empty cell in the first row
     *
     * @param $key
     * @return int
     */
    private function findEmptyColumn($key)
    {
        $worksheet = $this->getDocument($key);
        $cells = $worksheet->getContentsAsCells();
        $col = 'A';
        while (isset($cells["{$col}1"])) {
            $col++;
        }
        return ord($col) - 64;
    }
}
