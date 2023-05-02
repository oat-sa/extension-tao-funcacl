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
 * Copyright (c) 2013-2021 (original work) Open Assessment Technologies SA;
 */

namespace oat\funcAcl\models;

use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\helpers\MapHelper;
use oat\oatbox\log\logger\AdvancedLogger;
use oat\oatbox\log\logger\extender\ContextExtenderInterface;
use oat\oatbox\service\ConfigurableService;
use oat\oatbox\user\User;
use oat\tao\model\accessControl\AccessControl;
use oat\tao\model\accessControl\func\AccessRule;
use oat\tao\model\accessControl\func\FuncAccessControl;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * @author Joel Bout, <joel@taotesting.com>
 */
class FuncAcl extends ConfigurableService implements FuncAccessControl, AccessControl
{
    /**
     * {@inheritdoc}
     */
    public function accessPossible(User $user, $controller, $action)
    {
        $userRoles = $user->getRoles();

        try {
            $controllerAccess = CacheHelper::getControllerAccess($controller);
            $allowedRoles = isset($controllerAccess['actions'][$action])
                ? array_merge($controllerAccess['module'], $controllerAccess['actions'][$action])
                : $controllerAccess['module'];

            $accessAllowed = !empty(array_intersect($userRoles, $allowedRoles));

            if (!$accessAllowed) {
                $this->getAdvancedLogger()->info(
                    'Access denied.',
                    [
                        'allowedRoles' => $allowedRoles,
                    ]
                );
            }
        } catch (ReflectionException $exception) {
            $this->getAdvancedLogger()->error(
                sprintf('Unknown controller "%s"', $controller),
                [
                    ContextExtenderInterface::CONTEXT_EXCEPTION => $exception,
                ]
            );
            $accessAllowed = false;
        }

        return $accessAllowed;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAccess(User $user, $controllerName, $actionName, $parameters)
    {
        return $this->accessPossible($user, $controllerName, $actionName);
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
                    [$extension, $shortName] = $elements;
                    $accessService->grantModuleAccess($rule->getRole(), $extension, $shortName);

                    break;
                case 3:
                    [$extension, $shortName, $action] = $elements;
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
                    [$extension, $shortName] = $elements;
                    $accessService->revokeModuleAccess($rule->getRole(), $extension, $shortName);

                    break;
                case 3:
                    [$extension, $shortName, $action] = $elements;
                    $accessService->revokeActionAccess($rule->getRole(), $extension, $shortName, $action);

                    break;
                default:
                    // fail silently warning should already be send
            }
        } else {
            $this->getAdvancedLogger()->warning(
                sprintf(
                    'Only grant rules accepted in "%s"',
                    __CLASS__
                )
            );
        }
    }

    /**
     * Evaluate the mask to ACL components
     *
     * @param mixed $mask
     *
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

            $this->getAdvancedLogger()->warning(
                sprintf(
                    'Unknown controller "%s"',
                    $controller
                )
            );
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

            $this->getAdvancedLogger()->warning(
                sprintf(
                    'Uninterpretable filter in "%s"',
                    __CLASS__
                )
            );
        } else {
            $this->getAdvancedLogger()->warning(
                sprintf(
                    'Uninterpretable filter type "%s"',
                    gettype($mask)
                )
            );
        }

        return [];
    }

    private function getAdvancedLogger(): LoggerInterface
    {
        return $this->getServiceManager()->getContainer()->get(AdvancedLogger::ACL_SERVICE_ID);
    }
}
