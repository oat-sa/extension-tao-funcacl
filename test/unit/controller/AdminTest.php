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
 *q
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\funcAcl\test\unit\controller;

use oat\funcAcl\controller\Admin;
use oat\funcAcl\models\ActionAccessService;
use oat\funcAcl\models\ExtensionAccessService;
use oat\funcAcl\models\FuncAcl;
use oat\funcAcl\models\ModuleAccessService;
use oat\generis\test\MockObject;
use oat\generis\test\TestCase;
use oat\tao\model\accessControl\func\AclProxy;
use oat\tao\model\service\ApplicationService;
use ReflectionProperty;
use Request;
use tao_models_classes_Service;

class AdminTest extends TestCase
{
    /** @var Admin|MockObject */
    private $subject;

    /** @var ApplicationService|MockObject */
    private $applicationServiceMock;

    /** @var ExtensionAccessService|MockObject */
    private $extensionAccessServiceMock;

    /** @var ModuleAccessService|MockObject */
    private $moduleAccessServiceMock;

    /** @var ActionAccessService|MockObject */
    private $actionAccessServiceMock;

    /** @var FuncAcl|MockObject */
    private $funcAclMock;

    protected function setUp(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

        // At the moment controllers cannot be tested properly so we partially mock the class under test
        $this->subject = $this->getMockBuilder(Admin::class)
            ->setMethods(['getRequest', 'defaultData', 'prodLocker', 'returnJson'])
            ->getMock();

        $this->applicationServiceMock = $this->createMock(ApplicationService::class);
        $this->extensionAccessServiceMock = $this->createMock(ExtensionAccessService::class);
        $this->moduleAccessServiceMock = $this->createMock(ModuleAccessService::class);
        $this->actionAccessServiceMock = $this->createMock(ActionAccessService::class);
        $this->funcAclMock =  $this->createMock(FuncAcl::class);

        $this->applicationServiceMock
            ->expects($this->once())
            ->method('isDebugMode')
            ->willReturn(true);

        $this->subject->setServiceLocator($this->getServiceLocatorMock([
            ApplicationService::SERVICE_ID => $this->applicationServiceMock,
            AclProxy::SERVICE_ID => $this->funcAclMock,
        ]));

        // AccessServices are not registered in the service manager, need to mock them in the global Service class
        $instancesRef = new ReflectionProperty(tao_models_classes_Service::class, 'instances');
        $instancesRef->setAccessible(true);
        $instancesRef->setValue(null, [
            ExtensionAccessService::class => $this->extensionAccessServiceMock,
            ModuleAccessService::class => $this->moduleAccessServiceMock,
            ActionAccessService::class => $this->actionAccessServiceMock,
        ]);
    }

    protected function tearDown(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = null;
    }

    public function testRemoveExtensionAccess(): void
    {
        $this->extensionAccessServiceMock
            ->expects($this->once())
            ->method('remove')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->removeExtensionAccess();
    }

    public function testAddExtensionAccess(): void
    {
        $this->extensionAccessServiceMock
            ->expects($this->once())
            ->method('add')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->addExtensionAccess();
    }

    public function testRemoveModuleAccess(): void
    {
        $this->moduleAccessServiceMock
            ->expects($this->once())
            ->method('remove')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->removeModuleAccess();
    }

    public function testAddModuleAccess(): void
    {
        $this->moduleAccessServiceMock
            ->expects($this->once())
            ->method('add')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->addModuleAccess();
    }

    public function testRemoveActionAccess(): void
    {
        $this->actionAccessServiceMock
            ->expects($this->once())
            ->method('remove')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->removeActionAccess();
    }

    public function testAddActionAccess(): void
    {
        $this->actionAccessServiceMock
            ->expects($this->once())
            ->method('add')
            ->with('foo', 'bar');

        $this->subject
            ->method('getRequest')
            ->willReturn($this->getRequestMock('foo', 'bar'));

        $this->subject
            ->expects($this->once())
            ->method('returnJson')
            ->with([
                'uri' => 'bar',
            ]);

        $this->subject->addActionAccess();
    }

    /**
     * @return Request|MockObject
     */
    private function getRequestMock(string $role, string $uri): Request
    {
        $requestMock = $this->createMock(Request::class);

        $requestMock
            ->method('getParameter')
            ->withConsecutive(['role'], ['uri'])
            ->willReturnOnConsecutiveCalls($role, $uri);

        return $requestMock;
    }
}
