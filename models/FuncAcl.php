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
 *
 */

namespace oat\funcAcl\models;

use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\helpers\MapHelper;
use oat\tao\model\accessControl\func\FuncAccessControl;
use oat\tao\model\accessControl\func\AccessRule;
use oat\oatbox\user\User;
use oat\oatbox\service\ConfigurableService;

/**
 * Proxy for the Acl Implementation
 *
 * @access public
 * @author Joel Bout, <joel@taotesting.com>
 * @package tao
 */
class FuncAcl extends ConfigurableService implements FuncAccessControl
{
    
    /**
     * (non-PHPdoc)
     * @see \oat\tao\model\accessControl\func\FuncAccessControl::accessPossible()
     */
    public function accessPossible(User $user, $controller, $action)
    {
        $userRoles = $user->getRoles();
        try {
            $controllerAccess = CacheHelper::getControllerAccess($controller);
            $allowedRoles = isset($controllerAccess['actions'][$action])
                ? array_merge($controllerAccess['module'], $controllerAccess['actions'][$action])
                : $controllerAccess['module'];
            
            $accessAllowed = count(array_intersect($userRoles, $allowedRoles)) > 0;
        } catch (\ReflectionException $e) {
            \common_Logger::i('Unknown controller ' . $controller);
            $accessAllowed = false;
        }
        
        return (bool) $accessAllowed;
    }
    
    /**
     * Compatibility class for old implementation
     *
     * @param string $extension
     * @param string $controller
     * @param string $action
     *
     * @return boolean
     * @deprecated
     */
    public function hasAccess($action, $controller, $extension)
    {
        $user = \common_session_SessionManager::getSession()->getUser();
        $uri = ModuleAccessService::singleton()->makeEMAUri($extension, $controller);
        $controllerClassName = MapHelper::getControllerFromUri($uri);

        return $this->accessPossible($user, $controllerClassName, $action);
    }
    
    public function applyRule(AccessRule $rule)
    {
        if ($rule->isGrant()) {
            $accessService = AccessService::singleton();
            $elements = $this->evalFilterMask($rule->getMask());
            
            switch (count($elements)) {
                case 1:
                    $extension = reset($elements);
                    $accessService->grantExtensionAccess($rule->getRole(), $extension);
                    break;
                case 2:
                    list($extension, $shortName) = $elements;
                    $accessService->grantModuleAccess($rule->getRole(), $extension, $shortName);
                    break;
                case 3:
                    list($extension, $shortName, $action) = $elements;
                    $accessService->grantActionAccess($rule->getRole(), $extension, $shortName, $action);
                    break;
                default:
                    // fail silently warning should already be send
            }
        } else {
            $this->revokeRule(
                new AccessRule(
                    AccessRule::GRANT,
                    $rule->getRole(),
                    $rule->getMask()
                )
            );
        }
    }
    
    public function revokeRule(AccessRule $rule)
    {
        if ($rule->isGrant()) {
            $accessService = AccessService::singleton();
            $elements = $this->evalFilterMask($rule->getMask());
            
            switch (count($elements)) {
                case 1:
                    $extension = reset($elements);
                    $accessService->revokeExtensionAccess($rule->getRole(), $extension);
                    break;
                case 2:
                    list($extension, $shortName) = $elements;
                    $accessService->revokeModuleAccess($rule->getRole(), $extension, $shortName);
                    break;
                case 3:
                    list($extension, $shortName, $action) = $elements;
                    $accessService->revokeActionAccess($rule->getRole(), $extension, $shortName, $action);
                    break;
                default:
                    // fail silently warning should already be send
            }
        } else {
            \common_Logger::w('Only grant rules accepted in ' . __CLASS__);
        }
    }
    
    /**
     * Evaluate the mask to ACL components
     *
     * @param mixed $mask
     * @return string[] tao ACL components
     */
    public function evalFilterMask($mask)
    {
        // string masks
        if (is_string($mask)) {
            if (strpos($mask, '@') !== false) {
                [$controller, $action] = explode('@', $mask, 2);
            } else {
                $controller = $mask;
                $action = null;
            }
            if (class_exists($controller)) {
                $extension = MapHelper::getExtensionFromController($controller);
                $shortName = strpos($controller, '\\') !== false
                    ? substr($controller, strrpos($controller, '\\') + 1)
                    : substr($controller, strrpos($controller, '_') + 1);
        
                if (is_null($action)) {
                    // grant controller
                    return [$extension, $shortName];
                }

                // grant action
                return [$extension, $shortName, $action];
            }

            \common_Logger::w('Unknown controller ' . $controller);
        } elseif (is_array($mask)) { /// array masks
            if (isset($mask['act'], $mask['mod'], $mask['ext'])) {
                return [$mask['ext'], $mask['mod'], $mask['act']];
            }

            if (isset($mask['mod'], $mask['ext'])) {
                return [$mask['ext'], $mask['mod']];
            }

            if (isset($mask['ext'])) {
                return [$mask['ext']];
            }

            if (isset($mask['controller'])) {
                $extension = MapHelper::getExtensionFromController($mask['controller']);
                $shortName = strpos($mask['controller'], '\\') !== false
                    ? substr($mask['controller'], strrpos($mask['controller'], '\\') + 1)
                    : substr($mask['controller'], strrpos($mask['controller'], '_') + 1);

                return [$extension, $shortName];
            }

            if (isset($mask['act']) && strpos($mask['act'], '@') !== false) {
                [$controller, $action] = explode('@', $mask['act'], 2);
                $extension = MapHelper::getExtensionFromController($controller);
                $shortName = strpos($controller, '\\') !== false
                    ? substr($controller, strrpos($controller, '\\') + 1)
                    : substr($controller, strrpos($controller, '_') + 1);

                return [$extension, $shortName, $action];
            }

            \common_Logger::w('Uninterpretable filter in ' . __CLASS__);
        } else {
            \common_Logger::w('Uninterpretable filtertype ' . gettype($mask));
        }

        return [];
    }
}
