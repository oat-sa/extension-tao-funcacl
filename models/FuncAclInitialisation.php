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
 * Copyright (c) 2013 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 */

namespace oat\funcAcl\models;

use common_ext_ExtensionsManager;
use common_Logger;
use core_kernel_classes_Class;
use core_kernel_classes_Property;
use oat\tao\model\TaoOntology;

/**
 * Initialise the FuncAcl Model
 *
 * @access public
 *
 * @author Joel Bout, <joel@taotesting.com>
 *
 * @package tao
 */
class FuncAclInitialisation
{
    public static function run()
    {
        // We get all the management roles and the extension they belong to.
        $managementRoleClass = new core_kernel_classes_Class(TaoOntology::CLASS_URI_MANAGEMENT_ROLE);
        $foundManagementRoles = $managementRoleClass->getInstances(true);
        $managementRolesByExtension = [];

        foreach (common_ext_ExtensionsManager::singleton()->getInstalledExtensions() as $extension) {
            $managementRole = $extension->getManagementRole();

            if (empty($managementRole)) {
                // try to discover it.
                foreach ($foundManagementRoles as $mR) {
                    $moduleURIs = $mR->getPropertyValues(
                        new core_kernel_classes_Property(AccessService::PROPERTY_ACL_GRANTACCESS)
                    );

                    foreach ($moduleURIs as $moduleURI) {
                        $uri = explode('#', $moduleURI);
                        list($type, $extId) = explode('_', $uri[1]);

                        if ($extId == $extension->getId()) {
                            $managementRole = $mR;

                            break 2;
                        }
                    }
                }
            }

            if (!empty($managementRole)) {
                $managementRolesByExtension[$extension->getId()] = $managementRole;
            }
        }

        foreach (common_ext_ExtensionsManager::singleton()->getInstalledExtensions() as $extension) {
            if ($extension->getId() != 'generis') {
                // 2. Grant access to Management Role.
                if (!empty($managementRolesByExtension[$extension->getId()])) {
                    $extAccessService = ExtensionAccessService::singleton();
                    $extAccessService->add(
                        $managementRolesByExtension[$extension->getId()]->getUri(),
                        $extAccessService->makeEMAUri($extension->getId())
                    );
                } else {
                    common_Logger::i('Management Role not found for extension ' . $extension->getId());
                }
            }
        }
    }
}
