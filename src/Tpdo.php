<?php
namespace Tpdo;

use PDO;

class Tpdo extends PDO
{
    /**
     * Run a query on the database.
     *
     * Equivalent to:
     * $stmnt = $pdo->prepare($query);
     * $stmnt->execute($params);
     * return $stmnt;
     *
     * The function will expand out [?] and its associated array parameter, so:
     *
     * Tpdo::run(
     *     'select * from some_table where val = ? or other_val in ([?])',
     *     array(1, array(2, 3, 4))
     * );
     *
     * Will become:
     *
     * Tpdo::run(
     *     'select * from some_table where val = ? or other_val in (?, ?, ?)',
     *     array(1, 2, 3, 4)
     * );
     *
     * @param string $query
     * @param array $params
     *
     * @return \PDOStatement
     *
     * @throws Exception if query and parameters are mismatched
     */
    public function run($query, $params = array())
    {
        if (!$this->usingNamedParams($params)) {
            list($query, $params) = $this->expandIndexedArrayParams($query, $params);
        } else {
            list($query, $params) = $this->expandNamedParams($query, $params);
        }

        $q = parent::prepare($query);

        $res = $q->execute($params);

        return $q;
    }

    private function usingNamedParams($params)
    {
        return array_keys($params) !== range(0, count($params) - 1);
    }

    private function expandNamedParams($query, $params)
    {
        $matches = array();
        preg_match_all('/\[?:\w+\]?/', $query, $matches, PREG_OFFSET_CAPTURE);

        $matches = reset($matches);

        if (empty($matches)) {
            return array($query, $params);
        }

        /**
         * Code below changes the query text
         * Reverse the matches being considered here, to avoid changing the offsets
         * found by the regex above
         */
        $matches = array_reverse($matches);
        foreach ($matches as $match) {
            list ($pattern, $offset) = $match;

            if ($this->offsetInsideQuotes($query, $offset)) {
                continue;
            }

            if (strpos($pattern, '[') !== 0) {
                continue;
            }

            $param_name = trim($pattern, '[]');

            if (!is_array($params[$param_name])) {
                throw new Exception('Found ' . $pattern . ' in query, but parameter is not an array');
            }

            $val_keys = array();
            foreach ($params[$param_name] as $item) {
                do {
                    $val_key = $param_name . uniqid();
                } while (isset($params[$val_key]));

                $params[$val_key] = $item;
                $val_keys[] = $val_key;
            }

            $param_statement = implode(', ', $val_keys);

            $query = substr($query, 0, $offset) . ' ' .
                     $param_statement . ' ' .
                     substr($query, $offset + strlen($pattern));

            unset($params[$param_name]);
        }

        return array($query, $params);
    }

    private function expandIndexedArrayParams($query, $params)
    {
        $unkeyed_params = array_values($params);

        $matches = array();
        preg_match_all('/\[?\?\]?/', $query, $matches, PREG_OFFSET_CAPTURE);

        $matches = reset($matches);

        if (empty($matches)) {
            return array($query, $unkeyed_params);
        }

        /**
         * Code below changes the query text
         * Reverse the matches being considered here, to avoid changing the offsets
         * found by the regex above
         */
        $matches = array_reverse($matches);
        $param_idx = count($unkeyed_params);

        foreach ($matches as $match) {
            list ($pattern, $offset) = $match;

            if ($this->offsetInsideQuotes($query, $offset)) {
                continue;
            }

            $param_idx -= 1;

            if ($pattern == '?') {
                continue;
            }

            if (!is_array($unkeyed_params[$param_idx])) {
                throw new Exception('Found [?] in query, but parameter is not an array');
            }

            $query = $this->spliceParams($query, $offset, count($unkeyed_params[$param_idx]));

            array_splice($unkeyed_params, $param_idx, 1, $unkeyed_params[$param_idx]);
        }

        return array($query, $unkeyed_params);
    }

    private function offsetInsideQuotes($query, $offset)
    {
        $unused_matches = array();
        $preceeding_quotes = preg_match_all('/[^\\\]"/', substr($query, 0, $offset), $unused_matches);

        return $preceeding_quotes === false || $preceeding_quotes % 2 == 1;
    }

    private function spliceParams($query, $offset, $count)
    {
        $param_statement = implode(', ', array_fill(0, $count, '?'));

        return substr($query, 0, $offset) . ' ' . $param_statement . ' ' . substr($query, $offset + 3);
    }
}
