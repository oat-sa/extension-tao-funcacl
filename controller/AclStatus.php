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
 * Copyright (c) 2022 (update and modification) Open Assessment Technologies SA;
 */

namespace oat\funcAcl\controller;

use oat\funcAcl\models\AclStatusCheckService;
use tao_actions_CommonModule;

/**
 * @author Gabriel Felipe Soares <gabriel.felipe.soares@taotesting.com>
 *
 * @TODO Use controller from DI container
 */
class AclStatus extends tao_actions_CommonModule
{
    public function check(): void
    {
        //@TODO Get it from DI instead
        $status = $this->getServiceManager()->get(AclStatusCheckService::class)->check(
            [
                'userId' => $this->getGetParameter('userId'),
                'controller' => $this->getGetParameter('controller'),
                'action' => $this->getGetParameter('action'),
                'requestParameters' => $this->getGetParameter('requestParameters'),
            ]
        );

        $response = $this->getPsrResponse()
            ->withAddedHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode($status));

        $this->setResponse($response);
    }
}
