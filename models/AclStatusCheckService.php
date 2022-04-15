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
 * Copyright (c) 2022 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\funcAcl\models;

use core_kernel_classes_Resource;
use core_kernel_users_Service;
use InvalidArgumentException;
use oat\oatbox\service\ServiceManager;
use oat\oatbox\user\User;
use oat\oatbox\user\UserService;
use oat\funcAcl\helpers\CacheHelper;
use oat\oatbox\service\ConfigurableService;
use oat\tao\helpers\ControllerHelper;
use oat\tao\model\accessControl\ActionAccessControl;
use oat\tao\model\accessControl\data\DataAccessControl;
use oat\tao\model\accessControl\filter\ParameterFilterProxy;

/**
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 *
 * @TODO Migrate to DI container
 */
class AclStatusCheckService extends ConfigurableService
{
    /** @var UserService */
    private $userService;

    /** @var core_kernel_users_Service|null */
    private $usersService;

    /** @var ActionAccessControl */
    private $actionAccessControl;

    /** @var ParameterFilterProxy */
    private $parameterFilter;

    /** @var DataAccessControl */
    private $dataAccessControl;

    /** @var string[] */
    private $overloadedControllerAccess;

    /** @var string[] */
    private $overloadedActionRights;

    public function __construct($options = [])
    {
        parent::__construct($options);

        /**
         * @TODO Use it via DI container after validate the PoC...
         */
        $this->userService = ServiceManager::getServiceManager()->get(UserService::SERVICE_ID);
        $this->usersService = core_kernel_users_Service::singleton();
        $this->actionAccessControl = ServiceManager::getServiceManager()->get(ActionAccessControl::SERVICE_ID);
        $this->parameterFilter = new ParameterFilterProxy();
        $this->dataAccessControl = new DataAccessControl();
    }

    public function overloadControllerAccess(array $controllerAccess): self
    {
        $this->overloadedControllerAccess = $controllerAccess;

        return $this;
    }

    public function overloadActionRights(array $actionRights): self
    {
        $this->overloadedActionRights = $actionRights;

        return $this;
    }

    public function check(array $filter): array
    {
        if (!isset($filter['userId'], $filter['controller'], $filter['action'])) {
            throw new InvalidArgumentException('Missing required parameters');
        }

        $user = $this->userService->getUser($filter['userId']);
        $controller = $filter['controller'];
        $action = $filter['action'];
        $requestParameters = $filter['requestParameters'] ?? [];
        $userRoles = $this->getUserRoles($user);

        return [
            'controller' => $controller,
            'action' => $action,
            'user' => [
                'id' => $user->getIdentifier(),
                'class' => get_class($user),
                'roles' => $userRoles,
            ],
            'funcAclPermissions' => $this->getFunAclPermissions($controller, $action, $userRoles),
            'taoActionAccessPermissions' => $this->getActionAccessPermissions($controller, $action, $user),
            'resourcePermissions' => $this->getResourcesPermission($user, $controller, $action, $requestParameters),
        ];
    }

    private function getFunAclPermissions(string $controller, string $action, array $userRoles): array
    {
        $controllerAccess = $this->getControllerAccess($controller);

        $allowedRoles = isset($controllerAccess['actions'][$action])
            ? array_merge($controllerAccess['module'], $controllerAccess['actions'][$action])
            : (array)$controllerAccess['module'];

        $accessAllowed = !empty(array_intersect($userRoles, $allowedRoles));
        $controllerAllowedRoles = array_values((array)$controllerAccess['module']);
        $actionAllowedRoles = array_values($controllerAccess['actions'][$action] ?? []);

        return [
            'accessAllowed' => $accessAllowed,
            'controllerAllowedRoles' => empty($controllerAllowedRoles) ? null : $controllerAllowedRoles,
            'actionAllowedRoles' => empty($actionAllowedRoles) ? null : $actionAllowedRoles,
        ];
    }

    private function getActionAccessPermissions(string $controller, string $action, User $user): array
    {
        $permissionsOptions = $this->actionAccessControl->getOption(ActionAccessControl::OPTION_PERMISSIONS, []);
        $permissions = $permissionsOptions[$controller][$action] ?? [];

        return [
            'accessAllowed' => [
                ActionAccessControl::READ => $this->actionAccessControl->hasReadAccess($controller, $action, $user),
                ActionAccessControl::WRITE => $this->actionAccessControl->hasWriteAccess($controller, $action, $user),
                ActionAccessControl::GRANT => $this->actionAccessControl->hasGrantAccess($controller, $action, $user),
            ],
            'allowedPermissions' => empty($permissions) ? null : $permissions,
        ];
    }

    private function getResourcesPermission(User $user, string $controller, string $action, array $request): array
    {
        $required = [];
        $requiredRights = $this->getActionRights($controller, $action);
        $uris = $this->parameterFilter->filter($request, array_keys($requiredRights));

        foreach ($uris as $name => $urisValue) {
            $required[] = array_fill_keys($urisValue, $requiredRights[$name]);
        }

        return [
            'requiredRights' => empty($requiredRights) ? null : $requiredRights,
            'allowedAccess' => $this->dataAccessControl->hasPrivileges(
                $user,
                empty($required) ? $required : array_merge(...$required)
            ),
        ];
    }

    private function getUserRoles(User $user): array
    {
        $allRoles = [];

        foreach ($user->getRoles() as $role) {
            $allRoles[$role] = $role;

            /**
             * This is necessary for customer users that use
             * a different "oat\oatbox\user\UserService" implementation
             */
            foreach ($this->usersService->getIncludedRoles(new core_kernel_classes_Resource($role)) as $subRole) {
                $allRoles[$subRole->getUri()] = $subRole->getUri();
            }
        }

        return array_values($allRoles);
    }

    public function getActionRights(string $controller, string $action): array
    {
        return $this->overloadedActionRights ?? ControllerHelper::getRequiredRights($controller, $action);
    }

    private function getControllerAccess(string $controller): array
    {
        return $this->overloadedControllerAccess ?? CacheHelper::getControllerAccess($controller);
    }
}
