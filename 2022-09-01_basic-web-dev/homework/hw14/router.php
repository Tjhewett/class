<?php
// File:        router.php
// Author:      YOUR NAME HERE
// Date:        08-Nov-2020
// Purpose:     Demonstrates a an implementation of a RESTful API. Routes
//              requests and simply echos out what the request was in the 
//              controller function that the request maps to.

// If the file being requested exists, load it. This is for running in
// PHP dev mode.
if(file_exists(".". $_SERVER['REQUEST_URI'])){
    return false;
}


header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'library-v1.db';

// Leave this alone. It checks if you have a directory named www-data in
// you home directory (on a *nix server). If so, the database file is
// sought/created there. Otherwise, it uses the current directory.
// The former works on digdug where I've set up the www-data folder for you;
// the latter should work on your computer.
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
$homeDir = count($matches) > 1 ? $matches[1] : '';
$dataDir = "/home/$homeDir/www-data";
if(!file_exists($dataDir)){
    $dataDir = __DIR__;
}
$dbh = new PDO("sqlite:$dataDir/$dbName")   ;
// Set our PDO instance to raise exceptions when errors are encountered.
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

createTables();


    // if($action == 'add-book'){
    //     addBook($_POST);
    // } else if($action == 'add-patron') {
    //     addPatron($_POST);
    // } else if($action == 'checkout-book'){
    //     checkoutBook($_POST);
    // } else if($action == 'return-book'){
    //     returnBook($_POST);
    // } else if($action == 'get-overdue-books'){
    //     getOverDueBooks($_POST);
    // } else if($action == 'get-books'){
    //     getTable('Books');
    // } else if($action == 'get-book'){
    //     getTableRow('Books', $_POST);
    // } else if($action == 'get-patrons'){
    //     getTable('Patrons');
    // } else if($action == 'get-patron'){
    //     getTableRow('Patrons', $_POST);
    // } else if($action == 'get-checkouts'){
    //     getTable('Checkouts');
    // } else if($action == 'get-checkout'){
    //     getTableRow('Checkouts', $_POST);

// Routes.
$routes = [
    // Books.
    makeRoute("POST", "#^/books/?(\?.*)?$#", "addBook"),
    makeRoute("GET", "#^/books/?(\?.*)?$#", "getBooks"),
    makeRoute("GET", "#^/books/(\w+)/?(\?.*)?$#", "getBook"),

    // Patrons -- the handlers for these need to be re-vamped.
    makeRoute("POST", "#^/patrons/?(\?.*)?$#", "addPatron"),
    makeRoute("GET", "#^/patrons/?(\?.*)?$#", "getPatrons"),
    makeRoute("GET", "#^/patrons/(\w+)/?(\?.*)?$#", "getPatron"),


    // Checkouts.
    // TODO
];

// Initial request processing.
// If this is being served from a public_html folder, find the prefix (e.g., 
// /~jsmith/path/to/dir).
$matches = [];
preg_match('#^/~([^/]*)#', $_SERVER['REQUEST_URI'], $matches);
if(count($matches) > 0){
    $matches = [];
    preg_match("#/home/([^/]+)/public_html/(.*$)#", dirname(__FILE__), $matches);
    $prefix = "/~". $matches[1] ."/". $matches[2];
    $uri = preg_replace("#^". $prefix ."/?#", "/", $_SERVER['REQUEST_URI']);
} else {
    $prefix = "";
    $uri = $_SERVER['REQUEST_URI'];
}

// Get the request method; PHP doesn't handle non-GET or POST requests
// well, so we'll mimic them with POST requests with a "_method" param
// set to the method we want to use.
$method = $_SERVER["REQUEST_METHOD"];
$params = $_GET;
if($method == "POST"){
    $params = $_POST;
    if(array_key_exists("_method", $_POST))
        $method = strtoupper($_POST["_method"]);
} 

// Parse the request and send it to the corresponding handler.
$foundMatchingRoute = false;
$match = [];
foreach($routes as $route){
    if($method == $route["method"]){
        preg_match($route["pattern"], $uri, $match);
        if($match){
            error(json_encode($route["controller"]($uri, $match, $params)));
            $foundMatchingRoute = true;
        }
    }
}

