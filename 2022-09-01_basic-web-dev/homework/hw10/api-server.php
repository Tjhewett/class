<?php
header('Content-type: application/json');

// For debugging:
error_reporting(E_ALL);
ini_set('display_errors', '1');

// TODO Change this as needed. SQLite will look for a file with this name, or
// create one if it can't find it.
$dbName = 'data.db';

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

// Put your other code here.

createTables();

// Handle incoming requests.
if(array_key_exists('action', $_POST)){
    $action = $_POST['action'];
    if($action == 'add-quizitem'){
        addQuizItem($_POST);
    } else if($action == 'get-quizitems'){
        getTable('QuizItems');
    } else if($action == 'remove-quizitem'){
        removeQuizItem($_POST);
    } else if($action == 'update-quizitem'){
        updateQuizItem($_POST);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid action: '. $action
        ]);
    }
}


function createTables(){
    global $dbh;

    try{

        // Create the QuizItems table.
        $dbh->exec('create table if not exists QuizItems('. 
            'id integer primary key autoincrement, '. 
            'question text, answer text, created_at datetime default(datetime()), updated_at datetime default(datetime()))');
    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error creating the tables: $e"
        ]);
    }
}

/**
 * Adds a book to the database. Requires the parameters:
 *  - author
 *  - title
 *  - year
 *  - copies
 * @param data An associative array holding parameters and their values.
 */
function addQuizItem($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('insert into QuizItems(question, answer) '.
            'values (:question, :answer)');
        $statement->execute([
            ':question' => $data['question'], 
            ':answer'  => $data['answer']]);

        echo json_encode(['success' => true]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error adding a book: $e"
        ]);
    }
}

function updateQuizItem($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('update QuizItems set question = :question, answer = :answer, updated_at = datetime() where id = :id' );
        $statement->execute([
            ':question' => $data['question'], 
            ':answer'  => $data['answer'],
            ':id'  => $data['quizitem-id'],
        ]);

        echo json_encode(['success' => true]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error adding a book: $e"
        ]);
    }
}

function removeQuizItem($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('delete from QuizItems where id = :id');
        $statement->execute([
            ':id' => $data['quizitem-id']]);

        echo json_encode(['success' => true]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error adding a book: $e"
        ]);
    }
}

/**
 * Adds a patron to the database. Requires the parameters:
 *  - name
 *  - address
 *  - phone-number
 * @param data An associative array holding parameters and their values.
 */
function addPatron($data){
    global $dbh;

    try {
        $statement = $dbh->prepare('insert into Patrons'. 
            '(name, address, phone_number) '.
            'values (:name, :address, :phone_number)');
        $statement->execute([
            ':name' => $data['name'], 
            ':address'  => $data['address'], 
            ':phone_number'   => $data['phone-number']]);

        echo json_encode(['success' => true]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error adding a patron: $e"
        ]);
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

        echo json_encode(['success' => true]);
        
    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error checking out the book: $e"
        ]);
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

        echo json_encode(['success' => true]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error returning the book: $e"
        ]);
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
        echo json_encode([
            'success' => false, 
            'error' => "There was an error finding overdue books: $e"
        ]);
    }
}

/**
 * Outupts the row of the given table that matches the given id.
 */
function getTableRow($table, $data){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table where id = :id");
        $statement->execute([':id' => $data['id']]);
        // Use fetch here, not fetchAll -- we're only grabbing a single row, at 
        // most.
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $row]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]);
    }
}

/**
 * Outputs all the values of a database table. 
 * 
 * @param table The name of the table to display.
 */
function getTable($table){
    global $dbh;
    try {
        $statement = $dbh->prepare("select * from $table");
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);

    } catch(PDOException $e){
        echo json_encode([
            'success' => false, 
            'error' => "There was an error fetching rows from table $table: $e"
        ]);
    }
}
?>