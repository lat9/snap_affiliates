<?php
// -----
// Part of the SNAP Affiliates plugin for Zen Carts v156 and later.
//
// Copyright (c) 2013-2019, Vinos de Frutas Tropicales (lat9)
// Original: Copyright (c) 2009, Michael Burke (http://www.filterswept.com)
//
$autoLoadConfig[200][] = array(
    'autoType' => 'init_script',
    'loadFile' => 'init_snap_admin.php'
);

$autoLoadConfig[200][] = array(
    'autoType' => 'class',   
    'loadFile' => 'observers/SnapAffiliatesAdminObserver.php',
    'classPath' => DIR_WS_CLASSES
);
$autoLoadConfig[200][] = array (
    'autoType' => 'classInstantiate',
    'className' => 'SnapAffiliatesAdminObserver',
    'objectName' => 'snap'
);