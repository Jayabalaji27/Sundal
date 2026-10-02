<?php
/**
 * Custom package autoloader bootstrap.
 * Registers vendor packages that are not in the git-committed autoload maps.
 * This file is loaded from public/index.php and is git-tracked.
 */

$vendorDir = __DIR__ . '/../vendor';

// Register PSR-4 namespaces for packages not in committed autoload_psr4.php
$extraPsr4 = [
    'Maatwebsite\\Excel\\'          => $vendorDir . '/maatwebsite/excel/src',
    'PhpOffice\\PhpSpreadsheet\\'   => $vendorDir . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
    'Barryvdh\\DomPDF\\'            => $vendorDir . '/barryvdh/laravel-dompdf/src',
    'Dompdf\\'                      => $vendorDir . '/dompdf/dompdf/src',
    'FontLib\\'                     => $vendorDir . '/dompdf/php-font-lib/src/FontLib',
    'Svg\\'                         => $vendorDir . '/dompdf/php-svg-lib/src/Svg',
    'Google\\'                      => $vendorDir . '/google/apiclient/src',
    'Google\\Auth\\'                => $vendorDir . '/google/auth/src',
    'Google\\Service\\'             => $vendorDir . '/google/apiclient-services/src',
    'Firebase\\JWT\\'               => $vendorDir . '/firebase/php-jwt/src',
    'Sabberworm\\CSS\\'             => $vendorDir . '/sabberworm/php-css-parser/src',
    'Matrix\\'                      => $vendorDir . '/markbaker/matrix/classes/src',
    'Complex\\'                     => $vendorDir . '/markbaker/complex/classes/src',
    'Masterminds\\'                 => $vendorDir . '/masterminds/html5/src',
    'Nette\\'                       => $vendorDir . '/nette/utils/src',
    'ParagonIE\\ConstantTime\\'     => $vendorDir . '/paragonie/constant_time_encoding/src',
    'phpseclib3\\'                  => $vendorDir . '/phpseclib/phpseclib/phpseclib',
    'Composer\\Pcre\\'              => $vendorDir . '/composer/pcre/src',
];

spl_autoload_register(function ($class) use ($extraPsr4) {
    foreach ($extraPsr4 as $prefix => $dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
}, true, true); // prepend=true so this runs BEFORE composer autoloader

// Load Google API aliases file (defines Google_Client legacy class)
$googleAliases = $vendorDir . '/google/apiclient/src/aliases.php';
if (file_exists($googleAliases) && !class_exists('Google_Client', false)) {
    require_once $googleAliases;
}
