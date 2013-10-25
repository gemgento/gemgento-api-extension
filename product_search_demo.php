<?php
try {
    define("SOAP_WSDL",'http://jc.l/index.php/api/soap/?wsdl');
    define("SOAP_WSDL2",'http://jc.l/index.php/api/v2_soap/?wsdl');
    define("SOAP_USER","philip.vasilevski");
    define("SOAP_PASS","857123FHDShfd");

    if($_GET['ver'] == '2') {
        $client = new SoapClient(SOAP_WSDL2, array('trace' => 1,'cache_wsdl' => 0));
        echo "<br>version 2 <br>";
    }
    else {
        $client = new SoapClient(SOAP_WSDL,array('trace' => 1,'cache_wsdl' => 0));
        echo "<br>version 1 <br>";
    }

    $session = $client->login(SOAP_USER, SOAP_PASS);
    $result = array();

    try {
        if($_GET['ver'] == '2') {
             $result = $client->productSearchResults($session, 'jacket');
             var_dump ( $result);        
        } else {            
            $result= $client->call($session, 'productSearch.results', array($session, "jacket"));
            var_dump($result);
        }
    } catch (SoapFault $exception) {
        echo 'EXCEPTION='.$exception;
    }

    echo "<br>end test<br>";
} catch (Exception $e){
    echo var_dump($e);
    throw $e;
}   
?>
