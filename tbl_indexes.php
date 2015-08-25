<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Displays index edit/creation form and handles it
 *
 * @package PhpMyAdmin
 */

namespace PMA;

use PMA\libraries\Index;
use PMA\libraries\Response;

require_once 'libraries/common.inc.php';
require_once 'libraries/di/Container.class.php';
require_once 'libraries/controllers/TableIndexesController.class.php';
require_once 'libraries/Response.class.php';

$container = DI\Container::getDefaultContainer();
$container->factory('PMA\Controllers\Table\TableIndexesController');
$container->alias(
    'TableIndexesController', 'PMA\Controllers\Table\TableIndexesController'
);
$container->set('PMA\libraries\Response', Response::getInstance());
$container->alias('response', 'PMA\libraries\Response');

/* Define dependencies for the concerned controller */
$db = $container->get('db');
$table = $container->get('table');
$dbi = $container->get('dbi');

if (!isset($_REQUEST['create_edit_table'])) {
    include_once 'libraries/tbl_common.inc.php';
}
if (isset($_REQUEST['index'])) {
    if (is_array($_REQUEST['index'])) {
        // coming already from form
        $index = new Index($_REQUEST['index']);
    } else {
        $index = $dbi->getTable($db, $table)->getIndex($_REQUEST['index']);
    }
} else {
    $index = new Index;
}

$dependency_definitions = array(
    "index" => $index
);

/** @var Controllers\Table\TableIndexesController $controller */
$controller = $container->get('TableIndexesController', $dependency_definitions);
$controller->indexAction();
