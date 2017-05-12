<?php

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

//DB::$host = '127.0.0.1';
DB::$user = 'slimtodo';
DB::$password = '5fMWxhW5RtveBwX0';
DB::$dbName = 'slimtodo';
DB::$port = 3306;
DB::$encoding = 'utf8';

// Slim creation and setup
$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
        ));

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
    'cache' => dirname(__FILE__) . '/cache'
);
$view->setTemplatesDirectory(dirname(__FILE__) . '/templates');

if (!isset($_SESSION['imagouser'])) {
    $_SESSION['imagouser'] = array();
}

$twig = $app->view()->getEnvironment();
$twig->addGlobal('imagouser', $_SESSION['imagouser']);

/*
//handle with the "/" on the address
$app->get('/', function() use ($app) {
    $app->render('imagoindex.html.twig');
});
*/







$app->run();

