<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\funcAcl\scripts\update;


use oat\funcAcl\model\event\AccessRightAddedEvent;
use oat\funcAcl\model\event\AccessRightRemovedEvent;
use oat\oatbox\event\EventManager;
use oat\taoEventLog\model\LoggerService;

class Updater extends \common_ext_ExtensionUpdater
{

    /**
     * @param string $initialVersion
     * @return void
     */
    public function update($initialVersion) {
        
        $this->skip('0','2.8.0');

        if ($this->isVersion('2.8.0')) {

            /** @var EventManager $eventManager */
            $eventManager = $this->getServiceManager()->get(EventManager::CONFIG_ID);

            $eventManager->attach(AccessRightAddedEvent::class, [LoggerService::class, 'logEvent']);
            $eventManager->attach(AccessRightRemovedEvent::class, [LoggerService::class, 'logEvent']);

            $this->getServiceManager()->register(EventManager::CONFIG_ID, $eventManager);

            $this->setVersion('2.9.0');
        }

        $this->skip('2.9.0', '2.9.1');


    }
}