<?php

class moderaEventiType extends eZWorkflowEventType {

	const TYPE_ID = 'moderaeventi';

	public function __construct()
    {
		$this->eZWorkflowEventType( self::TYPE_ID, 'Modera Eventi' );
	}

	public function execute( $process, $event )
    {
		$processParams = $process->attribute( 'parameter_list' );
		$trigger = $processParams['trigger_name'];
		if ( $trigger == 'post_publish' )
        {
			$objectID = $processParams['object_id'];
			$contentObject = eZContentObject::fetch( $objectID );
			if( $contentObject instanceof eZContentObject )
			{
				if( $contentObject->attribute( 'current_version' ) == 1 )
				{
					$classIdentifiers = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ClassIdentifiers' ) );
					$moderatorGroups = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ModeratorGroupID' ) );
					$classIdentifier = $contentObject->contentClassIdentifier();
					if( in_array( $classIdentifier, $classIdentifiers ) )
					{
						$ownerID = $contentObject->attribute( 'owner_id' );
						$userContentObject = eZContentObject::fetch( $ownerID );
						if( $userContentObject instanceof eZContentObject )
						{
							$isModerator = false;
							$userGroups = $userContentObject->assignedNodes();
							foreach( $userGroups as $group )
							{
								$groupID = $group->attribute( 'parent_node_id' );
								if( in_array( $groupID, $moderatorGroups ) )
								{
									$isModerator = true;
									break;
								}
							}
							// se NON è un moderatore, assegna uno stato
							if( $isModerator == false )
							{
								$approvato = eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'StatoDaModerareID' );
								$approvato = (int) $approvato;
								
								if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
								{
									$operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
																					array( 'object_id'     => $objectID,
																						   'state_id_list' => array ( $approvato ) ) );
								}
								else
								{
									eZContentOperationCollection::updateObjectState( $objectID, array( $approvato ) );
								}
								//eZDebug::writeDebug( "Assegnato lo stato Da Moderare", __METHOD__ );
								$mailRecipient = array();
								$robots = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'RobotsUserName' ) );
								// al cambio di stato viene inviata una mail allo user.
								// non inviarla qui
								
								/*
								$user = eZUser::fetch( $ownerID );
								if( $user instanceof eZUser )
								{
									$robots = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'RobotsUserName' ) );
									if( !in_array( $user->Login, $robots ) )
									{
										moderaEventiType::sendMail( $user->Email, $contentObject->attribute( 'id' ) );
									}
								}
								*/
								/*
								 * manda una mail a tutto il gruppo moderatori
								 * avvisali che un evento è in moderazione
								 * non mandare mail ai robots
								 */
								
								$params = array ( 'ClassFilterType' => 'include',
												  'ClassFilterArray' => array( 'user' ) );
								foreach( $moderatorGroups as $group )
								{
									$nodeList = eZContentObjectTreeNode::subTreeByNodeId($params, $group );
									foreach( $nodeList as $node )
									{
										$obj = $node->object();
										$user = eZUser::fetch( $obj->attribute( 'id' ) );
										if( $user instanceof eZUser )
										{
											if( !in_array( $user->Login, $robots ) )
											{
												array_push( $mailRecipient, $user->Email );
											}
										}
									}
								}
								
								array_unique( $mailRecipient );
								foreach( $mailRecipient as $to )
								{
									//eZDebug::writeDebug( "Invio mail a " . $to . " che è nel gruppo moderatori",  "Inviata mail" );
									//moderaEventiType::sendMail( $to, $contentObject->attribute( 'id' ) );
									{
										$contentObjectID = (int) $contentObject->attribute( 'id' );
										$ini = eZINI::instance();
										$tpl = eZTemplate::factory();
										$hostname = eZSys::hostname();
										
										$fallback = $hostname . "/content/dashboard";
										
										$subject = "C'è un nuovo evento in attesa di moderazione";
										$message = "Un nuovo evento è in attesa di moderazione. Clicca qui per accedere alla bacheca di moderazione";
										
										$contentObject = eZContentObject::fetch( $contentObjectID );
								
										$mail = new eZMail();
										$tpl->resetVariables();
								
										// variabili che vuoi a diposizione nel template
										$tpl->setVariable( 'hostname', $hostname );
										$tpl->setVariable( 'node_id', $contentObject->mainNodeID() );
										$tpl->setVariable( 'event_name', $contentObject->Name );
										$tpl->setVariable( 'email_receiver', $to );
								
										// il tuo template
										$templateResult = $tpl->fetch( 'design:mail_modera_eventi.tpl' );
								
										if ( $tpl->hasVariable( 'content_type' ) )
											$mail->setContentType( $tpl->variable( 'content_type' ) );
								
										//mittente
										$emailSender = $ini->variable( 'MailSettings', 'EmailSender' );
										if ( $tpl->hasVariable( 'email_sender' ) )
											$emailSender = $tpl->variable( 'email_sender' );
										else if ( !$emailSender )
											$emailSender = $ini->variable( 'MailSettings', 'AdminEmail' );
									
										//destinatario
										$emailReceiver = $to;
										if ( $tpl->hasVariable( 'email_receiver' ) )
											$emailReceiver = $tpl->variable( 'email_receiver' );
										else if ( !$emailReceiver )
											$emailReceiver = $ini->variable( 'MailSettings', 'AdminEmail' );
										
										// altri destinatori
										$emailCC = array();
										if ( $tpl->hasVariable( 'email_cc' ) )
										{
											$emailCC = explode( ';', $tpl->variable( 'email_cc' ) );    
										}
										
										// oggetto della mail    
										if ( $tpl->hasVariable( 'subject' ) )
											$subject = $tpl->variable( 'subject' );
									
										// invio della mail
										$mail->setSender( $emailSender );
										$mail->setReceiver( $emailReceiver );
										foreach( $emailCC as $email )
										{
											$mail->addCC( $email );
										}
										$mail->setSubject( $subject );
										$mail->setBody( $templateResult );
										$mailResult = eZMailTransport::send( $mail );
									}
								}
							}
							else
							{
								//eZDebug::writeDebug( "Sono un moderatore, quindi non assegno alcuno stato", __METHOD__ );
							}
							
						}
					}
				}
			}
        }

        return eZWorkflowType::STATUS_ACCEPTED;
	}
	
}

eZWorkflowEventType::registerEventType( moderaEventiType::TYPE_ID, 'moderaEventiType' );

?>