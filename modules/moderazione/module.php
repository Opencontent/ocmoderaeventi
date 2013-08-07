<?php

$Module = array( 'name' => 'Approva eventi',
                 'variable_params' => true );

$ViewList = array();
$ViewList['approva'] = array( 'script' => 'approva.php',
    'params' => array( 'id' ),
    'functions' => array( 'approva' )
);
$ViewList['rimuovi'] = array( 'script' => 'rimuovi.php',
    'params' => array( 'id' ),
    'functions' => array( 'rimuovi' )
);

$FunctionList = array();
$FunctionList['approva'] = array();
$FunctionList['rimuovi'] = array();

?>
