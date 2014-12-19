<?php
use oat\tao\helpers\ControllerHelper;
/*  
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
 * 
 */
?>
<?php
/**
 * This controller provide the actions to manage the ACLs
 *
 * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
 * @license GPLv2  http://www.opensource.org/licenses/gpl-2.0.php
 * @package tao
 
 *
 */
class funcAcl_actions_Admin extends tao_actions_CommonModule {

    /**
     * Constructor performs initializations actions
     * @return void
     */
    public function __construct(){
        parent::__construct();
        $this->defaultData();
    }

    /**
     * Show the list of roles
     * @return void
     */
    public function index(){
        $rolesc = new core_kernel_classes_Class(CLASS_ROLE);
        $roles = array();
        foreach ($rolesc->getInstances(true) as $id => $r) {
            $roles[] = array('id' => $id, 'label' => $r->getLabel());
        }

        $this->setData('roles', $roles);
        $this->setView('list.tpl');
    }

    public function getModules() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        } else {
            $role = new core_kernel_classes_Class($this->getRequestParameter('role'));
            
            $profile = array();

            $included = array();
            foreach (tao_models_classes_RoleService::singleton()->getIncludedRoles($role) as $includedRole) {
                $included[] = $includedRole->getUri();
            }
            
            $extManager = common_ext_ExtensionsManager::singleton();
            $extensions = $extManager->getInstalledExtensions();
            $accessService = funcAcl_models_classes_AccessService::singleton();

            $extAccess = funcAcl_helpers_Cache::retrieveExtensions();
            foreach ($extensions as $extId => $ext){
                $extAclUri = $accessService->makeEMAUri($extId);
                $atLeastOneAccess = false;
                $allAccess = in_array($role->getUri(), $extAccess[$extAclUri]);
                $inherited = count(array_intersect($included, $extAccess[$extAclUri])) > 0; 
                
                $profile[$extId] = array('modules' => array(), 
                                         'has-access' => false,
                                         'has-allaccess' => $allAccess,
                                         'has-inherited' => $inherited, 
                                         'uri' => $extAclUri
                );
                
                foreach (ControllerHelper::getControllers($extId) as $controllerClassName) {
                    $modUri = funcAcl_helpers_Map::getUriForController($controllerClassName);
                    $module = new core_kernel_classes_Resource($modUri);
                    
                    $moduleAccess = funcAcl_helpers_Cache::getControllerAccess($controllerClassName);
                    $uri = explode('#', $modUri);
                    list($type, $extId, $modId) = explode('_', $uri[1]);
                    
                    $profile[$extId]['modules'][$modId] = array(
                        'has-access' => false,
                        'has-allaccess' => false,
                        'has-inherited' => false,
                        'uri' => $module->getUri()
                    );
                    
                    if (count(array_intersect($included, $moduleAccess['module'])) > 0) {
                        $profile[$extId]['modules'][$modId]['has-inherited'] = true;
                        $atLeastOneAccess = true;
                    } elseif (true === in_array($role->getUri(), $moduleAccess['module'])){
                        $profile[$extId]['modules'][$modId]['has-allaccess'] = true;
                        $atLeastOneAccess = true;
                    } else {
                        // have a look at actions.
                        foreach ($moduleAccess['actions'] as $roles){
                            if (in_array($role->getUri(), $roles)){
                                $profile[$extId]['modules'][$modId]['has-access'] = true;
                                $atLeastOneAccess = true;
                                break;
                            }
                        }
                    }
                }
                
                if (!$allAccess && $atLeastOneAccess){
                    $profile[$extId]['has-access'] = true;
                }
            }
            
            if (!empty($profile['generis'])){
                unset($profile['generis']);
            }
            
            echo json_encode($profile);
        }
    }

    public function getActions() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = new core_kernel_classes_Resource($this->getRequestParameter('role'));
            $included = array();
            foreach (tao_models_classes_RoleService::singleton()->getIncludedRoles($role) as $includedRole) {
                $included[] = $includedRole->getUri();
            }
            $module = new core_kernel_classes_Resource($this->getRequestParameter('module'));
            
            $controllerClassName = funcAcl_helpers_Map::getControllerFromUri($module->getUri());
            $moduleAccess = funcAcl_helpers_Cache::getControllerAccess($controllerClassName);
            
            $actions = array();
            foreach (ControllerHelper::getActions($controllerClassName) as $actionName) {
                $uri = funcAcl_helpers_Map::getUriForAction($controllerClassName, $actionName);
                $part = explode('#', $uri);
                list($type, $extId, $modId, $actId) = explode('_', $part[1]);
                
                $actions[$actId] = array(
                    'uri' => $uri,
                    'has-access' => false,
                    'has-inherited' => false
                );
                
                if (isset($moduleAccess['actions'][$actionName]) && count(array_intersect($included, $moduleAccess['actions'][$actionName])) > 0) {
                    $actions[$actId]['has-inherited'] = true;
                } elseif (isset($moduleAccess['actions'][$actionName]) && in_array($role->getUri(), $moduleAccess['actions'][$actionName])) {
                    $actions[$actId]['has-access'] = true;
                }
            }
            
            ksort($actions);
            echo json_encode($actions);    
        }
    }

    public function removeExtensionAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $extensionService = funcAcl_models_classes_ExtensionAccessService::singleton();
            $extensionService->remove($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function addExtensionAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $extensionService = funcAcl_models_classes_ExtensionAccessService::singleton();
            $extensionService->add($role, $uri);
            echo json_encode(array('uri' => $uri));
        }
    }

    public function removeModuleAccess() {
        if (!tao_helpers_Request::isAjax()) {
            throw new Exception("wrong request mode");
        } else {
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $moduleService = funcAcl_models_classes_ModuleAccessService::singleton();
            $moduleService->remove($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function addModuleAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $moduleService = funcAcl_models_classes_ModuleAccessService::singleton();
            $moduleService->add($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function removeActionAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $actionService = funcAcl_models_classes_ActionAccessService::singleton();
            $actionService->remove($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function addActionAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $actionService = funcAcl_models_classes_ActionAccessService::singleton();
            $actionService->add($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function moduleToActionAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $actionService = funcAcl_models_classes_ActionAccessService::singleton();
            $actionService->moduleToActionAccess($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function moduleToActionsAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $actionService = funcAcl_models_classes_ActionAccessService::singleton();
            $actionService->moduleToActionsAccess($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }

    public function actionsToModuleAccess() {
        if (!tao_helpers_Request::isAjax()){
            throw new Exception("wrong request mode");
        }
        else{
            $role = $this->getRequestParameter('role');
            $uri = $this->getRequestParameter('uri');
            $moduleService = funcAcl_models_classes_ModuleAccessService::singleton();
            $moduleService->actionsToModuleAccess($role, $uri);
            echo json_encode(array('uri' => $uri));    
        }
    }
}