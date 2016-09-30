<?php

class moderaEventiType extends eZWorkflowEventType
{
    const TYPE_ID = 'moderaeventi';
    private $currentUser;

    public function __construct()
    {
        $this->eZWorkflowEventType(self::TYPE_ID, 'Modera Eventi');
    }

    public function execute($process, $event)
    {
        $processParams = $process->attribute('parameter_list');
        $trigger = $processParams['trigger_name'];
        if ($trigger == 'post_publish') {
            $objectID = $processParams['object_id'];
            $contentObject = eZContentObject::fetch($objectID);
            if ($contentObject instanceof eZContentObject) {
                $helper = new OCModeraEventiHelper($contentObject);
                if ($helper->needWorkflow()) {
                    if ($helper->needModeration()) {
                        $helper->makeInModerationQueue();
                    } else {
                        $helper->addLocations();
                    }
                }
            }
        }

        return eZWorkflowType::STATUS_ACCEPTED;
    }
}

eZWorkflowEventType::registerEventType(moderaEventiType::TYPE_ID, 'moderaEventiType');
