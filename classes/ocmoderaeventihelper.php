<?php
class OCModeraEventiHelper
{
    protected
        $ini,
        $waitModerationStatus,
        $approvedModerationStatus,
        $currentObject,
        $currentUser,
        $owner,
        $classIdentifiers,
        $moderatorGroups,
        $moderatorsEmailAddress = array(),
        $whiteListGroups,
        $robotLoginNames,
        $alwaysAddLocations = array(),
        $addLocations = array(),
        $moderateOnlyFirstVersion;
    
    public
        $ownerIsModerator = false,
        $ownerIsInWhiteList = false,
        $ownerIsRobot = false;
    
    public function __construct( $contentObject = null )
    {        
        $this->ini = eZINI::instance( 'ocmoderaeventi.ini');
        
        $this->classIdentifiers = (array) $this->ini->variable( 'Events', 'ClassIdentifiers' );
        $this->moderatorGroups = (array) $this->ini->variable( 'Events', 'ModeratorGroupID' );
        $this->whiteListGroups = (array) $this->ini->variable( 'Events', 'WhiteListGroupID' );
        $this->robotLoginNames = (array) $this->ini->variable( 'Events', 'RobotsUserName' );
        
        $this->waitModerationStatus = (int) $this->ini->variable( 'Events', 'StatoDaModerareID' );
        $this->approvedModerationStatus = (int) $this->ini->variable( 'Events', 'StatoPubblicato' );
        
        $this->moderateOnlyFirstVersion = $this->ini->variable( 'Events', 'ModerateOnlyFirstVersion' ) == 'enabled';
        
        if ( $this->ini->hasVariable( 'Locations', 'Always' ) )
        {            
            $this->alwaysAddLocations = (array) $this->ini->variable( 'Locations', 'Always' ); 
        }
        
        if ( $this->ini->hasVariable( 'Locations', 'FromTo' ) )
        {
            $this->addLocations = (array) $this->ini->variable( 'Locations', 'FromTo' );   
        } 
        
        if ( $contentObject instanceof eZContentObject )
        {
            $this->currentObject = $contentObject;
            $this->owner = $this->getCurrentObjectOwner();
        }
    }
    
    public function needWorkflow()
    {
        return in_array( $this->currentObject->attribute( 'class_identifier' ), $this->classIdentifiers );
    }
    
    public function needModeration()
    {        
        if ( !$this->needWorkflow() )
        {
            eZDebug::writeNotice( "L'oggetto di classe {$this->currentObject->attribute( 'class_identifier' )} non rientra nella classi da moderare", __METHOD__ );
            return false;
        }
        if ( $this->currentObject->attribute( 'current_version' ) > 1 )
        {
            if ( $this->moderateOnlyFirstVersion )
            {
                eZDebug::writeNotice( "La versione dell'oggetto è superiore alla 1 e perciò non è da moderare", __METHOD__ );
                return false;
            }            
        }
        if ( $this->ownerIsModerator == true || $this->ownerIsInWhiteList == true )
        {
            eZDebug::writeNotice( "Il proprietario dell'oggetto è un moderatore o rientra nella whitelist: l'oggetto non è da moderare", __METHOD__ );
            return false;
        }
        return true;
    }
    
    public function processNewStates( array $states )
    {
        foreach( $states as $state )
        {
            switch ( $state )
            {
                case $this->waitModerationStatus:
                {
                    if ( !$this->ownerIsModerator && !$this->ownerIsRobot )
                    {
                        $this->sendMailToOwner( $this->waitModerationStatus );
                    }
                }
                break;
                case $this->approvedModerationStatus:
                {
                    if ( !$this->ownerIsModerator && !$this->ownerIsRobot )
                    {
                        $this->sendMailToOwner( $this->approvedModerationStatus );
                    }
                    $this->addLocations();
                }
                break;
            }
        }
    }
    
    public function makeInModerationQueue()
    {
        $this->sudo();
        if ( eZOperationHandler::operationIsAvailable( 'content_updateobjectstate' ) )
        {
            $operationResult = eZOperationHandler::execute( 'content', 'updateobjectstate',
                                                            array( 'object_id'     => $this->currentObject->attribute( 'id' ),
                                                                   'state_id_list' => array ( $this->waitModerationStatus ) ) );
        }
        else
        {
            eZContentOperationCollection::updateObjectState( $this->currentObject->attribute( 'id' ), array( $this->waitModerationStatus ) );
        }
        $this->unsudo();
        $this->sendMailToModerators();        
    }
    
