<?php

/**
 * This is a database wrapper based on PDO.
 */
class Tuffy_Database extends PDO {
    // Static database stuff.
    /**
     * The shared database connection instance. This is created if you use
     * Tuffy_Database::configure, or set it yourself.
     */
    public static $db;

    /**
     * Creates the shared database connection (which is, of course, an
     * instance of Tuffy_Database).
     *
     * This method will be called automatically if you define the
     * `modules.Tuffy_Database` setting to be TRUE. (It will obtain the
     * DSN and credentials from the `database.dsn`, `database.username`,
     * and `database.password` settings.)
     *
     * @param string $dsn The PDO Data Source Name to connect with.
     * @param string $username The database's username, if necessary.
     * @param string $password The database's password, if necessary.
     */
    public static function configure ($dsn = NULL, $username = '',
                                      $password = '') {
        if ($dsn === NULL) {
            $dsn = Tuffy::setting('database.dsn');
            $username = Tuffy::setting('database.username', '');
            $password = Tuffy::setting('database.password', '');
        }
        Tuffy::debug("Connecting to $dsn", "");
        self::$db = new self($dsn, $username, $password);
    }

    /**
     * This returns the character used to quote identifiers for the given
     * database.
     *
     * @param string $dbName The database name, as stored in the PDO attribute
     * PDO::ATTR_DRIVER_NAME.
     */
    public static function getIdentifierQuoteCharacter ($dbName) {
        // Borrowed from Idiorm by Jamie Matthews.
        switch($dbName) {
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                return '"';
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                return '`';
        }
    }

    // PDO method overrides.
    
    private $identifierQuoteCharacter;

    /**
     * Initializes the database connection and configures a few settings.
     *
     * @param string $dsn The PDO Data Source Name to connect with.
     * @param string $username The database's username, if necessary.
     * @param string $password The database's password, if necessary.
     * @param array $driverOpts Extra driver options to pass to the PDO
     * constructor.
     */
    public function __construct ($dsn, $username = '', $password = '',
                                 $driverOpts = array()) {
        parent::__construct($dsn, $username, $password, $driverOpts);
        $this->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS,
                            array('Tuffy_Database_Statement', array($this)));
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->identifierQuoteCharacter = self::getIdentifierQuoteCharacter(
            $this->getAttribute(PDO::ATTR_DRIVER_NAME)
        );
    }

    /**
     * Runs a query on the results and returns the Tuffy_Database_Statement
     * associated with it.
     *
     * @param string $sql The SQL statement to run as a query.
     * @param array $params Parameters to bind to the statement.
     * @see Tuffy_Statement::bind
     */
    public function query ($sql, $params = NULL) {
        $stmt = $this->prepare($sql);
        if (func_num_args() > 2) {
            $args = func_get_args();
            call_user_func_array(array($stmt, 'setFetchMode'),
                                 array_slice($args, 2));
        }
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Runs a query, and returns the number of rows affected. (This should be
     * a query with side effects, otherwise it makes no sense.)
     *
     * @param string $sql The SQL statement to run.
     * @param array $params Parameters to bind to the statement.
     * @see Tuffy_Statement::bind
     */
    public function exec ($sql, $params = NULL) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // Basic SQL operations.
    public function insert ($table, $data, $replace = FALSE) {
        return $this->exec(($replace ? "REPLACE INTO " : "INSERT INTO ") .
                           $this->quoteIdentifier($table) . " " .
                           $this->setClause($data), $data);
    }

    // SQL helpers.

    /**
     * Quotes an identifier for this database. It uses the quote character
     * preferred by the database.
     *
     * @param string $identifier The identifier to quote.
     * @param boolean $split Whether to split the identifier on dots before
     * quoting. (This is the default. It could lead to bizarre SQL injections
     * if you pass user input directly in.)
     */
    public function quoteIdentifier ($identifier, $split = TRUE) {
        if ($split && strpos($identifier, '.') !== FALSE) {
            return implode('.', array_map(explode('.', $identifier),
                                          array($this, '_quoteIdentifier')));
        }
        return $this->_quoteIdentifier($identifier);
    }

    protected function _quoteIdentifier ($identifier) {
        if ($identifier === '*') return $identifier;
        $ch = $this->identifierQuoteCharacter;
        return $ch . $identifier . $ch;
    }
    
    public function setClause ($data) {
        $sets = array();
        foreach (array_keys($data) as $col) {
            $sets[] = $this->quoteIdentifier($col) . " = :" . $col;
        }
        return 'SET ' . implode(', ', $sets);
    }
}


/**
 * This is the statement class generated by Tuffy_Database::prepare() and
 * returned by Tuffy_Database::query().
 */
class Tuffy_Database_Statement extends PDOStatement {
    private $pdo;

    protected function __construct ($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Binds multiple parameters at once to the database. This will properly
     * handle NULLs even on MySQL, and prepend a `:` to placeholder names for
     * you.
     *
     * @param array $params The parameters to bind.
     */
    public function bind ($params) {
        foreach ($params as $name => $value) {
            if ($value === NULL) {
                $this->bindValue(is_int($name) ? $name + 1 : ':' . $name,
                                 NULL, PDO::PARAM_INT);
            } else {
                $this->bindValue(is_int($name) ? $name + 1 : ':' . $name,
                                 $value);
            }
        }
    }

    /**
     * Executes the statement.
     *
     * @param array $params Parameters to bind before executing the statement.
     * You can use this to simplify running several sequential inserts.
     */
    public function execute ($params = NULL) {
        $debug = Tuffy::setting('debug');
        if ($params) $this->bind($params);
        if ($debug) {
            ob_start();
            $this->debugDumpParams();
            $idx = Tuffy::debug("Query", ob_get_clean());
        }
        parent::execute();
        if ($debug) {
            Tuffy_Debug::completeEvent($idx);
        }
    }
}

