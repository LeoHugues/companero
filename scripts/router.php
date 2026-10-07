<?php

// Router for PHP's built-in server (`.\dev serve`): existing files in public/,
// the Android APK built by `.\dev apk`, and everything else goes to Symfony.

$public = dirname(__DIR__).'/public';
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ('/companero.apk' === $path) {
    $apk = dirname(__DIR__).'/dist/companero.apk';
    if (!is_file($apk)) {
        http_response_code(404);
        echo "APK not built yet: run .\\dev apk\n";

        return true;
    }
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="companero.apk"');
    header('Content-Length: '.filesize($apk));
    readfile($apk);

    return true;
}

if ('/' !== $path && is_file($public.$path)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

require $public.'/index.php';
