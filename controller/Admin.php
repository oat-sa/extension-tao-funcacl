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
 * Copyright (c) 2002-2008 (original work) Public Research Centre Henri Tudor & University of Luxembourg (under the project TAO & TAO2);
 *               2008-2010 (update and modification) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *               2012-2018 (update and modification) Open Assessment Technologies SA;
 *
 */

namespace oat\funcAcl\controller;

use oat\generis\model\GenerisRdf;
use oat\tao\helpers\ControllerHelper;
use oat\funcAcl\models\AccessService;
use oat\funcAcl\models\ActionAccessService;
use oat\funcAcl\models\ExtensionAccessService;
use oat\funcAcl\models\ModuleAccessService;
use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\helpers\MapHelper;
use common_exception_BadRequest;
use oat\tao\model\service\ApplicationService;

/**
 * This controller provide the actions to manage the ACLs
 *
 * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
 * @license GPLv2  http://www.opensource.org/licenses/gpl-2.0.php
 * @package tao
 *
 */
class Admin extends \tao_actions_CommonModule {

    /**
     * Access to this functionality is inherited from
     * an included role
     *
     * @var string
     */
    const ACCESS_INHERITED = 'inherited';

    /**
     * Full access to this functionalities and children
     *
     * @var string
     */
    const ACCESS_FULL = 'full';

    /**
     * Partial access to thie functionality means
     * some children are at least partial accessible
     *
     * @var string
     */
    const ACCESS_PARTIAL = 'partial';

    /**
     * No access to this functionality or any of its children
     *
     * @var string
     */
    const ACCESS_NONE = 'none';

    /**
     * Show the list of roles
     * @return void
     */
    public function index()
    {
        $this->defaultData();
        $rolesc = new \core_kernel_classes_Class(GenerisRdf::CLASS_ROLE);
        $roles = array();
        foreach ($rolesc->getInstances(true) as $id => $r) {
            $roles[] = array('id' => $id, 'label' => $r->getLabel());
        }
        usort($roles, function($a, $b) {
        	return strcmp($a['label'],$b['label']);
        });

        $this->setData('roles', $roles);
        $this->setView('list.tpl');
    }

    /**
     * @throws \common_exception_Error
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function getModules()
    {
        $this->beforeAction();
        $role = new \core_kernel_classes_Class($this->getRequestParameter('role'));

        $included = array();
        foreach (\tao_models_classes_RoleService::singleton()->getIncludedRoles($role) as $includedRole) {
            $included[$includedRole->getUri()] = $includedRole->getLabel();
        }

        $extManager = \common_ext_ExtensionsManager::singleton();

        $extData = array();
        foreach ($extManager->getInstalledExtensions() as $extension){
            if ($extension->getId() != 'generis') {
                $extData[] = $this->buildExtensionData($extension, $role->getUri(), array_keys($included));
            }
        }

        usort($extData, function($a, $b) {
            return strcmp($a['label'],$b['label']);
        });


        $this->returnJson(array(
            'extensions' => $extData,
            'includedRoles' => $included,
            'locked' => $this->isLocked(),
        ));
    }

    protected function buildExtensionData(\common_ext_Extension $extension, $roleUri, $includedRoleUris) {
        $extAccess = CacheHelper::getExtensionAccess($extension->getId());
        $extAclUri = AccessService::singleton()->makeEMAUri($extension->getId());
        $atLeastOneAccess = false;
        $allAccess = in_array($roleUri, $extAccess);
        $inherited = count(array_intersect($includedRoleUris, $extAccess)) > 0;

        $controllers = array();
        foreach (ControllerHelper::getControllers($extension->getId()) as $controllerClassName) {
            $controllerData = $this->buildControllerData($controllerClassName, $roleUri, $includedRoleUris);
            $atLeastOneAccess = $atLeastOneAccess || $controllerData['access'] != self::ACCESS_NONE;
            $controllers[] = $controllerData;
        }

        usort($controllers, function($a, $b) {
        	return strcmp($a['label'],$b['label']);
        });

        $access = $inherited ? 'inherited'
            : ($allAccess ? 'full'
                : ($atLeastOneAccess ? 'partial' : 'none'));

        return array(
            'uri' => $extAclUri,
            'label' => $extension->getName(),
            'access' => $access,
            'modules' => $controllers
        );

    }

    protected function buildControllerData($controllerClassName, $roleUri, $includedRoleUris) {

        $modUri = MapHelper::getUriForController($controllerClassName);

        $moduleAccess = CacheHelper::getControllerAccess($controllerClassName);
        $uri = explode('#', $modUri);
        list($type, $extId, $modId) = explode('_', $uri[1]);

        $access = self::ACCESS_NONE;
        if (count(array_intersect($includedRoleUris, $moduleAccess['module'])) > 0) {
            $access = self::ACCESS_INHERITED;
        } elseif (true === in_array($roleUri, $moduleAccess['module'])){
            $access = self::ACCESS_FULL;
        } else {
            // have a look at actions.
            foreach ($moduleAccess['actions'] as $roles) {
                if (in_array($roleUri, $roles) || count(array_intersect($includedRoleUris, $roles)) > 0){
                    $access = self::ACCESS_PARTIAL;
                    break;
                }
            }
        }

        return array(
            'uri' => $modUri,
            'label' => $modId,
            'access' => $access,
        );
    }

    /**
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    private function beforeAction()
    {
        $this->defaultData();
        if (!\tao_helpers_Request::isAjax()) {
            throw new common_exception_BadRequest('wrong request mode');
        }
    }

    /**
     * @return bool
     */
    private function isLocked()
    {
        return !$this->getServiceLocator()->get(ApplicationService::SERVICE_ID)->isDebugMode();
    }

