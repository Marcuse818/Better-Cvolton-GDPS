<?php
    require_once __DIR__ .'/../../config/connection.php';

    class Database extends ConnectionConfig  {
        private $db;

        public function open_connection() {
            try {
                
                $validate_port = (!isset($this->port) || $this->port == '') ? 3306 : $this->port;

                $this->db = new PDO("mysql:host=". $this->servername . ";port=$validate_port;dbname=" . $this->dbname, $this->username, $this->password, array(
                    PDO::ATTR_PERSISTENT => true
                ));
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
                return $this->db;
            }
            catch(PDOException $e)
            {
                echo "Connection failed: " . $e->getMessage();
            }
        }

        public function close_connection($db) {
            $this->db = null;
        }
    }
