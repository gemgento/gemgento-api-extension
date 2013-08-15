<?php

$proxy = new SoapClient('http://jc.l/index.php/api/soap/?wsdl');
$api_user = 'philip.vasilevski';
$api_pass = '857123FHDShfd';
$queryString = 'jacket';

try {
    $sessionId = $proxy->login($api_user, $api_pass);
    $results = $proxy->call($sessionId, 'productSearch.results', array($queryString)); // make API call
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

print_r($results);
?>