    /**
     * @throws \common_exception_NotFound
     */
    private function prodLocker()
    {
        if ($this->isLocked()) {
            throw new \common_exception_NotFound();
        }
    }

    /**
     * Shows the access to the actions of a controller for a specific role
     *
     * @throws \common_exception_Error
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function getActions()
    {
        $this->beforeAction();
        $role = new \core_kernel_classes_Resource($this->getRequestParameter('role'));
        $included = array();
        foreach (\tao_models_classes_RoleService::singleton()->getIncludedRoles($role) as $includedRole) {
            $included[] = $includedRole->getUri();
        }
        $module = new \core_kernel_classes_Resource($this->getRequestParameter('module'));

        $controllerClassName = MapHelper::getControllerFromUri($module->getUri());
        $controllerAccess = CacheHelper::getControllerAccess($controllerClassName);

        $actions = array();
        foreach (ControllerHelper::getActions($controllerClassName) as $actionName) {
            $uri = MapHelper::getUriForAction($controllerClassName, $actionName);
            $part = explode('#', $uri);
            list($type, $extId, $modId, $actId) = explode('_', $part[1]);

            $allowedRoles = isset($controllerAccess['actions'][$actionName])
                ? array_merge($controllerAccess['module'], $controllerAccess['actions'][$actionName])
                : $controllerAccess['module'];

            $access = count(array_intersect($included, $allowedRoles)) > 0
                ? self::ACCESS_INHERITED
                : (in_array($role->getUri(), $allowedRoles)
                    ? self::ACCESS_FULL
                    : self::ACCESS_NONE);

            $actions[$actId] = array(
                'uri' => $uri,
                'access' => $access,
                'locked' => $this->isLocked(),
            );
        }

        ksort($actions);

        $this->returnJson($actions);
    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function removeExtensionAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $extensionService = ExtensionAccessService::singleton();
        $extensionService->remove($role, $uri);
        echo json_encode(array('uri' => $uri));
    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function addExtensionAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $extensionService = ExtensionAccessService::singleton();
        $extensionService->add($role, $uri);
        echo json_encode(array('uri' => $uri));
    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function removeModuleAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $moduleService = ModuleAccessService::singleton();
        $moduleService->remove($role, $uri);
        echo json_encode(array('uri' => $uri));

    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function addModuleAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $moduleService = ModuleAccessService::singleton();
        $moduleService->add($role, $uri);
        echo json_encode(array('uri' => $uri));
    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function removeActionAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $actionService = ActionAccessService::singleton();
        $actionService->remove($role, $uri);
        echo json_encode(array('uri' => $uri));
    }

    /**
     * @throws \common_exception_NotFound
     * @throws \common_ext_ExtensionException
     * @throws common_exception_BadRequest
     */
    public function addActionAccess()
    {
        $this->beforeAction();
        $this->prodLocker();
        $role = $this->getRequestParameter('role');
        $uri = $this->getRequestParameter('uri');
        $actionService = ActionAccessService::singleton();
        $actionService->add($role, $uri);
        echo json_encode(array('uri' => $uri));
    }

}