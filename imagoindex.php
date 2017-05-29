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
    $photoIdList = DB::query("SELECT id FROM photos WHERE userID=%i ORDER BY id DESC", $userId);
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

$app->post('/signup', function() use ($app, $log) {
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
            //'password' => $pass1
            'password' => password_hash($pass1, CRYPT_BLOWFISH)
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

$app->post('/signin', function() use ($app, $log) {
    $email = $app->request()->post('email');
    $pass = $app->request()->post('pass1');
    // verification    
    $error = false;
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if (!$user) {
        $error = true;
    }
    if (!password_verify($pass, $user['password'])) {
        $error = true;
    }
    // decide what to render
    if ($error) {
        $app->render('signin.html.twig', array("error" => true));
    } else {
        if (password_verify($pass, $user['password'])) {
            unset($user['password']);
            $_SESSION['imagouser'] = $user;
            $log->debug("User signed in with id=" . $user['id']);
            $app->render('signin_success.html.twig');
        }
    }
});


//**********************************
//******* SIGN OUT (logout) ********
$app->get('/signout', function() use ($app) {
    unset($_SESSION['imagouser']);
    $app->render('signout_success.html.twig');
});


//**********************************
//******* PROFILE (UPDATE) *********
$app->get('/profile', function() use ($app, $log) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('profile.html.twig');
});

$app->post('/profile', function() use ($app, $log) {

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
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s AND id!=%i", $email, $userId);
        if ($user) {
            array_push($errorList, "Email already in use by another account");
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
            //'password' => $pass1
            'password' => password_hash($pass1, CRYPT_BLOWFISH)
                ), "id=%i", $userId);

        unset($_SESSION['imagouser']);
        $app->render('profile_update_success.html.twig');
    }
});



//**********************************
//******* PROFILE (DELETE) *********
$app->get('/profile/delete', function() use ($app, $log) {
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

$app->post('/profile/delete', function() use ($app, $log) {
    $userId = $_SESSION['imagouser']['id'];
    unset($_SESSION['imagouser']);
    DB::delete('users', 'id=%i', $userId);
    $app->render('profile_delete_success.html.twig');
});

//****************************************************
//********* PASSWORD RESET *********** 
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

$app->map('/passreset', function () use ($app, $log) {
    // Alternative to cron-scheduled cleanup
    if (rand(1, 1000) == 111) {
        // TODO: do the cleanup 1 in 1000 accessed to /passreset URL
    }
    if ($app->request()->isGet()) {
        $app->render('passreset.html.twig');
    } else {
        $email = $app->request()->post('email');
        $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($user) {
            $app->render('passreset_success.html.twig');
            $secretToken = generateRandomString(50);
            // VERSION 2: insert-update TODO
            DB::insertUpdate('passresets', array(
                'userID' => $user['id'],
                'secretToken' => $secretToken,
                'expiryDateTime' => date("Y-m-d H:i:s", strtotime("+5 minutes"))
            ));
            // email user
            $url = 'http://' . $_SERVER['SERVER_NAME'] . '/passreset/' . $secretToken;
            $html = $app->view()->render('email_passreset.html.twig', array(
                'name' => $user['name'],
                'url' => $url
            ));
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Noreply <noreply@imago.ipd9.info>\r\n";
            $headers .= "To: " . htmlentities($user['name']) . " <" . $email . ">\r\n";

            mail($email, "Password reset from imaGO", $html, $headers);
            $log->info("Password reset for $email email sent");
        } else {
            $app->render('passreset.html.twig', array('error' => TRUE));
        }
    }
})->via('GET', 'POST');

$app->map('/passreset/:secretToken', function($secretToken) use ($app, $log) {
    $row = DB::queryFirstRow("SELECT * FROM passresets WHERE secretToken=%s", $secretToken);
    if (!$row) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
    if (strtotime($row['expiryDateTime']) < time()) {
        $app->render('passreset_notfound_expired.html.twig');
        return;
    }
    //
    if ($app->request()->isGet()) {
        $app->render('passreset_form.html.twig');
    } else {
        $pass1 = $app->request()->post('pass1');
        $pass2 = $app->request()->post('pass2');
        // TODO: verify password quality and that pass1 matches pass2
        $errorList = array();
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
            $app->render('passreset_form.html.twig', array(
                'errorList' => $errorList
            ));
        } else {
            // success - reset the password
            DB::update('users', array(
                'password' => password_hash($pass1, CRYPT_BLOWFISH)
                    //'password' => $pass1
                    ), "id=%d", $row['userID']);
            DB::delete('passresets', 'secretToken=%s', $secretToken);
            $app->render('passreset_form_success.html.twig');
            $log->info("Password reset completed for " . $row['email'] . " uid=" . $row['userID']);
        }
    }
})->via('GET', 'POST');

// returns TRUE if password is strong enough,
// otherwise returns string describing the problem
function verifyPassword($pass1) {
    if (!preg_match('/[0-9;\'".,<>`~|!@#$%^&*()_+=-]/', $pass1) || (!preg_match('/[a-z]/', $pass1)) || (!preg_match('/[A-Z]/', $pass1)) || (strlen($pass1) < 8)) {
        return "Password must be at least 8 characters " .
                "long, contain at least one upper case, one lower case, " .
                " one digit or special character";
    }
    return TRUE;
}

//**********************************
//************* PHOTOS *************

$app->get('/photos', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT id FROM photos WHERE userID=%i ORDER BY id DESC", $userId);
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

//*******************************************
//********* PHOTO VIEW AND DOWNLOAD *********
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

    $app->render('photos_delete.html.twig', array('photo' => $photo));
})->conditions(array('operation' => 'delete'));

