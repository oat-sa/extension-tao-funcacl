<?php

namespace oat\funcAcl\scripts\install;

use oat\funcAcl\model\event\AccessRightAddedEvent;
use oat\funcAcl\model\event\AccessRightRemovedEvent;
use oat\oatbox\event\EventManager;
use oat\taoEventLog\model\LoggerService;

class registerEvents extends \common_ext_action_InstallAction
{
    public function __invoke($params)
    {

        /** @var EventManager $eventManager */
        $eventManager = $this->getServiceManager()->get(EventManager::CONFIG_ID);

        $eventManager->attach(AccessRightAddedEvent::class, [LoggerService::class, 'logEvent']);
        $eventManager->attach(AccessRightRemovedEvent::class, [LoggerService::class, 'logEvent']);

        $this->getServiceManager()->register(EventManager::CONFIG_ID, $eventManager);

        return new \common_report_Report(\common_report_Report::TYPE_SUCCESS, 'Logging events has been successfully set up');
    }
}