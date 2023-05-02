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

use common_Logger;
use core_kernel_classes_Class;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\helpers\ModelHelper;
use oat\funcAcl\models\event\AccessRightAddedEvent;
use oat\funcAcl\models\event\AccessRightRemovedEvent;

/**
 * access operation for modules
 *
 * @access public
 *
 * @author Jehan Bihin
 *
 * @package tao
 *
 * @since 2.2
 */
class ModuleAccessService extends AccessService
{
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
        $module = new core_kernel_classes_Resource($accessUri);
        $role = new core_kernel_classes_Resource($roleUri);
        $moduleAccessProperty = new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS);

        $values = $role->getPropertyValues($moduleAccessProperty);

        if (!in_array($module->getUri(), $values)) {
            $role->setPropertyValue($moduleAccessProperty, $module->getUri());
            $this->getEventManager()->trigger(new AccessRightAddedEvent($roleUri, $accessUri));
            CacheHelper::cacheModule($module);
        } else {
            common_Logger::w('Tried to add role ' . $role->getUri() . ' again to controller ' . $accessUri);
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
        $module = new core_kernel_classes_Resource($accessUri);
        $role = new core_kernel_classes_Class($roleUri);
        $accessProperty = new core_kernel_classes_Property(static::PROPERTY_ACL_GRANTACCESS);

        // Retrieve the module ID.
        $uri = explode('#', $module->getUri());
        list($type, $extId, $modId) = explode('_', $uri[1]);

        // access via extension?
        $extAccess = CacheHelper::getExtensionAccess($extId);

        if (in_array($roleUri, $extAccess)) {
            // remove access to extension
            $extUri = $this->makeEMAUri($extId);
            ExtensionAccessService::singleton()->remove($roleUri, $extUri);

            // add access to all other controllers
            foreach (ModelHelper::getModules($extId) as $eModule) {
                if (!$module->equals($eModule)) {
                    $this->add($roleUri, $eModule->getUri());
                    $this->getEventManager()->trigger(new AccessRightRemovedEvent($roleUri, $eModule->getUri()));
                    //$role->setPropertyValue($accessProperty, $eModule->getUri());
                }
            }
            //CacheHelper::flushExtensionAccess($extId);
        }

        // Remove the access to the module for this role.
        $role->removePropertyValue($accessProperty, $module->getUri());

        $this->getEventManager()->trigger(new AccessRightRemovedEvent($roleUri, $accessUri));

        CacheHelper::cacheModule($module);

        // Remove the access to the actions corresponding to the module for this role.
        foreach (ModelHelper::getActions($module) as $actionResource) {
            ActionAccessService::singleton()->remove($role->getUri(), $actionResource->getUri());
        }

        CacheHelper::cacheModule($module);
    }
}
