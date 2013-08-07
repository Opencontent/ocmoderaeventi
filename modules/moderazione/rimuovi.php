<?php

$module = $Params['Module'];
$contentObjectID = $Params['id'];

$contentObject = eZContentObject::fetch( $contentObjectID );
if( !$contentObject instanceof eZContentObject )
{
    return $module->handleError( eZError::KERNEL_NOT_FOUND, 'kernel' );
}
else 
{
    $redirectURI = 'content/dashboard/';

    $http = eZHTTPTool::instance();
    if ( $http->hasVariable( "RedirectURI" ) )
    {
        $redirectURI = $http->variable( 'RedirectURI' );
    }
    
    // cestina questo oggetto
    $contentObject->removeThis();
    
    return $module->redirectTo( $redirectURI );
}

eZExecution::cleanExit();