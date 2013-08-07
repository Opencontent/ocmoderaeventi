<?php

class updateObjectStateType extends eZWorkflowEventType {

	const TYPE_ID = 'updateobjectstate';

	public function __construct()
    {
		$this->eZWorkflowEventType( self::TYPE_ID, 'Update Object State' );
	}

	public function execute( $process, $event )
    {
		$processParams = $process->attribute( 'parameter_list' );
		//eZDebug::writeNotice( var_export( $processParams, 1 ), "PROCESS PARAMS" );
		$trigger = $processParams['trigger_name'];;
		if( $trigger == 'post_updateobjectstate' )
		{
			$objectID = $processParams['object_id'];
			$contentObject = eZContentObject::fetch( $objectID );
			if( $contentObject instanceof eZContentObject )
			{
				$classIdentifiers = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ClassIdentifiers' ) );
				$classIdentifier = $contentObject->contentClassIdentifier();
				if( in_array( $classIdentifier, $classIdentifiers ) )
				{
					$target = eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'StatoPubblicato' );
					$daModerare = eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'StatoDaModerareID' );
					$aNewState = $processParams['state_id_list']; // array
					$aNewState = $aNewState[0];
					
					$moderatorGroups = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'ModeratorGroupID' ) );
					$creator = $contentObject->attribute( 'owner_id' );
					// stabilisci se è un moderatore: ti servirà più volte questa informazione
					$userContentObject = eZContentObject::fetch( $creator );
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
					}
					
					// ora verifica lo Stato dell'evento: è da moderare o è stato moderato
					if( $aNewState == $daModerare ) // un evento messo in "da moderare". Avvisa il creatore.
					{
						/*
						 * se NON è un moderatore, verifica che non sia un robot
						 * e se non è un robot invia una mail che indica il cambio di stato
						 */
						if( $isModerator == false )
						{
							$user = eZUser::fetch( $creator );
							if( $user instanceof eZUser )
							{
								$robots = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'RobotsUserName' ) );
								if( !in_array( $user->Login, $robots ) )
								{
									//updateObjectStateType::sendMail( $user->Email, $contentObject->attribute( 'id' ), 'da_moderare' );
									{
										$to = $user->Email;
										$type = 'da_moderare';
										$contentObjectID = (int) $contentObject->attribute( 'id' );
										$ini = eZINI::instance();
										$tpl = eZTemplate::factory();
										$hostname = eZSys::hostname();
										
										$fallback = $hostname . "/content/dashboard";
										
										$contentObject = eZContentObject::fetch( $contentObjectID );
								
										$mail = new eZMail();
										$tpl->resetVariables();
								
										// variabili che vuoi a diposizione nel template
										$tpl->setVariable( 'hostname', $hostname );
										$tpl->setVariable( 'node_id', $contentObject->mainNodeID() );
										$tpl->setVariable( 'event_name', $contentObject->Name );
										$tpl->setVariable( 'email_receiver', $to );
										
										if( $type == 'approvato' )
										{
											$tpl->setVariable( 'approvato', true );
											$subject = "L'evento " . $contentObject->Name . " è stato approvato";
										}
										else
										{
											$subject = "L'evento " . $contentObject->Name . " è in attesa di moderazione";
											$tpl->setVariable( 'approvato', false );
										}
								
										$templateResult = $tpl->fetch( 'design:mail_event_update_state.tpl' );
								
										if ( $tpl->hasVariable( 'content_type' ) )
											$mail->setContentType( $tpl->variable( 'content_type' ) );
								
										//mittente
										$emailSender = $ini->variable( 'MailSettings', 'EmailSender' );
										if ( $tpl->hasVariable( 'email_sender' ) )
											$emailSender = $tpl->variable( 'email_sender' );
										else if ( !$emailSender )
											$emailSender = $ini->variable( 'MailSettings', 'AdminEmail' );
									
										//destinatario
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
					// altrimenti è un evento da moderato a pubblicato? e da chi è stato pubblicato?
					elseif( in_array( $target, $processParams['state_id_list'] ) )
					{
						/*
						 * 1) notifica l'autore dell'evento
						 * (se non è moderatore né un robot)
						 * che il suo oggetto è stato pubblicato
						 */
						if( $isModerator == false )
						{
							$creator = $contentObject->attribute( 'owner_id' );
							$user = eZUser::fetch( $creator );
							if( $user instanceof eZUser )
							{
								$robots = explode( ',', eZINI::instance( 'ocmoderaeventi.ini')->variable( 'Events', 'RobotsUserName' ) );
								if(  !in_array( $user->Login, $robots ) )
								{
									//updateObjectStateType::sendMail( $user->Email, $contentObject->attribute( 'id' ), 'approvato' );
									{
										$to = $user->Email;
										$type = 'approvato';
										$contentObjectID = (int) $contentObject->attribute( 'id' );
										$ini = eZINI::instance();
										$tpl = eZTemplate::factory();
										$hostname = eZSys::hostname();
										
										$fallback = $hostname . "/content/dashboard";
										
										$contentObject = eZContentObject::fetch( $contentObjectID );
								
										$mail = new eZMail();
										$tpl->resetVariables();
								
										// variabili che vuoi a diposizione nel template
										$tpl->setVariable( 'hostname', $hostname );
										$tpl->setVariable( 'node_id', $contentObject->mainNodeID() );
										$tpl->setVariable( 'event_name', $contentObject->Name );
										$tpl->setVariable( 'email_receiver', $to );
										
										if( $type == 'approvato' )
										{
											$tpl->setVariable( 'approvato', true );
											$subject = "L'evento " . $contentObject->Name . " è stato approvato";
										}
										else
										{
											$subject = "L'evento " . $contentObject->Name . " è in attesa di moderazione";
											$tpl->setVariable( 'approvato', false );
										}
								
										$templateResult = $tpl->fetch( 'design:mail_event_update_state.tpl' );
								
										if ( $tpl->hasVariable( 'content_type' ) )
											$mail->setContentType( $tpl->variable( 'content_type' ) );
								
										//mittente
										$emailSender = $ini->variable( 'MailSettings', 'EmailSender' );
										if ( $tpl->hasVariable( 'email_sender' ) )
											$emailSender = $tpl->variable( 'email_sender' );
										else if ( !$emailSender )
											$emailSender = $ini->variable( 'MailSettings', 'AdminEmail' );
									
										//destinatario
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
						
						// 2) Colloca l'evento in tutti i calendari previsiti
						// considera solo il main node ID
						$mainParentNodeID = $contentObject->mainParentNodeID();
						$alwaysAddLocations = explode(',', eZINI::instance( 'ocmoderaeventi.ini' )->variable( 'Calendars', 'Always' ) );
						foreach( $alwaysAddLocations as $collocation )
						{
							// richiedi ogni volta la lista dei nodi: evita così i duplicati
							// anche se è poco performante
							$nodeList = $contentObject->parentNodeIDArray();
							if( !in_array( $collocation, $nodeList ) )
							{
								$contentObject->addLocation( $collocation );
								//eZDebug::writeNotice( "Pubblico anche in $collocation ", "ALWAYS" );
							}
						}
						
						$locations = eZINI::instance( 'ocmoderaeventi.ini' )->variable( 'Calendars', 'FromTo' );
						
						foreach( $locations as $location )
						{
							list( $from, $to ) = explode( ';', $location );
							//if( in_array( $from, $nodeList ) )
							if( $from == $mainParentNodeID )
							{
								$newLocations = explode( ',', $to );
								foreach( $newLocations as $parentNodeID )
								{
									// richiedi ogni volta la lista dei nodi: evita così i duplicati
									// anche e poco performante
									$nodeList = $contentObject->parentNodeIDArray();
									if( !in_array( $parentNodeID, $nodeList ) ) // se è già collocato anche qui, non duplicare la collocazione
									{
										//eZDebug::writeNotice( "Pubblico anche in $parentNodeID ", "NODI" );
										$contentObject->addLocation( $parentNodeID );
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

eZWorkflowEventType::registerEventType( updateObjectStateType::TYPE_ID, 'updateObjectStateType' );

?>