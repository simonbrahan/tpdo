<?php
namespace Tpdo;

use PHPUnit\Framework\TestCase;
use PDO;

class TpdoTest extends TestCase
{
    public function testRunOnceWithSingleValues()
    {
        $db = $this->getTpdo();
        $this->resetDb($db);
        $db->run('insert into test (val) values (?), (?)', array(2, 3));
        $res = $db->run('select * from test order by val asc');

        $this->assertEquals((object) array('val' => 2), $res->fetch());
        $this->assertEquals((object) array('val' => 3), $res->fetch());
        $this->assertFalse($res->fetch());
    }

    public function testRunOnceWithArrayValues()
    {
        $db = $this->getTpdo();
        $this->resetDb($db);
        $db->run('insert into test (val) values (?), (?), (?)', array(5, 6, 7));

        $res = $db->run(
            'select * from test where val = ? or val in ([?]) or val = ? order by val asc',
            array(0, array(6, 7), 10)
        );

        $this->assertEquals((object) array('val' => 6), $res->fetch());
        $this->assertEquals((object) array('val' => 7), $res->fetch());
        $this->assertFalse($res->fetch());
    }

    public function testRunFailsWithMismatchedQueryAndParams()
    {
        $db = $this->getTpdo();

        $this->setExpectedException(
            'Tpdo\Exception',
            'Found [?] in query, but parameter is not an array'
        );

        $db->run('select * from test where val = [?]', array('not an array'));
    }

    public function testArrayTokenIgnoredInQuotedValue()
    {
        $db = $this->getTpdo();
        $this->resetDb($db);
        $db->run('insert into test (val) values (?)', array('[?]'));

        $res = $db->run(
            'select * from test where val = "du\\"mmy" or val = ? or val = "[?]"',
            array('other dummy')
        );

        $this->assertEquals((object) array('val' => '[?]'), $res->fetch());
        $this->assertFalse($res->fetch());
    }

    public function testSupportsNamedParameters()
    {
        $db = $this->getTpdo();
        $this->resetDb($db);
        $db->run('insert into test (val) values (?), (?)', array('val1', 'val2'));

        $res = $db->run(
            'select * from test where val in (:val1, :val2) order by val',
            array(':val1' => 'val1', ':val2' => 'val2')
        );

        $this->assertEquals((object) array('val' => 'val1'), $res->fetch());
        $this->assertEquals((object) array('val' => 'val2'), $res->fetch());
        $this->assertFalse($res->fetch());
    }

    private function getTpdo()
    {
        require_once __DIR__ . '/../src/Tpdo.php';
        require_once __DIR__ . '/../src/Exception.php';

        return new Tpdo(
            'mysql:dbname=test',
            'test',
            'test',
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
            )
        );
    }

    private function resetDb(Tpdo $db)
    {
        $db->run('drop table if exists test');
        $db->run('create table test (val varchar(255) default null)');
    }
}
