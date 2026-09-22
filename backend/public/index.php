<?php

declare(strict_types=1);

use CampBuddy\Middleware\JsonErrorMiddleware;
use CampBuddy\Middleware\SecurityHeadersMiddleware;
use CampBuddy\Settings;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$settings = Settings::fromEnv(dirname(__DIR__));

// --- Admin session, configured before it starts anywhere downstream -----
// An explicit, app-owned save path rather than the system default temp dir
// — on shared hosting, PHP-FPM/CGI pools are often isolated per-domain or
// per-account with their own private tmp, and if that path isn't writable
// (or a proxy/pool routes requests inconsistently), sessions silently fail
// to persist: login appears to succeed (redirect fires) but the next
// request finds no session data and bounces back to the login page.
$sessionPath = $settings->basePath . '/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0700, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

session_name('campbuddy_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $settings->adminUrl(),
    'secure' => str_starts_with($settings->appUrl, 'https://'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', $settings->adminUrl())) {
    session_start();
}

$builder = new ContainerBuilder();
$builder->addDefinitions(dirname(__DIR__) . '/src/dependencies.php');
/** @var Container $container */
$container = $builder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($settings->backendMountPath);

$app->addBodyParsingMiddleware();
$app->add(new SecurityHeadersMiddleware($settings));

$errorMiddleware = $app->addErrorMiddleware($settings->appDebug, true, true);
$errorMiddleware->setDefaultErrorHandler(
    new JsonErrorMiddleware($container->get(LoggerInterface::class), $settings->appDebug)
);

(require dirname(__DIR__) . '/src/routes.php')($app);

$app->run();
