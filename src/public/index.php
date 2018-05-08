<?php
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params(3600);
    session_start();
}

/**
 * Require the autoload script, this will automatically load our classes
 * so we don't have to require a class everytime we use a class. Evertime
 * you create a new class, remember to runt 'composer update' in the terminal
 * otherwise your classes may not be recognized.
 */
require_once '../../vendor/autoload.php';

/**
 * Here we are creating the app that will handle all the routes. We are storing
 * our database config inside of 'settings'. This config is later used inside of
 * the container inside 'App/container.php'
 */

$container = require '../App/container.php';
$app = new \Slim\App($container);
$auth = require '../App/auth.php';
require '../App/cors.php';


/********************************
 *          ROUTES              *
 ********************************/


$app->get('/', function ($request, $response, $args) {
    /**
     * This fetches the 'index.php'-file inside the 'views'-folder
     */
    return $this->view->render($response, 'index.php');
});


/**
 * I added basic inline login functionality. This could be extracted to a
 * separate class. If the session is set is checked in 'auth.php'
 */
$app->post('/login', function ($request, $response, $args) {
    /**
     * Everything sent in 'body' when doing a POST-request can be
     * extracted with 'getParsedBody()' from the request-object
     * https://www.slimframework.com/docs/v3/objects/request.html#the-request-body
     */
    $body = $request->getParsedBody();
    $fetchUserStatement = $this->db->prepare('SELECT * FROM users WHERE username=:username');
    $fetchUserStatement->execute([
        ':username' => $body['username']
    ]);
    $user = $fetchUserStatement->fetch();
    if (password_verify($body['password'], $user['password'])) {
        $_SESSION['loggedIn'] = true;
        $_SESSION['userID'] = $user['userID'];
        return $response->withJson(['data' => [ $user['userID'], $user['username'] ]]);
    }
    return $response->withJson(['error' => 'wrong password']);
});

/**
 * Basic implementation, implement a better response
 */
$app->get('/logout', function ($request, $response, $args) {
    session_destroy();
    return $response->withJson('Success');
});

/**
 *  First implementation
 * 
 * Fix: Check for dupliate usernames, check incoming values
 */
$app->post('/register', function ($request, $response, $args) 
{
    $body = $request->getParsedBody();

    $username = $body['username'];
    $hashed_password = password_hash($body['password'], PASSWORD_DEFAULT);
    
    $statement = $this->database->prepare("INSERT INTO users(username, password) 
    VALUES(:username, :password)");
    $statement->bindparam(":username", $username);
    $statement->bindparam(":password", $hashed_password);            
    $statement->execute(); 
    
    return $response->withJson('Success');
});

/**
 * The group is used to group everything connected to the API under '/api'
 * This was done so that we can check if the user is authed when calling '/api'
 * but we don't have to check for auth when calling '/signin'
 */
$app->group('/api', function () use ($app) {

    //---------------------------------------------------------------------------------------------
    // Users
    //---------------------------------------------------------------------------------------------

    // GET http://localhost:XXXX/api/users
    $app->get('/users', function ($request, $response, $args) 
    {
        $all_users = $this->users->GetAllUsers();
        return $response->withJson(['data' => $all_users]);
    });

    // GET http://localhost:XXXX/api/users/id
    $app->get('/users/{id}', function ($request, $response, $args) 
    {
        $id = $args['id'];
        $user = $this->users->GetUserByID($id);
        return $response->withJson(['data' => $user]);
    });
    //---------------------------------------------------------------------------------------------
    // Entries
    //---------------------------------------------------------------------------------------------

    // GET http://localhost:XXXX/api/entries
    $app->get('/entries', function ($request, $response, $args)
    {
        $all_entries = $this->entries->GetAllEntries();                                               //This should be limited to 20 later on
        return $response->withJson(['data' => $all_entries]);
    });

    // POST http://localhost:XXXX/api/entries
    $app->post('/entries', function ($request, $response, $args)
    {
        $body = $request->getParsedBody();
        $new_entry = $this->entries->AddEntry($body);
        return $response->withJson(['data' => $new_entry]);                                           
    });

    // GET http://localhost:XXXX/api/entries/id
    $app->get('/entries/{id}', function ($request, $response, $args) 
    {
        $id = $args['id'];
        $entry = $this->entries->GetEntryByID($id);
        return $response->withJson(['data' => $entry]);
    });

    // DELETE http://localhost:XXXX/api/entries/id
    $app->delete('/entries/{id}', function ($request, $response, $args) 
    {
        $id = $args['id'];
        $entry = $this->entries->DeleteEntry($id);
        return $response->withJson(['data' => $entry]);
    });

    // PATCH http://localhost:XXXX/api/entries/id
    $app->patch('/entries/{id}', function ($request, $response, $args) 
    {
        $id = $args['id'];
        $body = $request->getParsedBody();
        $entry = $this->entries->UpdateEntry($id, $body);
        return $response->withJson(['data' => $entry]);
    });

    // POST http://localhost:XXXX/api/entries
    $app->post('/entries/search', function ($request, $response, $args)
    {
        $body = $request->getParsedBody();
        $search_results = $this->entries->Search($body['search-text']);
        return $response->withJson(['data' => $search_results]);                                           
     });

    
    // GET http://localhost:XXXX/api/entries/username
    $app->get('/{username}/entries', function ($request, $response, $args)
    {
        $username = $args['username'];
        $all_entries = $this->entries->GetEntriesByUsername($username);                                               
        return $response->withJson(['data' => $all_entries]);
    });
    

    // GET http://localhost:XXXX/api/entries/user/id
    $app->get('/entries/user/{id}', function ($request, $response, $args)
    {
        $id = $args['id'];
        $all_entries = $this->entries->GetEntriesByUserID($id);                                               
        return $response->withJson(['data' => $all_entries]);
    });

    
    //---------------------------------------------------------------------------------------------
    // Comments
    //---------------------------------------------------------------------------------------------
    
    // GET http://localhost:XXXX/api/comments
    $app->get('/comments', function ($request, $response, $args)
    {
        $all_comments = $this->comments->GetAllComments();                                               //This should be limited to 20 later on
        return $response->withJson(['data' => $all_comments]);
    });

    // GET http://localhost:XXXX/api/comments/id
    $app->get('/comments/{id}', function ($request, $response, $args)
    {
        $id = $args['id'];
        $comment = $this->comments->GetCommentsByID($id);
        return $response->withJson(['data' => $comment]);
    });

    // POST http://localhost:XXXX/api/comments
    $app->post('/comments', function ($request, $response, $args)
    {
        $body = $request->getParsedBody();
        $new_comment = $this->comments->PostComment($body);
        return $response->withJson(['data' => $new_comment]);                                           
     });

    // DELETE http://localhost:XXXX/api/comments/id
    $app->delete('/comments/{id}', function ($request, $response, $args) 
    {
        $id = $args['id'];
        $comment = $this->comments->DeleteComment($id);
        return $response->withJson(['data' => $comment]);
    });
});

$app->run();