    public function sendMailToOwner( $status )
    {
        if ( $this->owner == null )
        {
            return false;
        }
        
        try
        {
            $ini = eZINI::instance();
            $tpl = eZTemplate::factory();
            $hostname = eZSys::hostname();        
            
            $mail = new eZMail();
            $tpl->resetVariables();
    
            // variabili che vuoi a diposizione nel template
            $tpl->setVariable( 'hostname', $hostname );										
            $tpl->setVariable( 'event', $this->currentObject->attribute( 'main_node' ) );
            
            if ( $status == $this->approvedModerationStatus )
            {
                $tpl->setVariable( 'approvato', true );
                $subject = "L'evento " . $this->currentObject->attribute( 'name' ) . " è stato approvato";
            }
            else
            {
                $tpl->setVariable( 'approvato', false );
                $subject = "L'evento " . $this->currentObject->attribute( 'name' ) . " è in attesa di moderazione";            
            }
    
            $templateResult = $tpl->fetch( 'design:mail/event_update_state.tpl' );
    
            if ( $tpl->hasVariable( 'content_type' ) ) $mail->setContentType( $tpl->variable( 'content_type' ) );
    
            //mittente
            $emailSender = $ini->variable( 'MailSettings', 'EmailSender' );
            if ( $tpl->hasVariable( 'email_sender' ) )
                $emailSender = $tpl->variable( 'email_sender' );
            else if ( !$emailSender )
                $emailSender = $ini->variable( 'MailSettings', 'AdminEmail' );
        
            // altri destinatori
            $emailCC = array();
            if ( $tpl->hasVariable( 'email_cc' ) )
                $emailCC = explode( ';', $tpl->variable( 'email_cc' ) );    
            
            // oggetto della mail    
            if ( $tpl->hasVariable( 'subject' ) )
                $subject = $tpl->variable( 'subject' );
        
            // invio della mail
            $mail->setSender( $emailSender );
            $mail->setReceiver( $this->owner->attribute( 'email' ) );
    
            foreach( $emailCC as $email )
                $mail->addCC( $email );
    
            $mail->setSubject( $subject );
            $mail->setBody( $templateResult );
            $mailResult = eZMailTransport::send( $mail );
            eZDebug::writeNotice( $this->owner->attribute( 'email' ), __METHOD__ );
        }
        catch( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
        }
    }
    
    public function sendMailToModerators()
    {
        $mailAddresses = $this->findModeratorsEmailAddress();
        $firstAddress = array_shift( $mailAddresses );
        try
        {
            $ini = eZINI::instance();
            $tpl = eZTemplate::factory();
            $hostname = eZSys::hostname();
            
            $subject = "C'è un nuovo evento in attesa di moderazione";
            $message = "Un nuovo evento è in attesa di moderazione. Clicca qui per accedere alla bacheca di moderazione";
            
            $mail = new eZMail();
            $tpl->resetVariables();
    
            // variabili che vuoi a diposizione nel template
            $tpl->setVariable( 'hostname', $hostname );										
            $tpl->setVariable( 'event', $this->currentObject->attribute( 'main_node' ) );
            $tpl->setVariable( 'email_receiver', $moderatorEmailAddress );
    
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
            $mail->setReceiver( $firstAddress );
            foreach( $mailAddresses as $email )
            {
                if ( eZMail::validate( $email ) )
                {
                    $mail->addCC( $email );
                }
            }
            foreach( $emailCC as $email )
            {
                if ( eZMail::validate( $email ) )
                {
                    $mail->addCC( $email );
                }
            }
            $mail->setSubject( $subject );
            $mail->setBody( $templateResult );
            $mailResult = eZMailTransport::send( $mail );
            eZDebug::writeNotice( $firstAddress . ', ' . implode( ',', $mailAddresses ), __METHOD__ );
        }
        catch( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
        }
    }
    