$app->post('/photoview/:id(/:operation)', function($id, $operation = '') use ($app) {
    DB::delete('photos', 'id=%i', $id);
    $app->render('photos_delete_success.html.twig');
})->conditions(array('operation' => 'delete'));


//**********************************
//************* ALBUMS *************
$app->get('/albums', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $albumList = DB::query("SELECT * FROM albums WHERE userID=%i ORDER BY id DESC", $userId);
    $firstPhoto = DB::query("SELECT * FROM photosonalbums WHERE userID=%i", $userId);
    //$photosOnAlbumMaxId = DB::query("SELECT MAX(photoID) FROM photosonalbums WHERE userID=%i and albumID=%i", $userId, $albumID);
    //print_r($photosOnAlbumMaxId);
    //$app->render('albums.html.twig', array('albumList' => $albumList, 'firstPhoto' => $firstPhoto, photosOnAlbumMaxId => $photosOnAlbumMaxId));
    $app->render('albums.html.twig', array('albumList' => $albumList, 'firstPhoto' => $firstPhoto));
});


//**********************************
//*********** ALBUMS ADD ***********
$app->get('/albums/add', function() use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $app->render('albums_add.html.twig');
});

$app->post('/albums/add', function() use ($app) {
    // extract variables
    $userId = $_SESSION['imagouser']['id'];
    $albumTitle = $app->request()->post('albumTitle');
    $albumDate = $app->request()->post('albumDate');
    $valueList = array('albumTitle' => $albumTitle, 'albumDate' => $albumDate);
    // verify inputs
    $errorList = array();
    if (strlen($albumTitle) < 2 || strlen($albumTitle) > 100) {
        array_push($errorList, "The title of this album must be between 2 and 100 characters");
    }
    // TODO: check if date looks like it should / parses as a date
    if (empty($albumDate)) {
        array_push($errorList, "You must select a date");
    }
    // receive data and insert
    if (!$errorList) {
        $userID = $_SESSION['imagouser']['id'];
        DB::insert('albums', array(
            'userID' => $userID,
            'title' => $albumTitle,
            'date' => $albumDate
        ));
        $app->render('albums_add_success.html.twig');
    } else {
        // TODO: keep values entered on failed submission
        $app->render('albums_add.html.twig', array(
            'v' => $valueList
        ));
    }
});


//*******************************************
//********* ALBUM EDIT *********************
$app->get('/albums/:id/edit', function($id) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE userID=%i AND id=%i", $userId, $id);
    $app->render('albums_edit.html.twig', array('album' => $album));
});

