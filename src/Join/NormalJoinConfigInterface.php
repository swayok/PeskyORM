<?php

declare(strict_types=1);

namespace PeskyORM\Join;

use PeskyORM\Utils\DbQuoter;
use PeskyORM\Utils\QueryBuilderUtils;

/**
 * Expected query format:
 * SELECT *, {foreign_columns_to_select} FROM {local_table_name} AS {local_table_alias}
 * {join_type} JOIN {foreign_table_schema}.{foreign_table_name} AS {join_name}
 *      ON {local_table_alias}.{local_column_name} = {join_name}.{foreign_column_name}
 *      AND {additional_conditions}
 *
 * Examples:
 *
 * SELECT * FROM "companies" AS "Companies"
 *      LEFT JOIN "public"."users" AS "User"
 *      ON "Companies"."id" = "User"."company_id"
 *
 * SELECT *, "User".* FROM "companies" AS "Companies"
 *      INNER JOIN "public"."users" AS "User"
 *      ON "Companies"."id" = "User"."company_id" AND "User"."is_active" = true
 */
interface NormalJoinConfigInterface extends JoinConfigInterface
{
    /**
     * Get local table alias.
     */
    public function getLocalTableAlias(): string;

    /**
     * Get local table column name.
     */
    public function getLocalColumnName(): string;

    /**
     * Get foreign table column name
     */
    public function getForeignColumnName(): string;

    /**
     * Get foreign table name
     */
    public function getForeignTableName(): string;

    /**
     * Get foreign table schema
     */
    public function getForeignTableSchema(): ?string;

    /**
     * Add more join conditions.
     * By default, join has only one condition:
     * ON {local_table_alias}.{local_column_name} = {join_name}.{foreign_column_name}
     * This way you can add more conditions to JOIN.
     */
    public function setAdditionalJoinConditions(array $conditions): static;

    /**
     * Set a list of columns to select from foreign table
     * @param array $columns - use '*' or ['*'] to select all columns and empty array to select none
     */
    public function setForeignColumnsToSelect(array $columns): static;

    /**
     * List of foreign table columns to select their values
     */
    public function getForeignColumnsToSelect(): array;

    /**
     * Get all join conditions.
     * Conditions format should be compatible with conditions assembler.
     * @see QueryBuilderUtils::assembleWhereConditionsFromArray().
     * You must use DbExpr in order to quote contifion values that contian db entity names.
     * @see DbQuoter::quoteDbExpr().
     * Examples: [
     *      'join_name.foreign_column_name' => DbExpr::create("`local_table_alias`.`local_column_name`"),
     *      'local_table_alias.col_name2' => DbExpr::create("`join_name`.`col_name3`"),
     * ]
     * By default, there must be at least 1 condition:
     * {join_name}.{foreign_column_name} = {local_table_alias}.{local_column_name}
     * Plus some conditions may be added using self::setAdditionalJoinConditions().
     * @see self::setAdditionalJoinConditions()
     */
    public function getJoinConditions(): array;
}