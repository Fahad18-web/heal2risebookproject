<?php
/**
 * Heal2Rise Book - Logout Handler
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

logout();

redirect('/index.php');
exit;
?>
