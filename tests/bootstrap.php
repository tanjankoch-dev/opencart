<?php
declare(strict_types=1);

// Composer autoloader (PHPUnit + vendor libs)
require_once __DIR__ . '/../upload/system/storage/vendor/autoload.php';

// Directory constants
define('DIR_OPENCART',    realpath(__DIR__ . '/../upload') . '/');
define('DIR_SYSTEM',      DIR_OPENCART . 'system/');
define('DIR_APPLICATION', DIR_OPENCART . 'catalog/');
define('DIR_EXTENSION',   DIR_OPENCART . 'extension/');
define('DIR_CONFIG',      DIR_SYSTEM . 'config/');
define('DIR_LANGUAGE',    DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE',    DIR_APPLICATION . 'view/template/');
define('DIR_CACHE',       DIR_SYSTEM . 'storage/cache/');
define('DIR_LOGS',        DIR_SYSTEM . 'storage/logs/');
define('DIR_DOWNLOAD',    DIR_SYSTEM . 'storage/download/');
define('DIR_UPLOAD',      DIR_SYSTEM . 'storage/upload/');
define('DIR_MODIFICATION', DIR_SYSTEM . 'modification/');
define('APPLICATION',     'Catalog');
define('VERSION',         '4.2.0.0');
define('HTTP_SERVER',     'http://localhost/');
define('HTTPS_SERVER',    'http://localhost/');
define('DB_PREFIX',       'oc_');

// Register OpenCart's custom autoloader
require_once DIR_SYSTEM . 'engine/autoloader.php';

$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

$GLOBALS['oc_autoloader'] = $autoloader;
