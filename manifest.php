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

use oat\funcAcl\models\FuncAclServiceProvider;
use oat\funcAcl\scripts\install\RegisterEvents;
use oat\funcAcl\scripts\install\RegisterFuncAcl;
use oat\funcAcl\scripts\update\Updater;

$extpath = __DIR__ . DIRECTORY_SEPARATOR;

return [
    'name' => 'funcAcl',
    'label' => 'Functionality ACL',
    'description' => 'Functionality Access Control Layer',
    'license' => 'GPL-2.0',
    'author' => 'Open Assessment Technologies, CRP Henri Tudor',
    'install' => [
        'rdf' => [
            __DIR__ . '/models/ontology/taofuncacl.rdf',
        ],
        'php' => [
            RegisterFuncAcl::class,
            RegisterEvents::class,
        ],
    ],
    'update' => Updater::class,
    'managementRole' => 'http://www.tao.lu/Ontologies/taoFuncACL.rdf#FuncAclManagerRole',
    'acl' => [
        ['grant', 'http://www.tao.lu/Ontologies/taoFuncACL.rdf#FuncAclManagerRole', ['ext' => 'funcAcl']],
    ],
    'routes' => [
        '/funcAcl' => 'oat\\funcAcl\\controller',
    ],
    'constants' => [
        # views directory
        'DIR_VIEWS' => $extpath . 'views' . DIRECTORY_SEPARATOR,

        #BASE URL (usually the domain root)
        'BASE_URL' => ROOT_URL . 'funcAcl/',
    ],
    'extra' => [
        'structures' => __DIR__ . DIRECTORY_SEPARATOR . 'controller' . DIRECTORY_SEPARATOR . 'structures.xml',
    ],
    'containerServiceProviders' => [
        FuncAclServiceProvider::class
    ],
];
