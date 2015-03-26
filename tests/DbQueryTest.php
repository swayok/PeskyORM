<?php

namespace Db;

use Db\Model\AppModel;

require_once 'DbQuery.php';

class DbQueryTest extends DbQuery {

    /** @var Model  */
    static $testModel;

    static public function runTests() {
        self::$testModel = AppModel::VideoModel();
        echo '<h1>Columns</h1>';
        self::testColumns();
        echo '<h1>Conditions</h1>';
        self::testConditions();
        echo '<h1>Group By</h1>';
        self::testGroupBy();
        echo '<h1>Order By</h1>';
        self::testOrder();
        echo '<h1>Limit Offset</h1>';
        self::testLimitOffset();
        echo '<h1>Joins</h1>';
        self::testJoins();
        echo '<h1>Records Processing</h1>';
        self::testRecordsProcessing();
        echo '<h1>Insert, Insert Many, Update, Delete</h1>';
        self::testRecordsManagement();
    }

    static public function createTestBuilder() {
        return self::create(self::$testModel);
    }

    static protected function testColumns() {
        $query = self::createTestBuilder();
        // no columns
        echo '<h2>No Columns</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields('')->buildQuery());
        dpr('$query->columns("")', $res, $res == $test ? 'Ok' : 'Fail');
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields(null)->buildQuery());
        dpr('$query->columns(null)', $res, $res == $test ? 'Ok' : 'Fail');
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields(array())->buildQuery());
        dpr('$query->columns(null)', $res, $res == $test ? 'Ok' : 'Fail');
        try {
            $res = 'no query';
            $res = trim($query->fields(array(''))->buildQuery());
            dpr('$query->columns(array(""))', $res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->fields(): Empty field name detected';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        // single column
        echo '<h2>1 Column</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields('id')->buildQuery());
        dpr('$query->columns("id")', $res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->fields(array('id'))->buildQuery());
        dpr('$query->columns(array("id"))', $res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->fields(array('Video.id'))->buildQuery());
        dpr('$query->columns(array("Video.id"))', $res, $res == $test ? 'Ok' : 'Fail');
        // column with alias
        $test = 'SELECT "Video"."id" AS "__Video__test" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields(array('test' => 'Video.id'))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // distinct
        echo '<h2>Distinct</h2>';
        $test = 'SELECT DISTINCT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->fields('id')->distinct()->buildQuery());
        dpr('$query->columns("id")', $res, $res == $test ? 'Ok' : 'Fail');
        $query->distinct(false);
        // all columns
        echo '<h2>All Columns</h2>';
        $test = '%SELECT "Video"."id" AS "__Video__id", "Video"."user_id" AS "__Video__user_id"(,\s.+?) FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC%is';
        $res = trim($query->fields('*')->buildQuery());
        dpr($res, preg_match($test, $res) ? 'Ok' : 'Fail');
        echo '<h2>Invalid</h2>';
        // unknown alias
        try {
            $res = 'no query';
            $res = trim($query->fields('id', 'User')->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->fields(): Unknown table alias: [User]';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        // unknown column
        try {
            $res = 'no query';
            $res = trim($query->fields('qqq')->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->disassembleField(): unknown column [qqq] in table [public.videos]';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
    }

    static protected function testConditions() {
        $query = self::createTestBuilder();
        $query->fields('id');
        // nothing
        echo '<h2>Empty</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where()->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where(array())->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where('')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where('  ')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where(0)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where(false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // true
        echo '<h2>True</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(true)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where(1)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where('1')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // 1 - plain condition
        echo '<h2>Plain</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "id" = \'1\'  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where('`id` = ``1``')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" = \'1\'  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where('`Video.id` = ``1``')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->where(\Db\DbExpr::create('`Video.id` = ``1``'))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" = \'1\' AND "Video"."id" = \'1\' AND "id" = \'1\'  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(array(\Db\DbExpr::create('`Video.id` = ``1``'), '`Video.id` = ``1``', '`id` = ``1``'))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // 2 & 2.1 & 2.2 - column => value
        echo '<h2>array(column => value)</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" = \'1\' AND "Video"."user_id" IS NULL AND "Video"."duration" > \'100\' AND "Video"."duration" < \'100.11\' AND "Video"."is_processing" = FALSE AND "Video"."duration" IS NOT NULL AND "Video"."is_processing" != FALSE  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(array(
            'Video.id' => '1',
            'user_id' => null,
            'duration >' => 100,
            'duration <' => 100.11,
            'is_processing' => false,
            'duration NOT' => null,
            'is_processing NOT' => false,
            '  ', //< hehe
        ))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        try {
            $res = 'no query';
            $res = trim($query->where(array(
                ' >=' => 1,
            ))->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->assembleConditions(): empty column name in [ >=]';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        // 2.4 - column => array(value1, value2)
        echo '<h2>array(column => array(value1, value2))</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" IN (\'1\',\'2\',\'3\') AND "Video"."id" IN (\'1\',\'2\',\'3\') AND "Video"."id" IN (\'1\',\'2\',\'3\') AND "Video"."id" NOT IN (\'1\',\'2\',\'3\') AND "Video"."id" NOT IN (\'1\',\'2\',\'3\') AND "Video"."id" NOT IN (\'1\',\'2\',\'3\')  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(array(
            'id' => array('1', '2', '3'),
            'id =' => array('1', '2', '3'),
            'id IN' => array('1', '2', '3'),
            'id !=' => array('1', '2', '3'),
            'id NOT' => array('1', '2', '3'),
            'id NOT IN' => array('1', '2', '3'),
        ))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        try {
            $res = 'no query';
            $res = trim($query->where(array(
                'id >=' => array('1', '2', '3'),
            ))->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->assembleConditions(): cannot use operator [>=] to compare with array of values';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        // 2.3 - between
        echo '<h2>Between => array()</h2>';
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" BETWEEN \'1\' AND \'2\' AND "Video"."id" NOT BETWEEN \'1\' AND \'2\'  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(array(
            'id BETWEEN' => array('1', '2'),
            'id NOT BETWEEN' => array('1', '2'),
        ))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        try {
            $res = 'no query';
            $res = trim($query->where(array(
                'id BETWEEN' => array('1', '2', '3'),
            ))->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->assembleConditions(): incorrect amount of values provided for BETWEEN condition';
            dpr($res, $exc->getMessage(), stristr($exc->getMessage(), $test) ? 'Ok' : 'Fail');
        }
        // 2.4 - nested conditions
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  WHERE "Video"."id" IN (\'1\',\'2\',\'3\') AND ("Video"."id" IN (\'1\',\'2\',\'3\') OR "Video"."id" IN (\'1\',\'2\',\'3\') OR ("Video"."id" NOT IN (\'1\',\'2\',\'3\') AND "Video"."id" NOT IN (\'1\',\'2\',\'3\')) OR ("Video"."id" NOT IN (\'1\',\'2\',\'3\') AND "Video"."id" NOT IN (\'1\',\'2\',\'3\'))) AND ("Video"."id" NOT IN (\'1\',\'2\',\'3\') AND "Video"."id" IN (\'1\',\'2\',\'3\'))  ORDER BY  "Video"."id" ASC';
        $res = trim($query->where(array(
            'id' => array('1', '2', '3'),
            'OR' => array(
                'id =' => array('1', '2', '3'),
                'id IN' => array('1', '2', '3'),
                'AND' => array(
                    'id !=' => array('1', '2', '3'),
                    'id NOT' => array('1', '2', '3'),
                ),
                array(
                    'id !=' => array('1', '2', '3'),
                    'id NOT' => array('1', '2', '3'),
                )
            ),
            'AND' => array(
                'id NOT IN' => array('1', '2', '3'),
                'id IN' => array('1', '2', '3'),
            )
        ))->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
    }

    static protected function testOrder() {
        $query = self::createTestBuilder();
        $query->fields('id');
        // 1 column ordering
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."user_id" ASC';
        $res = trim($query->orderBy('user_id', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy('Video.user_id', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy('Video.user_id ASC', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy(array('user_id'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."user_id" DESC';
        $res = trim($query->orderBy('user_id DESC', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy('Video.user_id DESC', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy(array('user_id DESC'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // cleanup
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->orderBy(null, false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy(false, false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy(array(), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');

        // bad arg
        try {
            $res = 'no query';
            $res = trim($query->orderBy(new \stdClass(), false)->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'Something wrong passed as $orderBy arg';
            dpr($res, $exc->getMessage(), stristr($exc->getMessage(), $test) ? 'Ok' : 'Fail');
        }
        // multiple cols ordering
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."user_id" ASC ,  "Video"."is_processing" DESC';
        $res = trim($query->orderBy(array('user_id', 'is_processing DESC'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->orderBy(array('Video.user_id', 'Video.is_processing DESC'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // order by RANDOM()
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  RANDOM() ASC';
        $res = trim($query->orderBy(\Db\DbExpr::create('RANDOM()'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
    }

    static public function testGroupBy() {
        $query = self::createTestBuilder();
        $query->fields('id');
        // 1 column grouping
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  GROUP BY "Video"."user_id"';
        $res = trim($query->groupBy('user_id', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->groupBy('Video.user_id', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // append column && 2 columns
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  GROUP BY "Video"."user_id", "Video"."id"';
        $res = trim($query->groupBy('id')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->groupBy(null)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->groupBy(array('user_id', 'id'), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // cleanup
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->groupBy('', false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->groupBy(array(), false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->groupBy(null, false)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // bad arg
        try {
            $res = 'no query';
            $res = trim($query->groupBy(new \stdClass(), false)->buildQuery());
            dpr($res, 'Fail');
        } catch (DbQueryException $exc) {
            $test = 'Something wrong passed as $columns arg';
            dpr($res, $exc->getMessage(), stristr($exc->getMessage(), $test) ? 'Ok' : 'Fail');
        }
    }

    static protected function testLimitOffset() {
        $query = self::createTestBuilder();
        $query->fields('id');
        // invalid values
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->limit(null)->offset(null)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit('')->offset('')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit('text')->offset('text')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit(array())->offset(array())->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit(0)->offset(0)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit(-1)->offset(-1)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit('123pp')->offset('123pp')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // valid values
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC   LIMIT 10  OFFSET 10';
        $res = trim($query->limit(10)->offset(10)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit('10')->offset('10')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit(10.123)->offset(10.123)->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $res = trim($query->limit('10.123')->offset('10.123')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
    }

    static protected function testJoins() {
        $query = self::createTestBuilder();
        $query->fields('id');
        $usersModel = AppModel::UserModel();
        // 1st join v1 - no table2 alias
        $test = 'SELECT "Video"."id" AS "__Video__id", "Owner"."id" AS "__Owner__id" FROM "public"."videos" AS "Video"  LEFT JOIN "public"."users" AS "Owner" ON ("Owner"."id"="Video"."user_id")  ORDER BY  "Video"."id" ASC';
        $res = trim($query->join($usersModel, 'Owner', 'id', null, 'user_id', 'id', 'left')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        try {
            $res = 'no query';
            trim($query->join($usersModel, 'Owner', 'id', 'Video', 'user_id', 'id', 'left')->buildQuery());
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->join(): table alias [Owner] already used';
            dpr($exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        // delete join
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->removeJoin('Owner')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // 1st join v2 - with table2 alias, no join type
        $test = 'SELECT "Video"."id" AS "__Video__id", "Owner"."id" AS "__Owner__id" FROM "public"."videos" AS "Video"  INNER JOIN "public"."users" AS "Owner" ON ("Owner"."id"="Video"."user_id")  ORDER BY  "Video"."id" ASC';
        $res = trim($query->join($usersModel, 'Owner', 'id', 'Video', 'user_id', 'id')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        $query->removeJoin('Owner');
        // test exceptions on empty columns
        try {
            $res = 'no query';
            $res = trim($query->join($usersModel, 'Owner', null, 'Video', 'user_id', 'id', 'left')->buildQuery());
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->join(): $relatedColumn is empty';
            dpr($res, $exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        try {
            $res = 'no query';
            trim($query->join($usersModel, 'Owner', 'id', 'Video', null, 'id', 'left')->buildQuery());
        } catch (DbQueryException $exc) {
            $test = 'DbQuery->join(): $table2Column is empty';
            dpr($exc->getMessage(), $exc->getMessage() == $test ? 'Ok' : 'Fail');
        }
        $query->removeJoin('Owner');
        // 1st join v3 - no table 1 alias
        $test = 'SELECT "Video"."id" AS "__Video__id", "User"."id" AS "__User__id" FROM "public"."videos" AS "Video"  INNER JOIN "public"."users" AS "User" ON ("User"."id"="Video"."user_id")  ORDER BY  "Video"."id" ASC';
        $res = trim($query->join($usersModel, null, 'id', 'Video', 'user_id', 'id')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // append join
        $test = 'SELECT "Video"."id" AS "__Video__id", "User"."id" AS "__User__id", "Owner"."id" AS "__Owner__id" FROM "public"."videos" AS "Video"  INNER JOIN "public"."users" AS "User" ON ("User"."id"="Video"."user_id")  INNER JOIN "public"."users" AS "Owner" ON ("Owner"."id"="Video"."user_id")  ORDER BY  "Video"."id" ASC';
        $res = trim($query->join($usersModel, 'Owner', 'id', 'Video', 'user_id', 'id')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
        // delete all joins
        $test = 'SELECT "Video"."id" AS "__Video__id" FROM "public"."videos" AS "Video"  ORDER BY  "Video"."id" ASC';
        $res = trim($query->removeJoin('all')->buildQuery());
        dpr($res, $res == $test ? 'Ok' : 'Fail');
    }

    static protected function testRecordsProcessing() {
        $query = self::createTestBuilder();
        $records = $query->fields('*')
            ->join(AppModel::UserModel(), 'Owner', 'id', null, 'user_id', '*', 'left')
            ->limit(3)
            ->find();
        dpr($records, !empty($records[0]['Video']) && (!empty($records[0]['Video']['Owner'])) ? 'Ok' : 'Fail');
        $record = $query->findOne();
        dpr($record, !empty($record['id']) && (!empty($record['Owner'])) ? 'Ok' : 'Fail');
    }

    static protected function testRecordsManagement() {
        echo '<h2>create/update/delete 1 record</h2>';
        // create 1 record
        $tokensModel = AppModel::UserTokenModel();
        $tokensModel->begin();
        $user = self::create(AppModel::UserModel())->findOne();
        dpr($user);
        $record = array(
            'token' => sha1('test1'),
            'user_id' => $user['id'],
            'user_agent' => 'DbQueryTest',
            'created' => date('Y-m-d H:i:s'),
            'remember' => false
        );
        self::create($tokensModel)->where(array('token' => $record['token']))->delete(); //< in case when token was not deleted
        $token = self::create($tokensModel)->insert($record, false);
        dpr($token, $token == $record['token'] ? 'Ok' : 'Fail');
        $utoken = self::create($tokensModel)->where(array('token' => $record['token']))->findOne();
        dpr($utoken, !empty($utoken) ? 'Ok' : 'Fail');
        // update 1 record
        $updatedRecords = self::create($tokensModel)->where(array('token' => $record['token']))->update(array('user_agent' => 'DbQueryTest-update'));
        dpr('Updated:' . $updatedRecords, $updatedRecords === 1 ? 'Ok' : 'Fail');
        $utoken = self::create($tokensModel)->where(array('token' => $record['token']))->findOne();
        dpr($utoken, $utoken['user_agent'] == 'DbQueryTest-update' ? 'Ok' : 'Fail');
        // delete 1 record
        $count = self::create($tokensModel)->where(array('token' => $record['token']))->delete();
        dpr('Deleted:' . $count, $count == 1 ? 'Ok' : 'Fail');

        echo '<h2>create/update/delete 1 record (with returning statement / all fields)</h2>';
        // create 1 record with returning all data
        $tokensModel = AppModel::UserTokenModel();
        $insertedRecord = self::create($tokensModel)->insert($record, true);
        dpr($insertedRecord, $insertedRecord['token'] == $record['token'] ? 'Ok' : 'Fail');
        $utoken = self::create($tokensModel)->where(array('token' => $record['token']))->findOne();
        dpr($utoken, !empty($utoken) ? 'Ok' : 'Fail');
        // update 1 record with returning all data
        $updatedRecords = self::create($tokensModel)->where(array('token' => $record['token']))->update(array('user_agent' => 'DbQueryTest-update'), true);
        dpr('Updated:', $updatedRecords, count($updatedRecords) === 1 ? 'Ok' : 'Fail');
        $utoken = self::create($tokensModel)->where(array('token' => $record['token']))->findOne();
        dpr($utoken, $utoken['user_agent'] == 'DbQueryTest-update' ? 'Ok' : 'Fail');
        // delete 1 record with returning
        $deletedRecords = self::create($tokensModel)->where(array('token' => $record['token']))->delete(true);
        dpr('Deleted:', $deletedRecords, count($deletedRecords) == 1 ? 'Ok' : 'Fail');

        echo '<h2>create/update/delete 2 records (with returning)</h2>';
        // create 2 records
        $records = array(
            $record,
            array(
                'token' => sha1('test2'),
                'user_id' => $user['id'],
                'user_agent' => 'DbQueryTest2',
                'created' => date('Y-m-d H:i:s'),
                'remember' => true
            )
        );
        $tokens = \Set::extract('/token', $records);
        self::create($tokensModel)->where(array('token' => $tokens))->delete(); //< in case when token was not deleted
        $insertedRecords = self::create($tokensModel)->insertMany(array_keys($record), $records, true);
        dpr('Inserted: ', $insertedRecords, count($insertedRecords) === 2 ? 'Ok' : 'Fail');
        $utokens = self::create($tokensModel)->where(array('token' => $tokens))->find();
        dpr($utokens, count($utokens) == 2 ? 'Ok' : 'Fail');
        // update 2 records
        $updatedRecords = self::create($tokensModel)->where(array('token' => $tokens))->update(array('user_agent' => 'DbQueryTest-update'), array('token', 'user_id'));
        dpr('Updated:', $updatedRecords, count($updatedRecords) === 2 && count($updatedRecords[0]) == 2 ? 'Ok' : 'Fail');
        $utokens = self::create($tokensModel)->where(array('token' => $tokens))->find();
        $uAgents = array_unique(\Set::extract('/UserToken/user_agent', $utokens));
        dpr($utokens, count($utokens) == 2 && count($uAgents) == 1 && array_shift($uAgents) == 'DbQueryTest-update' ? 'Ok' : 'Fail');
        // delete 2 records
        $deletedRecords = self::create($tokensModel)->where(array('token' => $tokens))->delete(null);
        dpr('Deleted:', $deletedRecords, count($deletedRecords) === 2 ? 'Ok' : 'Fail');

        // delete records with order and limit
        $test = 'DELETE FROM "public"."user_tokens" WHERE "token" IN (SELECT "token" FROM "public"."user_tokens" AS "UserToken" WHERE "UserToken"."token" != \'0\'  ORDER BY  RANDOM() ASC   LIMIT 10 )';
        $deletedRecords = $tokensModel->delete(array('token !=' => 0, 'ORDER' => \Db\DbExpr::create('RANDOM()'), 'LIMIT' => 10));
        dpr('Deleted:', $deletedRecords, $tokensModel->lastQuery() == $test ? 'Ok' : 'Fail');
        $tokensModel->rollback();
    }
}