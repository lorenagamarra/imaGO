<?php

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

DB::$user = 'imago';
DB::$dbName = 'imago';
DB::$password = 'viXftFK4GpTbhQvu';
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




//**********************************
//************** HOME **************

$app->get('/', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('home.html.twig');
        return;
    }
    $app->render('photos.html.twig');
});



//**********************************
//***** SIGN UP (registration) *****

// STATE 1: First show
$app->get('/signup', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signup.html.twig');
        return;
    }
    $app->render('photos.html.twig');
});

// Receiving a submission
$app->post('/signup', function() use ($app) {
    
    //USE FACEBOOK/GOOGLE ACCOUNT  *********************************************************************
    
    // extract variables
    $name = $app->request()->post('name');
    $email = $app->request()->post('email');
    $pass1 = $app->request()->post('pass1');
    $pass2 = $app->request()->post('pass2');
    // list of values to retain after a failed submission
    $valueList = array('email' => $email, 'name' => $name);
    // check for errors and collect error messages
    $errorList = array();
    
    if (strlen($name) < 6 || strlen($name) > 50) {
        array_push($errorList, "Name must be between 6-50 characters long");
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
        array_push($errorList, "Email is invalid");
    } else {
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            array_push($errorList, "Email already in use");
        }
    }
    if ($pass1 != $pass2) {
        array_push($errorList, "Passwords do not match");
    } else {
        if (strlen($pass1) < 6) {
            array_push($errorList, "Password too short, must be 6 characters or longer");
        }
        if (preg_match('/[A-Z]/', $pass1) != 1 || preg_match('/[a-z]/', $pass1) != 1 || preg_match('/[0-9]/', $pass1) != 1) {
            array_push($errorList, "Password must contain at least one lowercase, one uppercase letter and a digit");
        }
    }
    //
    if ($errorList) {
        $app->render('signup.html.twig', array(
            'errorList' => $errorList,
            'v' => $valueList
        ));
    } else {
        DB::insert('users', array(
            'name' => $name,
            'email' => $email,
            'password' => $pass1
        ));
        $app->render('signup_success.html.twig');         //change to FLASH message after  **********************************
        
        //after successful sign up, sign in automatically and show /photos  **********************************
    }
});

// AJAX: Is user with this email already registered?
$app->get('/ajax/emailused/:email', function($email) {
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    echo json_encode($user != null);
});



//**********************************
//********* SIGN IN (login) ********
$app->get('/signin', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');             
        return;
    }
    $app->render('photos.html.twig');
});

$app->post('/signin', function() use ($app) {
    //print_r($_POST);    
    $email = $app->request()->post('email');
    $pass = $app->request()->post('pass');
    // verification    
    $error = false;
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$user) {
        $error = true;
    } else {
        if ($user['password'] != $pass) {
            $error = true;
        }
    }
    // decide what to render
    if ($error) {
        $app->render('login.html.twig', array("error" => true));
    } else {
        unset($user['password']);
        $_SESSION['imagouser'] = $user;
        $app->render('signin_success.html.twig');       //change to FLASH message after  **********************************
        
        //after successful sign in, show /photos  **********************************
    }
});


//**********************************
//******* SIGN OUT (logout) ********
$app->get('/signout', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('home.html.twig');
        return;
    }
    $app->render('signout.html.twig');                  
});

$app->post('/signout', function() use ($app) {
    unset($_SESSION['imagouser']);
    $app->render('signout_success.html.twig');         //change to FLASH message after  **********************************
});


//**********************************
//************* PHOTOS *************
$app->get('/photos', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');
        return;
    }
    $app->render('photos.html.twig');
});

$app->get('/photos', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    
    $photoList = DB::query("SELECT imageData, imageMimeType FROM photos WHERE userID=%i ORDER BY id DESC", $userId);
    foreach ($photoList as $row) {
        echo $row['imageData'] . $row['imageMimeType'];
        //echo '<img src="data:image/jpeg;base64,'.base64_encode( $result['image'] ).'"/>';
        //https://meekro.com/docs.php
    }
});


//**********************************
//*********** PHOTOS ADD ***********
$app->get('/photos/add', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');
        return;
    }
    $app->render('photos_add.html.twig');
});

$app->post('/photos/add', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');
        return;
    }

    // extract variables
    $image = isset($_FILES['image']) ? $_FILES['image'] : array();

    // verify inputs
    $errorList = array();
    if ($image) {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 3000 || $height > 3000) {
                array_push($errorList, "Image must at most 3000 by 3000 pixels");
            }
        }
    }
    // receive data and insert
    if (!$errorList) {
        $imageBinaryData = file_get_contents($image['tmp_name']);
        $userId = $_SESSION['imagouser']['id'];
        $mimeType = mime_content_type($image['tmp_name']);
        DB::insert('photos', array(
            'userId' => $userId,
            'imageData' => $imageBinaryData,
            'imageMimeType' => $mimeType
        ));
        $app->render('photos_add_success.html.twig');             //change to FLASH message after  **********************************
    } else {
        // TODO: keep values entered on failed submission
        $app->render('photos_add.html.twig');
    }
});














































































































$app->run();

