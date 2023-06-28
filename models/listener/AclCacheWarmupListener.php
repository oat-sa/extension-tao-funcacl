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
 * Copyright (c) 2023 (original work) Open Assessment Technologies SA;
 *
 */

declare(strict_types=1);

namespace oat\funcAcl\models\listener;

use oat\funcAcl\helpers\CacheHelper;
use oat\generis\model\data\event\CacheWarmupEvent;
use oat\oatbox\reporting\Report;
use oat\tao\helpers\ControllerHelper;

/**
 * @codeCoverageIgnore Ignore because it uses static method which can't be easily tested
 */
class AclCacheWarmupListener
{
    private \common_ext_ExtensionsManager $extensionsManager;

    public function __construct(
        \common_ext_ExtensionsManager $extensionsManager
    ) {
        $this->extensionsManager = $extensionsManager;
    }

    public function handleEvent(CacheWarmupEvent $event): void
    {
        foreach ($this->extensionsManager->getInstalledExtensionsIds() as $extId) {
            if ('generis' === $extId) {
                continue;
            }

            CacheHelper::getExtensionAccess($extId);
            foreach (ControllerHelper::getControllers($extId) as $controllerClassName) {
                CacheHelper::getControllerAccess($controllerClassName);
                foreach (ControllerHelper::getActions($controllerClassName) as $actionName) {
                    ControllerHelper::getRequiredRights($controllerClassName, $actionName);
                }
            }
        }
        $event->addReport(Report::createInfo('Generated ACL cache.'));
    }
}