$app->post('/albums/:id/edit', function($id) use ($app) {
    $userId = $_SESSION['imagouser']['id'];
    $albumTitle = $app->request()->post('albumTitle');
    $albumDate = $app->request()->post('albumDate');
    // verify inputs
    $errorList = array();
    if (strlen($albumTitle) < 2 || strlen($albumTitle) > 100) {
        array_push($errorList, "The title of this album must be between 2 and 100 characters");
    }
    // TODO: check if date looks like it should / parses as a date
    if (empty($albumDate)) {
        array_push($errorList, "You must select a date");
    }
    //
    if ($errorList) {
        $app->call('/albums/:id/edit', array(
            'errorList' => $errorList
        ));
    } else {
        DB::update('albums', array(
            'title' => $albumTitle,
            'date' => $albumDate
                ), "id=%i", $id);
        $app->render('albums_edit_success.html.twig');
    }
});


//**********************************
//********* ALBUM DELETE ***********
$app->get('/albums/:id/delete', function($id) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE userID=%i AND id=%i", $userId, $id);
    $app->render('albums_delete.html.twig', array('album' => $album));
});

$app->post('/albums/:id/delete', function($id) use ($app) {
    DB::delete('albums', 'id=%i', $id);
    $app->render('albums_delete_success.html.twig');
});


//****************************************************
//******** PHOTO LIST TO ADD TO ALBUM *************
$app->get('/albums/:id/addphoto', function($id) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT id FROM photos WHERE userID=%i ORDER BY id DESC", $userId);
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE id=%i and userID=%i", $id, $userId);
    $app->render('albums_add_photo.html.twig', array('album' => $album, 'photoIdList' => $photoIdList));
});


//****************************************************
//*********** ADDING PHOTO TO ALBUM ****************
$app->get('/albums/:id/addphoto/:photoid', function($id, $photoid) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE id=%i and userID=%i", $id, $userId);
    $photo = DB::queryFirstRow("SELECT * FROM photos WHERE id=%i and userID=%i", $photoid, $userId);

    /*
      $check = DB::query("SELECT * FROM photosonalbums WHERE photoID=%i and albumID=%i", $photoid, $id);
      print_r($check);

      $errorList = array();
      if($check) {
      array_push($errorList, "This photo is already into this album.");
      }

      if ($errorList) {
      $app->call('/albums/:id/addphoto', array(
      'errorList' => $errorList
      ));
      } else {
      DB::insert('photosonalbums', array(
      'photoID' => $photoid,
      'albumID' => $id,
      'userID' => $userId
      ));

      $app->render('albums_add_photo_success.html.twig', array('album' => $album, 'photo' => $photo));
      }
     */

    DB::insert('photosonalbums', array(
        'photoID' => $photoid,
        'albumID' => $id,
        'userID' => $userId
    ));

    $app->render('albums_add_photo_success.html.twig', array('album' => $album, 'photo' => $photo));
});


//****************************************************
//******** PHOTO LIST TO REMOVE FROM ALBUM *************
$app->get('/albums/:id/removephoto', function($id) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT photoID FROM photosonalbums WHERE albumID=%i and userID=%i ORDER BY photoID DESC", $id, $userId);
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE id=%i and userID=%i", $id, $userId);
    $app->render('albums_remove_photo.html.twig', array('album' => $album, 'photoIdList' => $photoIdList));
});


//****************************************************
//*********** REMOVING PHOTO FROM ALBUM ****************
$app->get('/albums/:id/removephoto/:photoID', function($id, $photoID) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE id=%i and userID=%i", $id, $userId);
    $photo = DB::queryFirstRow("SELECT * FROM photos WHERE id=%i and userID=%i", $photoID, $userId);

    DB::delete('photosonalbums', "photoID=%i and albumID=%i and userID=%i ", $photoID, $id, $userId);

    $app->render('albums_remove_photo_success.html.twig', array('album' => $album, 'photo' => $photo));
});


//****************************************************
//******** SEE PHOTO LIST FROM ALBUM *************
$app->get('/albums/:id/list', function($id) use ($app) {
    if (!$_SESSION['imagouser']) {
        $app->render('forbidden.html.twig');
        return;
    }
    $userId = $_SESSION['imagouser']['id'];
    $photoIdList = DB::query("SELECT photoID FROM photosonalbums WHERE albumID=%i and userID=%i ORDER BY photoID DESC", $id, $userId);
    $album = DB::queryFirstRow("SELECT * FROM albums WHERE id=%i and userID=%i", $id, $userId);
    $app->render('albums_list.html.twig', array('album' => $album, 'photoIdList' => $photoIdList));
});


$app->run();