if(!$foundMatchingRoute){
    error("No route found for: $method $uri");
}

////////////////////////////////////////////////////////////////////////////////
// FUNCTIONS
////////////////////////////////////////////////////////////////////////////////

/**
 * Creates these tables if they don't already exist:
 * 
 *  Books
 *  Patrons
 *  Checkouts
 * 
 */
function createTables(){
    global $dbh;

    try{

        // Create the Books table.
        $dbh->exec('create table if not exists Books('. 
            'id integer primary key autoincrement, '. 
            'title text, author text, year int, copies int)');

        // Create the Patrons table.
        $dbh->exec('create table if not exists Patrons('. 
            'id integer primary key autoincrement, '. 
            'name text, address text, phone_number text)');

        // Create the Checkouts table.
        $dbh->exec('create table if not exists Checkouts('. 
            'id integer primary key autoincrement, '. 
            'patron_id int, book_id int, '. 
            'checked_out_on date, due_on date, returned_at datetime, '.
            'foreign key (patron_id) references patrons (id),'. 
            'foreign key (book_id) references books (id))');

    } catch(PDOException $e){
        error("There was an error creating the tables: $e");
    }
}

////////////////////////////////////////////////////////////////////////////////
// Handlers
////////////////////////////////////////////////////////////////////////////////


/**
 * Adds a book to the database. Requires the parameters:
 *  - author
 *  - title
 *  - year
 *  - copies
 *
 * @param uri The URI of the request.
 * @param matches An array of matches (unused).
 * @param data An associative array holding parameters and their values.
 */
function addBook($uri, $matches, $data){
    global $dbh, $prefix;

    try {
        $statement = $dbh->prepare('insert into Books(author, title, year, copies) '.
            'values (:author, :title, :year, :copies)');
        $statement->execute([
            ':author' => $data['author'], 
            ':title'  => $data['title'], 
            ':year'   => $data['year'], 
            ':copies' => $data['copies']]);

        created("$prefix/books/". $dbh->lastInsertId(), null);

    } catch(PDOException $e){
        error("There was an error adding a book: $e");
    }
}

/**
 * Gets information about a book.
 *
 * @param uri The URI of the request.
 * @param matches An array of matches -- the second match should be the book id.
 * @param data An associative array holding parameters and their values.
 */
function getBook($uri, $matches, $data){
    global $dbh, $prefix;
    $id = $matches[1];
    getTableRow('Books', ['id' => $id], "$prefix/books/$id");
}

/**
 * Gets a listing of books.
 *
 * @param uri The URI of the request.
 * @param matches An array of matches (unused).
 * @param data An associative array holding parameters and their values.
 */
function getBooks($uri, $matches, $data){
    global $dbh, $prefix;
    getTable('Books', "$prefix/books");
}


////////////////////////////////////////////////////////////////////////////////
// Handlers that need to be converted to REST (these are the ad hoc versions).
////////////////////////////////////////////////////////////////////////////////
/**
 * Adds a patron to the database. Requires the parameters:
 *  - name
 *  - address
 *  - phone-number
 * 
 * @param uri The URI of the request.
 * @param matches An array of matches (unused).
 * @param data An associative array holding parameters and their values.
 */
function addPatron($uri, $matches, $data){
    global $dbh;

    try {
        $statement = $dbh->prepare('insert into Patrons'. 
            '(name, address, phone_number) '.
            'values (:name, :address, :phone_number)');
        $statement->execute([
            ':name' => $data['name'], 
            ':address'  => $data['address'], 
            ':phone_number'   => $data['phone-number']]);

        success(null);

    } catch(PDOException $e){
        error("There was an error adding a patron: $e");
    }
}

/**
 * Checks a book out. Requires the parameters:
 *  - book-id
 *  - patron-id
 *  - due-on (a date)
 * @param data An associative array holding parameters and their values.
 */
