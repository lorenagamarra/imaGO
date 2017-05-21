<?php

session_cache_limiter(false);
session_start();

require_once 'vendor/autoload.php';

//************************************
//       TEACHER SPECIAL CODE
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('logs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('logs/errors.log', Logger::ERROR));
//       TEACHER SPECIAL CODE
//************************************

/*
  DB::$user = 'imago';
  DB::$dbName = 'imago';
  DB::$password = 'viXftFK4GpTbhQvu';
  DB::$port = 3306;
  DB::$encoding = 'utf8';
 */

DB::$user = 'cp4776_imago';
DB::$dbName = 'cp4776_imago';
DB::$password = 'viXftFK4GpTbhQvu';

//************************************
//       TEACHER SPECIAL CODE
DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

function nonsql_error_handler($params) {
    global $app, $log;
    $log->error("Database error: " . $params['error']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die;
}

function sql_error_handler($params) {
    global $app, $log;
    $log->error("SQL error: " . $params['error']);
    $log->error(" in query: " . $params['query']);
    http_response_code(500);
    $app->render('error_internal.html.twig');
    die; // don't want to keep going if a query broke
}

//       TEACHER SPECIAL CODE
//************************************
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
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT id FROM photos WHERE userID=%i", $userId);
    //print_r($photoIdList);
    $app->render('photos.html.twig', array('photoIdList' => $photoIdList));
});






//**********************************
//***** SIGN UP (registration) *****
$app->get('/signup', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signup.html.twig');
        return;
    }
    $app->render('photos.html.twig');
});


$app->post('/signup', function() use ($app) {
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
        $app->render('signup_success.html.twig');
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
    $email = $app->request()->post('email');
    $pass = $app->request()->post('pass1');
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
        $app->render('signin.html.twig', array("error" => true));
    } else {
        unset($user['password']);
        $_SESSION['imagouser'] = $user;
        $app->render('signin_success.html.twig');
    }
});





//**********************************
//******* SIGN OUT (logout) ********
$app->get('/signout', function() use ($app) {
    unset($_SESSION['imagouser']);
    $app->render('signout_success.html.twig');
});






//**********************************
//************* PHOTOS *************

$app->get('/photos', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT id FROM photos WHERE userID=%i", $userId);
    //print_r($photoIdList);
    $app->render('photos.html.twig', array('photoIdList' => $photoIdList));
});





//**********************************
//*********** PHOTOS ADD ***********
$app->get('/photos/add', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('photos_add.html.twig');
});

$app->post('/photos/add', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('signin.html.twig');
        return;
    }
    //print_r($_POST);
    //print_r($_FILES);
    // extract variables
    $image = $_FILES['image'];
    /* Process image with GD library */
    $verifyimg = getimagesize($_FILES['image']['tmp_name']);

    // verify inputs
    $errorList = array();
    //if ($image['error'] == 0) {
    if ($image) {
        $imageInfo = getimagesize($image["tmp_name"]);
        if (!$imageInfo) {
            array_push($errorList, "File does not look like an valid image");
        } else {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width > 7000 || $height > 7000) {
                array_push($errorList, "Image must at most 7000 by 7000 pixels");
            }

            // FIXME: opened a security hole here! .. must be forbidden
            if (strstr($image["name"], "..")) {
                array_push($errorList, "File name invalid");
            }

            /* Make sure the MIME type is an image */
            $pattern = "#^(image/)[^\s\n<]+$#i";
            if (!preg_match($pattern, $verifyimg['mime'])) {
                die("Only image files are allowed!");
            }

            // FIXME: only allow select extensions .jpg .gif .png, never .php
            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png'))) {
                array_push($errorList, "File name invalid");
            }
            // FIXME: do not allow file to override an previous upload
            if (file_exists('uploads/' . $image['name'])) {
                array_push($errorList, "File name already exists. Will not override.");
            }
        }
    } else {
        array_push($errorList, "You must select a file");
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
        //change to FLASH message after  **********************************
        $app->render("photos_add_success.html.twig");
    } else {
        // TODO: keep values entered on failed submission
        $app->render('photos_add.html.twig');
    }
});




//**********************************
//********* PHOTO DOWNLOAD *********
$app->get('/photoview/:id(/:operation)', function($id, $operation = '') use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }

    $userId = $_SESSION['imagouser']['id'];
    $photo = DB::queryFirstRow("SELECT * FROM photos WHERE userID=%i AND id=%i", $userId, $id);
    
    $app->response->headers->set('Content-Type', $photo['imageMimeType']);
        echo $photo['imageData'];
        
    if (!$photo) {
        $app->response()->status(404);
        echo "404 - not found";
    } else {
        if ($operation == 'download') {
            $app->response->headers->set('Content-Disposition', 'attachment; somefile.jpg');
        }
    }
})->conditions(array('operation' => 'download'));



//**********************************
//********* PHOTO DELETE ***********
$app->get('/photoview/:id(/:operation)', function($id, $operation = '') use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }

    $userId = $_SESSION['imagouser']['id'];
    $photo = DB::queryFirstRow("SELECT * FROM photos WHERE userID=%i AND id=%i", $userId, $id);
    //print_r($photo);
    
    //$app->response->headers->set('Content-Type', $photo['imageMimeType']);
        //echo $photo['imageData'];
    $app->render('photos_delete.html.twig', array('photo' => $photo));
})->conditions(array('operation' => 'delete'));

$app->post('/photoview/:id(/:operation)', function($id, $operation = '') use ($app) {
    DB::delete('photos', 'id=%i', $id);
    $app->render('photos_delete_success.html.twig');
})->conditions(array('operation' => 'delete'));




//**********************************
//******* PROFILE (UPDATE) *********
$app->get('/profile', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('profile.html.twig');
});

$app->post('/profile', function() use ($app) {
    
    $userId = $_SESSION['imagouser']['id'];

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
        $app->render('profile.html.twig', array(
            'errorList' => $errorList,
            'v' => $valueList
        ));
    } else {
        DB::update('users', array(
            'name' => $name,
            'email' => $email,
            'password' => $pass1
                ), "id=%i", $userId);

        unset($_SESSION['imagouser']);
        $app->render('profile_update_success.html.twig');
    }
});



//**********************************
//******* PROFILE (DELETE) *********
$app->get('/profile/delete', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $profile = DB::queryFirstRow('SELECT * FROM users WHERE id=%i', $userId);
    $app->render('profile_delete.html.twig', array(
        'p' => $profile
    ));
});

$app->post('/profile/delete', function() use ($app) {
    $userId = $_SESSION['imagouser']['id'];
    unset($_SESSION['imagouser']);
    DB::delete('users', 'id=%i', $userId);                  //FIX ME - CHANGE FK CONSTRAINT TO ON DELETE CASCADE
    $app->render('profile_delete_success.html.twig');
});

$app->run();

