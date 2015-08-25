<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Creates the database interface required for database interactions
 * and add it to GLOBALS.
 *
 * @package PhpMyAdmin-DBI
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

require_once 'libraries/di/Container.class.php';
require_once 'libraries/DatabaseInterface.php';

if (defined('TESTSUITE')) {
    /**
     * For testsuite we use dummy driver which can fake some queries.
     */
    include_once './libraries/dbi/DBIDummy.class.php';
    $extension = new PMA_DBI_Dummy();
} else {

    /**
     * First check for the mysqli extension, as it's the one recommended
     * for the MySQL server's version that we support
     * (if PHP 7+, it's the only one supported)
     */
    $extension = 'mysqli';
    if (! PMA\libraries\DatabaseInterface::checkDbExtension($extension)) {

        $docurl = PMA\libraries\Util::getDocuLink('faq', 'faqmysql');
        $doclink = sprintf(
            __('See %sour documentation%s for more information.'),
            '[a@' . $docurl  . '@documentation]',
            '[/a]'
        );

        if (PMA_PHP_INT_VERSION < 70000) {
            $extension = 'mysql';
            if (! PMA\libraries\DatabaseInterface::checkDbExtension($extension)) {
                // warn about both extensions missing and exit
                PMA_warnMissingExtension(
                    'mysqli|mysql',
                    true,
                    $doclink
                );
            } elseif (empty($_SESSION['mysqlwarning'])) {
                trigger_error(
                    __(
                        'You are using the mysql extension which is deprecated in '
                        . 'phpMyAdmin. Please consider installing the mysqli '
                        . 'extension.'
                    ) . ' ' . $doclink,
                    E_USER_WARNING
                );
                // tell the user just once per session
                $_SESSION['mysqlwarning'] = true;
            }
        } else {
            // mysql extension is not part of PHP 7+, so warn and exit
            PMA_warnMissingExtension(
                'mysqli',
                true,
                $doclink
            );
        }
    }

    /**
     * Including The DBI Plugin
     */
    switch($extension) {
    case 'mysql' :
        include_once './libraries/dbi/DBIMysql.class.php';
        $extension = new PMA_DBI_Mysql();
        break;
    case 'mysqli' :
        include_once './libraries/dbi/DBIMysqli.class.php';
        $extension = new PMA_DBI_Mysqli();
        break;
    }
}
$GLOBALS['dbi'] = new PMA\libraries\DatabaseInterface($extension);

$container = \PMA\DI\Container::getDefaultContainer();
$container->set('PMA_DatabaseInterface', $GLOBALS['dbi']);
$container->alias('dbi', 'PMA_DatabaseInterface');
