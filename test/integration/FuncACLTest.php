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
 * Copyright (c) 2008-2010 (original work) Deutsche Institut für Internationale Pädagogische Forschung
 *                                         (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor
 *                                                  (under the project TAO-SUSTAIN & TAO-DEV);
 *               2023 (update and modification) Open Assessment Technologies SA;
 */

use oat\funcAcl\helpers\CacheHelper;
use oat\funcAcl\models\AccessService;
use oat\funcAcl\models\ActionAccessService;
use oat\funcAcl\models\FuncAcl;
use oat\funcAcl\models\ModuleAccessService;
use oat\oatbox\user\LoginService;
use oat\tao\model\TaoOntology;
use oat\tao\test\TaoPhpUnitTestRunner;

// phpcs:disable
include_once dirname(__FILE__) . '/../../includes/raw_start.php';
// phpcs:enable

/**
 * @todo fix 'PHP Warning:  in_array() expects parameter 2 to be array, null given in
 *      /vagrant/build/tao/models/classes/class.UserService.php on line 506'
 */
class FuncACLTest extends TaoPhpUnitTestRunner
{
    private $user;
    private $testRole;

    public function setUp(): void
    {
        parent::setUp();

        $userService = tao_models_classes_UserService::singleton();
        $roleService = tao_models_classes_RoleService::singleton();
        $baseRole = new core_kernel_classes_Resource(TaoOntology::PROPERTY_INSTANCE_ROLE_BACKOFFICE);
        $this->testRole = $roleService->addRole('testrole', $baseRole);
        $this->user = $userService->addUser('testcase', 'testcase');
        $userService->attachRole($this->user, $this->testRole);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $userService = tao_models_classes_UserService::singleton();
        $roleService = tao_models_classes_RoleService::singleton();

        if ($this->user != null) {
            $userService->removeUser($this->user);
        }

        if ($this->testRole) {
            $roleService->removeRole($this->testRole);
        }
    }

    public function testFuncACL()
    {
        $srv = tao_models_classes_UserService::singleton();
        $generisUser = new core_kernel_users_GenerisUser($this->user);
        $this->assertTrue(LoginService::startSession($generisUser));

        // -- Test uri creation
        $emauri = AccessService::FUNCACL_NS . '#a_tao_Users_add';
        $emaurimod = AccessService::FUNCACL_NS . '#m_tao_Users';
        $makeemauri = AccessService::singleton()->makeEMAUri('tao', 'Users', 'add');
        $makeemaurimod = AccessService::singleton()->makeEMAUri('tao', 'Users');
        $this->assertEquals($emauri, $makeemauri);
        $this->assertEquals($emaurimod, $makeemaurimod);

        $funcAclImp = new FuncAcl();

        // -- Try to access a restricted action
        $this->assertFalse($funcAclImp->hasAccess('add', 'Users', 'tao'));

        // -- Try to access a unrestricted action
        // (BACKOFFICE has access to the backend login action because it includes the TAO Role)
        $this->assertTrue($funcAclImp->hasAccess('login', 'Main', 'tao'));

        // -- Try to access an action that does not exist.
        $this->assertFalse($funcAclImp->hasAccess('action', 'Unknown', 'tao'));

        // -- Try to access a unrestricted action
        // Add access for this action to the Manager role.
        ActionAccessService::singleton()->add($this->testRole->getUri(), $makeemauri);

        // Add the Manager role the the currently tested user
        tao_models_classes_UserService::singleton()->attachRole($this->user, $this->testRole);

        // Logoff/login, to refresh roles cache
        $this->assertTrue(LoginService::startSession($generisUser));

        // Ask for access
        $this->assertTrue($funcAclImp->hasAccess('add', 'Users', 'tao'));

        // Remove the access to this action from the Manager role
        ActionAccessService::singleton()->remove($this->testRole->getUri(), $makeemauri);

        // We should not have access anymore to this action with the Manager role
        $this->assertFalse($funcAclImp->hasAccess('add', 'Users', 'tao'));

        // -- Give access to the entire module and try to access the previously tested action
        ModuleAccessService::singleton()->add($this->testRole->getUri(), $makeemaurimod);
        $this->assertTrue($funcAclImp->hasAccess('add', 'Users', 'tao'));

        // -- Remove the entire module access and try again
        ModuleAccessService::singleton()->remove($this->testRole->getUri(), $makeemaurimod);
        $this->assertFalse($funcAclImp->hasAccess('add', 'Users', 'tao'));

        // reset
        ModuleAccessService::singleton()->add($this->testRole->getUri(), $makeemaurimod);

        // Unattach role from user
        tao_models_classes_UserService::singleton()->unnatachRole($this->user, $this->testRole);
    }

    public function testACLCache()
    {
        $moduleCache = CacheHelper::getControllerAccess('tao_actions_Users');
        $this->assertTrue(is_array($moduleCache));
    }
}
