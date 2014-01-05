<?php
/*-----
** Initialize the session's referrer key.
*/
//-bof-v2.1.2a
if (!defined('SNAP_COOKIE_LIFETIME')) {
  define ('SNAP_COOKIE_LIFETIME', 60*60*24*365);
}
//-eof-v2.1.2a
if( isset($_GET['referrer']) ) {
  if( strpos($_GET['referrer'], SNAP_KEY_PREFIX) === 0 ) {
    if( strlen($_GET['referrer']) < 24 ) {
      $_SESSION['referrer_key'] = $_GET['referrer'];
      setcookie("referrer_key", $_GET['referrer'], time() + SNAP_COOKIE_LIFETIME, "/");  /*v2.1.2c*/
    }
  }

} else if( $_COOKIE['referrer_key'] ) {
  if( strpos($_COOKIE['referrer_key'], SNAP_KEY_PREFIX) === 0 ) {
    if( strlen($_COOKIE['referrer_key']) < 24 ) {
      $_SESSION['referrer_key'] = $_COOKIE['referrer_key'];
    }
  }
}