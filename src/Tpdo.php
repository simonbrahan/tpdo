<?php
namespace Tpdo;

use PDO;

class Tpdo extends PDO
{
    /**
     * Run a query on the database. Does not currently support named parameters.
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
        list($query, $params) = $this->expandArrayParams($query, $params);

        $q = parent::prepare($query);

        $res = $q->execute($params);

        return $q;
    }

    private function expandArrayParams($query, $params)
    {
        $unkeyed_params = array_values($params);

        $matches = array();
        preg_match_all('/\[?\?\]?/', $query, $matches, PREG_OFFSET_CAPTURE);

        $matches = reset($matches);

        if (empty($matches)) {
            return array($query, $unkeyed_params);
        }

        $q_searches = array();
        $q_replacements = array();
        foreach ($matches as $idx => $match) {
            list ($pattern, $offset) = $match;

            if ($pattern == '?') {
                continue;
            }

            if (!is_array($unkeyed_params[$idx])) {
                throw new Exception('Found [?] in query, but parameter is not an array');
            }

            $q_searches[] = '[?]';
            $q_replacements[] = implode(', ', array_fill(0, count($unkeyed_params[$idx]), '?'));
            array_splice($unkeyed_params, $idx, 1, $unkeyed_params[$idx]);
        }

        $expanded_query = str_replace($q_searches, $q_replacements, $query);

        return array($expanded_query, $unkeyed_params);
    }
}