function checkoutBook($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('insert into Checkouts'. 
            '(patron_id, book_id, checked_out_on, due_on) '.
            'values (:patron_id, :book_id, date(\'now\'), :due_on)');
        $statement->execute([
            ':patron_id' => $data['patron-id'], 
            ':book_id'  => $data['book-id'], 
            ':due_on'   => $data['due-on']]);

        success(null);
        
    } catch(PDOException $e){
        error("There was an error checking out the book: $e");
    }
}

/**
 * Returns a book. Requires the parameters:
 *  - checkout-id
 * @param data An associative array holding parameters and their values.
 */
function returnBook($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('update Checkouts '. 
            'set returned_at = datetime(\'now\') '.
            'where id = :id');
        $statement->execute([
            ':id' => $data['checkout-id']]);

        success(null);

    } catch(PDOException $e){
        error("There was an error returning the book: $e");
    }
}

/**
 * Outputs a list of books that are over due, including details about the book
 * (author, title, year, and copies) and the patron (name, address, and 
 * phone number).
 */
function getOverDueBooks(){
    global $dbh;
    try {
        $statement = $dbh->prepare('select * from Checkouts '. 
            'join Patrons on Patrons.id = patron_id '.
            'join Books on Books.id = book_id '.
            'where returned_at is null and due_on < date(\'now\')');
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);

    } catch(PDOException $e){
        error("There was an error finding overdue books: $e");
    }
}

////////////////////////////////////////////////////////////////////////////////
// Helper funcitons
////////////////////////////////////////////////////////////////////////////////

/**
 * Outupts the row of the given table that matches the given id.
 */
function getTableRow($table, $data, $uri){
    global $dbh;

    try {
        $statement = $dbh->prepare("select * from $table where id = :id");
        $statement->execute([':id' => $data['id']]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if(!$row){
            notFound($data['id']);
        }

        $row['uri'] = $uri;
        success($row);

    } catch(PDOException $e){
       error("There was an error fetching rows from table $table: $e");
    }
}

/**
 * Outputs all the values of a database table. 
 * 
 * @param table The name of the table to display.
 */
function getTable($table, $uriPrefix){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table");
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){
            $row['uri'] = "$uriPrefix/${row['id']}";
        }
        success($rows);

    } catch(PDOException $e){
        error("There was an error fetching rows from table $table: $e");
    }
}

/**
 * Emits a 200 response along with a JSON object with two fields:
 *   - success => true
 *   - data => the data that was passed in as `$data`
 * 
 * @param $data The value to assign to the `data` field of the output.
 */
function success($data){
    $response = ['success' => true];
    if($data){
        $response['data'] = $data;
    }
    die(json_encode($response));
}

/**
 * Emits a 201 Created response along with a JSON object with two fields:
 *   - success => true
 *   - data => the data that was passed in as `$data`
 * Sets the "Location" field of the header to the given URI.
 * 
 * @param $uri The URI of the created resource.
 * @param $data The value to assign to the `data` field of the output.
 */
function created($uri, $data){
    http_response_code(201);
    header("Location: $uri");
    $response = ['success' => true];
    if($data){
        $response['data'] = $data;
    }
    die(json_encode($response));
}

/**
 * Emits a 500 response along with a JSON object with two fields:
 *   - success => false
 *   - error => an error message`
 * 
 * @param $error The value to assign to the `error` field of the output.
 */
function error($error){
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => $error
    ]));
}

/**
 * Emits a 404 response along with a JSON object with two fields:
 *   - success => false
 *   - error => an error message`
 * 
 * @param $error The value to assign to the `error` field of the output.
 */
function notFound($error){
    http_response_code(404);
    die(json_encode([
        'success' => false,
        'error' => $error
    ]));
}


/**
 *  Creates a map with three keys pointing the the arguments passed in:
 *      - method => $method
 *      - pattern => $pattern
 *      - controller => $function
 * 
 * @param method The http method for this route.
 * @param pattern The pattern the URI is matched against. Include groupings
 *                around ids, etc.
 * @param function The name of the function to call.
 * @return A map with the key,value pairs described above.
 */
function makeRoute($method, $pattern, $function){
    return [
        "method" => $method,
        "pattern" => $pattern,
        "controller" => $function
    ];
}


?>