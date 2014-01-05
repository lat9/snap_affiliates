<?php
/*
** autoloader activation point for snap-affiliates initialization
**
** @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
*/
if (!defined('IS_ADMIN_FLAG')) {
 die('Illegal Access');
}
/*
** point 160 is after the session is established.
*/
  $autoLoadConfig[160][] = array('autoType'=>'init_script',
                                 'loadFile'=> 'init_snap.php');
                                 
  $autoLoadConfig[160][] = array('autoType'=>'class',
                                 'loadFile'=>'observers/class.snap_order_observer.php');
  $autoLoadConfig[160][] = array('autoType'=>'classInstantiate',
                                 'className'=>'snap_order_observer',
                                 'objectName'=>'snap_order_observer');