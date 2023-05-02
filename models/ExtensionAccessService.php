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
 * Copyright (c) 2009-2012 (original work) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 */

namespace oat\funcAcl\models;

use common_ext_ExtensionsManager;
use common_Logger;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\models\event\AccessRightAddedEvent;
use oat\funcAcl\models\event\AccessRightRemovedEvent;

/**
 * access operation for extensions
 *
 * @access public
 *
 * @author Jehan Bihin
 *
 * @package tao
 *
 * @since 2.2
 */
class ExtensionAccessService extends AccessService
{
    // --- ASSOCIATIONS ---

    // --- ATTRIBUTES ---

    // --- OPERATIONS ---

    /**
     * Short description of method add
     *
     * @access public
     *
     * @author Jehan Bihin, <jehan.bihin@tudor.lu>
     *
     * @param string $roleUri
     * @param string $accessUri
     *
     * @return mixed
     */
    public function add($roleUri, $accessUri)
    {
        $uri = explode('#', $accessUri);
        list($type, $extId) = explode('_', $uri[1]);

        $extManager = common_ext_ExtensionsManager::singleton();
        $extension = $extManager->getExtensionById($extId);
        $role = new core_kernel_classes_Resource($roleUri);

        $values = $role->getPropertyValues(new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS));

        if (!in_array($accessUri, $values)) {
            $role->setPropertyValue(new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS), $accessUri);
            $this->getEventManager()->trigger(new AccessRightAddedEvent($roleUri, $accessUri));
            CacheHelper::flushExtensionAccess($extId);
        } else {
            common_Logger::w('Tried to regrant access for role ' . $role->getUri() . ' to extension ' . $accessUri);
        }
    }

    /**
     * Short description of method remove
     *
     * @access public
     *
     * @author Jehan Bihin, <jehan.bihin@tudor.lu>
     *
     * @param string $roleUri
     * @param string $accessUri
     *
     * @return mixed
     */
    public function remove($roleUri, $accessUri)
    {
        $uri = explode('#', $accessUri);
        list($type, $extId) = explode('_', $uri[1]);

        // Remove the access to the extension for this role.
        $extManager = common_ext_ExtensionsManager::singleton();
        $extension = $extManager->getExtensionById($extId);
        $role = new core_kernel_classes_Resource($roleUri);

        $role->removePropertyValues(
            new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS),
            ['pattern' => $accessUri]
        );
        CacheHelper::flushExtensionAccess($extId);

        // also remove access to all the controllers
        $moduleAccessProperty = new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS);
        $moduleAccessService = ModuleAccessService::singleton();
        $grantedModules = $role->getPropertyValues($moduleAccessProperty);

        foreach ($grantedModules as $gM) {
            $gM = new core_kernel_classes_Resource($gM);
            $uri = explode('#', $gM->getUri());
            list($type, $ext) = explode('_', $uri[1]);

            if ($extId == $ext) {
                $moduleAccessService->remove($role->getUri(), $gM->getUri());
                $this->getEventManager()->trigger(new AccessRightRemovedEvent($roleUri, $accessUri));
            }
        }
    }
}
