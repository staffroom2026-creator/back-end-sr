<?php 
/*
* PDO Database Class
* Connect to database
* Create prepare statements
* Bind values
* Return rows and results
* Logs database errors internally and returns safe 500 JSON responses without exposing DB internals.
*/

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;

    private $dbh;
    private $stmt;
    private $error;

    public function __construct(){
        require_once dirname(__DIR__, 2) . '/config/database.php';
        $this->dbh = DatabaseConnection::get();
    }

    public function handleResponse($data)
    {
        try {
            if (!isset($data['status_code']) || !isset($data['status']) || !isset($data['message'])) {
                throw new Exception("Invalid input data. 'status' and 'message' keys are required.");
            }

            $response = [
                'success' => $data['status'],
                'status' => $data['status'],
                'message' => $data['message'],
            ];

            http_response_code($data['status_code']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            error_log("Internal Error in handleResponse: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'status' => false,
                'message' => 'Internal Server Error: A system error occurred.'
            ]);
            exit;
        }
    }

    private function logAndFail($exception)
    {
        $this->error = $exception->getMessage();
        error_log("Database Error: " . $this->error . " in " . $exception->getFile() . " on line " . $exception->getLine());
        
        $res = [
            'status_code' => 500,
            'status' => false,
            'message' => 'Internal Server Error: A database operation failed.'
        ];
        $this->handleResponse($res);
        exit;
    }

    // Prepare Statement
    public function query($sql){
        try {
            $this->stmt = $this->dbh->prepare($sql);
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    // Bind values
    public function bind($param, $value, $type = null){
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }

        try {
            $this->stmt->bindValue($param, $value, $type);
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    // Execute the prepared statement
    public function execute(){
        try {
            return $this->stmt->execute();
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    // Get result set as array of objects
    public function resultSet(){
        try {
            $this->execute();
            return $this->stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    // Get single record as object
    public function single(){
        try {
            $this->execute();
            return $this->stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    public function lastInsertId() {
        try {
            return $this->dbh->lastInsertId();
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    public function rowCount(){
        try {
            return $this->stmt->rowCount();
        } catch (PDOException $e) {
            $this->logAndFail($e);
        }
    }

    public function beginTransaction()
    {
        return $this->dbh->beginTransaction();
    }

    public function commit()
    {
        return $this->dbh->commit();
    }

    public function rollBack()
    {
        return $this->dbh->rollBack();
    }
}
?>
