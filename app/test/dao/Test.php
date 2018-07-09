<?php
namespace Metz\app\test\dao;

use Metz\sys\Configure;

class Test extends \Metz\app\metz\Dao
{
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

    protected function _get_table_name()
    {
        return 'testing';
    }

    protected function _get_db_config()
    {
        return Configure::config('db/test/votia');
    }

    protected function _get_related_table_info()
    {
        return [];
    }

    protected function _get_fields_info()
    {
        return [
            'id' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_AUTO_INCREMENT => true
            ],
            'a1' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b1' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c1' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'a2' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b2' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c2' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'a3' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'b3' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
            'c3' => [
                self::FIELD_INFO_TYPE => 'int(11)',
                self::FIELD_INFO_NULLABLE => false,
                self::FIELD_INFO_DEFAULT => 1
            ],
        ];
    }
}
