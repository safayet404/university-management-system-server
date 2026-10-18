<?php

// Vercel serverless entry point: hand every request to Laravel's front controller.
//
// vercel-php runs this file as the router with SCRIPT_NAME=/api/index.php, so
// Symfony would treat "/api" as the app's base path and strip it from every
// request, leaving no route under api/ reachable. Pretend we are public/index.php.
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__ . '/../public/index.php';
