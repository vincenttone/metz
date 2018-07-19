<?php
namespace Metz\app\test\model\table;

use Metz\sys\Configure;
use Metz\app\metz\db\Table;

class Test extends Table
{
    protected function _get_db_config()
    {
        return Configure::config('db/test/votia');
    }

    public function get_indexes()
    {
        return [
            self::INDEX_TYPE_PRIMARY => 'id',
            self::INDEX_TYPE_UNIQ => [
                ['a1', 'b1', 'c1'],
                'a2',
            ],
            self::INDEX_TYPE_COMMON => [
                'b2',
                'c2',
                ['b3', 'c3'],
            ],
        ];
    }

    public function get_table_name()
    {
        return 'testing';
    }

    public function get_related_table_info()
    {
        return [];
    }

    public function get_fields_info()
    {
        return [
            'id' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_UNSIGNED => true,
                self::FIELD_INFO_AUTO_INCREMENT => true
            ],
            'a1' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b1' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_VARCHAR,
                self::FIELD_INFO_LENGTH => 10,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c1' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 3,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'a2' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 5,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b2' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 20,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c2' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'a3' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b3' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c3' => [
                self::FIELD_INFO_TYPE => Dao::FIELD_TYPE_INT,
                self::FIELD_INFO_LENGTH => 11,
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
        ];
    }
}