    protected function findModeratorsEmailAddress()
    {
        if ( empty( $this->moderatorsEmailAddress ) )
        {
            foreach( $this->moderatorGroups as $groupID )
            {
                $groupObject = eZContentObject::fetch( $groupID );
                if ( $groupObject instanceof eZContentObject )
                {
                    foreach( $groupObject->attribute( 'assigned_nodes' ) as $groupNode )
                    {
                        $userNodeList = $groupNode->subTree( array( 'Limitation' => array() ) );                                            											
                        foreach( $userNodeList as $node )
                        {                                                                                                
                            $user = eZUser::fetch( $node->attribute( 'contentobject_id' ) );
                            if( $user instanceof eZUser )
                            {
                                if( !in_array( $user->Login, $this->robotLoginNames ) )
                                {
                                    $this->moderatorsEmailAddress[] = $user->Email;
                                }
                            }
                        }
                    }                                        
                }
            }
        }
        $this->moderatorsEmailAddress = array_unique( $this->moderatorsEmailAddress );  
        return $this->moderatorsEmailAddress;
    }
    
    protected function getCurrentObjectOwner()
    {
        $ownerID = $this->currentObject->attribute( 'owner_id' );
        $userObject = eZUser::fetch( $ownerID );
        if ( $userObject instanceof eZUser )
        {
            $userGroups = $userObject->groups( false ); 
            $this->ownerIsModerator = count( array_intersect( $this->moderatorGroups, $userGroups ) ) > 0;
            $this->ownerIsInWhiteList = count( array_intersect( $this->whiteListGroups, $userGroups ) ) > 0;
            $this->ownerIsRobot = in_array( $userObject->attribute( 'login' ), $this->robotLoginNames );
            return $userObject;
        }
        return null;
    }
    
    protected function sudo()
    {
        $this->currentUser = eZUser::currentUser();
        $root = eZUser::fetchByName( 'admin' );
        eZUser::setCurrentlyLoggedInUser( $root, $root->attribute( 'contentobject_id' ) );
    }
    
    protected  function unsudo()
    {
        eZUser::setCurrentlyLoggedInUser( $this->currentUser, $this->currentUser->attribute( 'contentobject_id' ) );
    }
    
    public function isApproved()
    {        
        return in_array( $this->approvedModerationStatus, $this->currentObject->attribute( 'state_id_array' ) );
    }
    
    public function addLocations()
    {
        //if ( $this->currentObject->attribute( 'current_version' ) > 1 )
        //{
        //    eZDebug::writeNotice( "La versione dell'oggetto è superiore alla 1, non vengono aggiunte collocazioni", __METHOD__ );
        //    return false;
        //}

        $this->sudo();
        $mainParentNodeID = $this->currentObject->mainParentNodeID();        
        if( !empty( $this->alwaysAddLocations ) )
        {							                            
            $addLocations = array();
            foreach( $this->alwaysAddLocations as $new )
            {
                $nodeExists = eZContentObjectTreeNode::fetch( $new, false );
                $missingLocation = true;
                
                foreach( $assignedNodes as $assignedNode )
                {
                    if ( $assignedNode['parent_node_id'] == $new )
                    {
                        $missingLocation = false;
                        break;
                    }
                }
                
                if ( $nodeExists && $missingLocation )
                {
                    $addLocations[] = $new;
                }
            }
            if ( !empty( $addLocations ) )
            {
                eZContentOperationCollection::addAssignment( $this->currentObject->attribute( 'main_node_id' ),
                                                             $this->currentObject->attribute( 'id' ),
                                                             $addLocations );
            }
        }
        
        $assignedNodes = $this->currentObject->assignedNodes( false );
        foreach( $this->addLocations as $location )
        {
            list( $from, $to ) = explode( ';', $location );							
            if( $from == $mainParentNodeID )
            {
                $newLocations = explode( ',', $to );
                $addLocations = array();
                foreach( $newLocations as $new )
                {
                    $nodeExists = eZContentObjectTreeNode::fetch( $new, false );
                    $missingLocation = true;
                    
                    foreach( $assignedNodes as $assignedNode )
                    {
                        if ( $assignedNode['parent_node_id'] == $new )
                        {
                            $missingLocation = false;
                            break;
                        }
                    }
                    if ( $nodeExists && $missingLocation )
                    {
                        $addLocations[] = $new;
                    }
                }
                if ( !empty( $addLocations ) )
                {
                    eZContentOperationCollection::addAssignment( $this->currentObject->attribute( 'main_node_id' ),
                                                                 $this->currentObject->attribute( 'id' ),
                                                                 array( $addLocations ) );
                }
            }
        }
        $this->unsudo();
    }
}