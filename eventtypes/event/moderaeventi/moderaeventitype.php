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
				// considero solo la prima versione
                if( $contentObject->attribute( 'current_version' ) == 1 )
				{
					$classIdentifiers = (array) eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ClassIdentifiers' );
					$moderatorGroups = (array) eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ModeratorGroupID' );
					$whiteListGroups = (array) eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'WhiteListGroupID' );
					$classIdentifier = $contentObject->contentClassIdentifier();
                    
                    // considero solo le classi degli ini
					if( in_array( $classIdentifier, $classIdentifiers ) )
					{
						$ownerID = $contentObject->attribute( 'owner_id' );
                        $userObject = eZUser::fetch( $ownerID );						
						if( $userObject instanceof eZUser )
						{
							$isModerator = false;
                            $inWhiteList = false;
							$userGroups = $userObject->groups( false );
							foreach( $userGroups as $group )
							{								
								if( in_array( $group, $moderatorGroups ) )
								{
									$isModerator = true;
									break;
								}
							}
                            
                            foreach( $userGroups as $group )
							{								
								if( in_array( $group, $whiteListGroups ) )
								{
									$inWhiteList = true;
									break;
								}
							}
                            
                            
							// se NON è un moderatore o NON è in whitelist, assegna lo stato da approvare
							if( $isModerator == false && $inWhiteList == false )
							{
								$daModerare = (int) eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'StatoDaModerareID' );								
								
								if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
								{
									$operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
																					array( 'object_id'     => $objectID,
																						   'state_id_list' => array ( $daModerare ) ) );
								}
								else
								{
									eZContentOperationCollection::updateObjectState( $objectID, array( $daModerare ) );
								}
								//eZDebug::writeDebug( "Assegnato lo stato Da Moderare", __METHOD__ );
								
                                $mailRecipient = array();
								$robots = (array) eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'RobotsUserName' );
								
                                // al cambio di stato viene inviata una mail a tutti i moderatori								
								foreach( $moderatorGroups as $group )
								{
									$groupObject = eZContentObject::fetch( $group );
                                    if ( $groupObject instanceof eZContentObject )
                                    {
                                        foreach( $groupObject->attribute( 'assigned_nodes' ) as $userNode )
                                        {
                                            $userNodeList = $userNode->attribute( 'subtree' );
                                            foreach( $userNodeList as $node )
                                            {                                                                                                
                                                $user = eZUser::fetch( $node->attribute( 'contentobject_id' ) );
                                                if( $user instanceof eZUser )
                                                {
                                                    if( !in_array( $user->Login, $robots ) )
                                                    {
                                                        $mailRecipient[] = $user->Email;
                                                    }
                                                }
                                            }
                                        }
                                    }
								}
								
								array_unique( $mailRecipient );
								foreach( $mailRecipient as $to )
								{
									//eZDebug::writeDebug( "Invio mail a " . $to . " che è nel gruppo moderatori",  "Inviata mail" );
									//moderaEventiType::sendMail( $to, $contentObject->attribute( 'id' ) );
									
                                    // invio mail ai moderatori
                                    if ( eZMail::validate( $to ) )
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
										$tpl->setVariable( 'event', $contentObject->attribute( 'main_node' ) );
										$tpl->setVariable( 'email_receiver', $to );
								
										// il tuo template
										$templateResult = $tpl->fetch( 'design:mail/modera_eventi.tpl' );
								
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