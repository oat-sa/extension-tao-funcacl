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
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA;
 */

namespace oat\funcAcl\scripts\update;

use common_ext_ExtensionUpdater;
use oat\funcAcl\models\FuncAcl;
use oat\tao\model\accessControl\func\AclProxy;

/**
 * @deprecated use migrations instead. See https://github.com/oat-sa/generis/wiki/Tao-Update-Process
 */
class Updater extends common_ext_ExtensionUpdater
{
    /**
     * @param string $initialVersion
     *
     * @return void
     */
    public function update($initialVersion)
    {
        $this->skip('0', '3.0.0');

        if ($this->isVersion('3.0.0')) {
            $this->getServiceManager()->register(AclProxy::SERVICE_ID, new FuncAcl());
            $this->setVersion('4.0.0');
        }

        $this->skip('4.0.0', '5.7.1');

        //Updater files are deprecated. Please use migrations.
        //See: https://github.com/oat-sa/generis/wiki/Tao-Update-Process

        $this->setVersion($this->getExtension()->getManifest()->getVersion());
    }
}
