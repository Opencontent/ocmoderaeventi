<?php

class updateObjectStateType extends eZWorkflowEventType
{
	const TYPE_ID = 'updateobjectstate';
	public function __construct()
    {
		$this->eZWorkflowEventType( self::TYPE_ID, 'Moderazione e ricollocazione eventi' );
	}
	public function execute( $process, $event )
    {
		$processParams = $process->attribute( 'parameter_list' );
		$trigger = $processParams['trigger_name'];;
		if( $trigger == 'post_updateobjectstate' )
		{
			$objectID = $processParams['object_id'];
			$contentObject = eZContentObject::fetch( $objectID );
			if( $contentObject instanceof eZContentObject )
			{
				$helper = new OCModeraEventiHelper( $contentObject );
                if ( $helper->needWorkflow() )
                {
                    $newStates = $processParams['state_id_list'];
                    $helper->processNewStates( $newStates );
                }
			}
		}
        return eZWorkflowType::STATUS_ACCEPTED;
	}
}

eZWorkflowEventType::registerEventType( updateObjectStateType::TYPE_ID, 'updateObjectStateType' );