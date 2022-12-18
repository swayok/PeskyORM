<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class RelationTest extends BaseTestCase
{
    public function testInvalidRelation1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '%\$foreignTableClass argument must be a class that implements .*TableInterface%'
        );
        new Relation('valid', Relation::HAS_MANY, static::class, 'id', 'Relation');
    }

    public function testInvalidRelation2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$foreignColumnName argument value (\'id\') refers to a primary key column. It makes no sense for HAS MANY relation.'
        );
        $relation = new Relation(
            'id',
            Relation::HAS_MANY,
            TestingAdminsTable::class,
            'id',
            'Relation'
        );
        $relation->toJoinConfig('Test');
    }

    public function testInvalidRelation3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "TestingAdminsTableStructure does not know about column named 'foreign_invalid'"
        );
        $relation = new Relation(
            'id',
            Relation::HAS_MANY,
            TestingAdminsTable::class,
            'foreign_invalid',
            'Relation'
        );
        $relation->toJoinConfig('Test');
    }

    public function testRelationToJoinConfig(): void
    {
        $relation = new Relation(
            'id',
            Relation::HAS_MANY,
            TestingAdminsTable::class,
            'parent_id',
            'Relation'
        );
        $joinConfig = $relation->toJoinConfig('Test');
        $conditions = [
            'Relation.parent_id' => new DbExpr('`Test`.`id`', false),
        ];
        static::assertEquals($conditions, $joinConfig->getJoinConditions());
        static::assertEquals(['*'], $joinConfig->getForeignColumnsToSelect());
        // additional conditions (array)
        $additionalConditions = [
            'Relation.login' => DbExpr::create('`Test`.`login`')
        ];
        $relation->setAdditionalJoinConditions($additionalConditions);
        $joinConfig = $relation->toJoinConfig('Test');
        static::assertEquals(
            $conditions + $additionalConditions,
            $joinConfig->getJoinConditions()
        );
        // additional conditions (closure)
        $additionalConditionsClosure = static function (
            Relation $relation,
            string $localTableAlias
        ) {
            return [
                $relation->getName() . '.login' => DbExpr::create("`{$localTableAlias}`.`login`")
            ];
        };
        $relation->setAdditionalJoinConditions($additionalConditionsClosure);
        $joinConfig = $relation->toJoinConfig('Test');
        static::assertEquals(
            $conditions + $additionalConditions,
            $joinConfig->getJoinConditions()
        );

        // HAS_ONE
        $conditions = [
            'Relation.id' => new DbExpr('`Test`.`parent_id`', false),
        ];
        $relation = new Relation(
            'parent_id',
            Relation::HAS_ONE,
            TestingAdminsTable::class,
            'id',
            'Relation'
        );
        $joinConfig = $relation->toJoinConfig('Test');
        static::assertEquals($conditions, $joinConfig->getJoinConditions());

        // BELONGS_TO
        $relation = new Relation(
            'parent_id',
            Relation::BELONGS_TO,
            TestingAdminsTable::class,
            'id',
            'Relation'
        );
        $joinConfig = $relation->toJoinConfig('Test');
        static::assertEquals($conditions, $joinConfig->getJoinConditions());
    }
}