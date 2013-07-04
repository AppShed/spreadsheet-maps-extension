<?php

namespace AppshedExtension\SpreadsheetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;

 
class DefaultController extends Controller {

    var $filterTypes = array('<', '>', '=', '!=', '<=', '>=',
            //'aroundme'
    );

    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction() {
         
        return array('hi'=>'hello');
    }
    
}
