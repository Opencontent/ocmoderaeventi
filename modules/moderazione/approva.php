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
    
    $approvato = eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'StatoPubblicato');
    $approvato = (int) $approvato;
    
    if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
    {
        $operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
                                                        array( 'object_id'     => $contentObjectID,
                                                               'state_id_list' => array ( $approvato ) ) );
    }
    else
    {
        eZContentOperationCollection::updateObjectState( $contentObjectID, array( $approvato ) );
    }
    
    return $module->redirectTo( $redirectURI );
}

eZExecution::cleanExit();