<?php
declare(strict_types=1);

/**
 * ============================================================================
 * CHAROTAR UNIVERSITY OF SCIENCE AND TECHNOLOGY (CHARUSAT)
 * FACULTY OF TECHNOLOGY AND ENGINEERING (FTE)
 * SUBJECT: WEB DEVELOPMENT FRAMEWORKS (ITUE203)
 * ============================================================================
 * File: db.php
 * Description: Secure Database Connection Manager supporting both PHP PDO
 *              and MySQLi with Prepared Statements, Stored Procedure execution,
 *              and robust error handling.
 * Practical Mapping:
 *   - Practical 8: MySQL Schema, ER Model, PDO Connectivity, Prepared Statements,
 *                  and Stored Procedures (Advanced Extension).
 *   - Practical 9: User Registration with Database Insert & Password Hashing.
 *   - Practical 10: Secure Login/Logout with Sessions.
 * ============================================================================
 */

// Database Configuration Constants
defined('DB_HOST')    || define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
defined('DB_NAME')    || define('DB_NAME', getenv('DB_NAME') ?: 'student_hub');
defined('DB_USER')    || define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS')    || define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

/**
 * Class Database
 * Singleton connection provider for PDO and MySQLi
 */
class Database
{
    private static ?PDO $pdoInstance = null;
    private static ?mysqli $mysqliInstance = null;

    /**
     * Get secure PDO Connection instance (Practical 8 Intermediate Extension)
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getPDO(): PDO
    {
        if (self::$pdoInstance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$pdoInstance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[StudentHub DB Error] PDO Connection failed: ' . $e->getMessage());
                throw new PDOException('Database connection failed. Please ensure MySQL service is running in XAMPP: ' . $e->getMessage());
            }
        }
        return self::$pdoInstance;
    }

    /**
     * Get secure MySQLi Connection instance (Practicals 8 & 9)
     *
     * @return mysqli
     * @throws mysqli_sql_exception
     */
    public static function getMySQLi(): mysqli
    {
        if (self::$mysqliInstance === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
                $mysqli->set_charset(DB_CHARSET);
                self::$mysqliInstance = $mysqli;
            } catch (mysqli_sql_exception $e) {
                error_log('[StudentHub DB Error] MySQLi Connection failed: ' . $e->getMessage());
                throw new mysqli_sql_exception('MySQLi connection failed. Please verify credentials: ' . $e->getMessage());
            }
        }
        return self::$mysqliInstance;
    }

    /**
     * Execute a prepared PDO statement with parameter binding (Practical 8)
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Positional or named parameters
     * @return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all records as an associative array using prepared PDO
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single record as an associative array using prepared PDO
     *
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute INSERT/UPDATE/DELETE and return last insert ID or affected rows
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    public static function execute(string $sql, array $params = []): int
    {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $lastId = (int) $pdo->lastInsertId();
        return $lastId > 0 ? $lastId : $stmt->rowCount();
    }

    /**
     * Execute a MySQL Stored Procedure (Practical 8 Advanced Extension)
     *
     * @param string $procedureName Name of the procedure
     * @param array $inParams Indexed array of input values
     * @param array $outParams List of OUT variable names without @ (e.g. ['res_code', 'res_msg'])
     * @return array Associative array of OUT parameter values or result set
     */
    public static function callProcedure(string $procedureName, array $inParams = [], array $outParams = []): array
    {
        $pdo = self::getPDO();

        $placeholders = [];
        $execParams = [];
        $outVars = [];

        // Build IN placeholders
        foreach ($inParams as $val) {
            $placeholders[] = '?';
            $execParams[] = $val;
        }

        // Build OUT placeholders as user variables @var
        foreach ($outParams as $varName) {
            $userVar = '@' . preg_replace('/[^a-zA-Z0-9_]/', '', $varName);
            $placeholders[] = $userVar;
            $outVars[] = $userVar;
        }

        $sql = sprintf('CALL `%s`(%s)', $procedureName, implode(', ', $placeholders));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($execParams);

        // If OUT parameters requested, fetch them via SELECT @var1, @var2
        if (!empty($outVars)) {
            $selectSql = 'SELECT ' . implode(', ', $outVars);
            $outStmt = $pdo->query($selectSql);
            $rawOut = $outStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $cleanOut = [];
            foreach ($rawOut as $k => $v) {
                $cleanOut[ltrim((string)$k, '@')] = $v;
            }
            return $cleanOut;
        }

        // Otherwise return any result sets
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Backward-compatible procedural function for legacy scripts (login.php, register.php)
 *
 * @return mysqli
 */
function getDatabaseConnection(): mysqli
{
    return Database::getMySQLi();
}
