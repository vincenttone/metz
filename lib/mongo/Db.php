<?php
namespace Metz\lib\mongo;
/**
 * @file mongo/db.php
 * @author vincent
 * @date 2014/05/07 10:11:50
 * @brief 
 *  
 **/

class Db{
    const CONFIG_FIELD_HOST = 'host';
    const CONFIG_FIELD_PORT = 'port';
    const CONFIG_FIELD_DB = 'db';
    const CONFIG_FIELD_USERNAME = 'username';
    const CONFIG_FIELD_PASSWORD = 'password';
    const RETRY_COUNT = 2;

    const ERRNO_EMPTY_PARAMS = 100;
    const ERRNO_CONNECT_FAIL = 101;
    const ERRNO_INSERT_FAIL = 102;
    const ERRNO_FINDONE_FAIL = 103;
    const ERRNO_FIND_FAIL = 104;
    const ERRNO_DELETE_FAIL = 105;
    const ERRNO_SAVE_FAIL = 106;
    const ERRNO_UPDATE_FAIL = 107;

    private $_conn;
    private $_database;
    private $_hostname, $_hostport;
    private $_options;

    private $_currentDbName;
    private $_currentTableName;
    private $_currentCompressFields = [];
    private $_arrIntDbFields;

    /**
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config = null) {
        $this->Configure($config)->Connect();
    }

    /**
     * @param array $config
     * @return $this|bool
     */
    public function Configure($config) {

        if(empty($config)) {
            return false;
        }
        $this->_hostname = $config[self::CONFIG_FIELD_HOST];
        unset($config[self::CONFIG_FIELD_HOST]);
        $this->_hostport = $config[self::CONFIG_FIELD_PORT];
        unset($config[self::CONFIG_FIELD_PORT]);
        isset($config[self::CONFIG_FIELD_DB])
            && $this->_currentDbName = $config[self::CONFIG_FIELD_DB];
        $this->_options = $config;
        return $this;
    }

    /**
     * @return array $this
     * @throws Exception
     */
    public function Connect() {
        try {
		    $this->_conn = new MongoClient(
                'mongodb://'.$this->_hostname.':'.$this->_hostport,
                $this->_options
            );
        } catch (Exception $e) {
            throw new Exception(
                __METHOD__.' Exception code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_CONNECT_FAIL
            );
        }
        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function ReConnect() {
        return $this->Connect();
    }

    /**
     * @param $db
     * @return $this
     */
    public function SetDbName($db) {
        $this->_currentDbName = $db;
        return $this;
    }

    /**
     * @param $table
     * @return $this
     */
    public function SetTableName($table) {
        $this->_currentTableName = $table;
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    public function setCompressFields($fields)
    {
        $this->_currentCompressFields = $fields;
        return $this;
    }

    /**
     * @param array $data
     * @return array
     */
    public function Compress($data) {
        if(!empty($this->_currentCompressFields)){
            foreach($this->_currentCompressFields as $field){
                if(isset($data[$field]) && is_string($data[$field])){
                    $data[$field] = snappy_compress($data[$field]);
                    $data[$field] = new MongoBinData($data[$field], MongoBinData::GENERIC);
                }
            }
        }
        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    public function UnCompress($data) {
        if (!empty($this->_currentCompressFields)) {
            foreach ($this->_currentCompressFields as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = $data[$field]->bin;
                    $data[$field] = snappy_uncompress($data[$field]);
                }
            }
        }
        return $data;
    }

    /**
     * @param $callback
     * @return null|array
     * @throws Exception
     */
    protected function _doAction($callback)
    {
        Lib_Log::debug( __METHOD__.': do action begin!');
        if (empty($this->_currentDbName) || empty($this->_currentTableName)) {
            throw new Exception(
                __METHOD__.'empty param ! DB:'.var_export($this->_currentDbName, true)
                .' Table: '.var_export($this->_currentTableName, true),
                self::ERRNO_EMPTY_PARAMS
            );
        }
        Lib_Log::debug(__METHOD__.': data check finish!');
        $doAct = function() use ($callback) {
            $dbName = $this->_currentDbName;
            $tableName = $this->_currentTableName;
            if (empty($this->_conn->$dbName)) {
                $this->ReConnect();
            }
            $collection = $this->_conn->$dbName->$tableName;
            $ret = $callback($collection);
            return $ret;
        };
        Lib_Log::debug(__METHOD__.': create function finish!');
        $retryCount = self::RETRY_COUNT - 1;
        $ret = null;
        do {
            Lib_Log::debug(__METHOD__.': begin do action! retry count is: %d', $retryCount);
            try {
                $ret = $doAct();
                $retryCount = -1;
            } catch (Exception $e) {
                if ($retryCount < 0) {
                    throw new Exception($e->getMessage(), $e->getCode());
                } else {
                    Lib_Log::warn(
                        "MONGO Retry,retry time: %d, Exception code: %s, msg: %s",
                        array(self::RETRY_COUNT - $retryCount, strval($e->getCode()), $e->getMessage())
                    );
                }
            }
        } while(is_null($ret) && $retryCount-- >= 0);
        return $ret;
    }

    /**
     * 更新操作
     * @param $dataSave
     * @param bool $batch
     * @return array|bool|null
     * @throws Exception
     */
    public function Insert($dataSave, $batch = false) {
        if(empty($dataSave)){
            throw new Exception(
                __METHOD__.'empty param ! Data:'. json_encode($dataSave),
                self::ERRNO_EMPTY_PARAMS
            );
        }
        $dataSave = $this->Compress($dataSave);
        $callback = function($collection) use ($dataSave, $batch) {
            $action = $batch === true ? 'batchInsert' : 'insert';
            $ret = $collection->$action($dataSave);
            return $ret;
        };
        try {
            $ret = $this->_doAction($callback);
            if (is_null($ret['err'])) {
                return true;
            } else {
                $msg = '';
                if (isset($ret['code']) && isset($ret['errmsg'])) {
                    $msg = 'code: '.$ret['code'].' msg: '.$ret['errmsg'];
                } else {
                    $msg = json_encode($ret);
                }
                throw new Exception(
                    __METHOD__.' Insert fail! code: '.$msg,
                    self::ERRNO_INSERT_FAIL
                );
            }
        } catch (Exception $e) {
            throw new Exception(
                __METHOD__.' Insert fail! Exception code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_INSERT_FAIL
            );
        }
        return $ret;
    }

    /**
     * @param $dataSave
     * @return array|bool|null
     * @throws Exception
     */
    public function Save($dataSave) {
        if(empty($dataSave)){
            throw new Exception(
                __METHOD__.'empty param ! Data:'. json_encode($dataSave),
                self::ERRNO_EMPTY_PARAMS
            );
        }
        $dataSave = $this->Compress($dataSave);
        $callback = function($collection) use ($dataSave) {
            $ret = $collection->save($dataSave);
            return $ret;
        };
        try {
            $ret = $this->_doAction($callback);
            if (is_null($ret['err'])) {
                return true;
            } else {
                $msg = '';
                if (isset($ret['code']) && isset($ret['errmsg'])) {
                    $msg = 'code: '.$ret['code'].' msg: '.$ret['errmsg'];
                } else {
                    $msg = json_encode($ret);
                }
                throw new Exception(
                    __METHOD__.' Insert fail! code: '.$msg,
                    self::ERRNO_SAVE_FAIL
                );
            }
        } catch (Exception $e) {
            throw new Exception(
                __METHOD__.' Insert fail! Exception code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_SAVE_FAIL
            );
        }
        return $ret;
    }

    /**
     * 单个查询操作
     * @param $conds
     * @param array $fields
     * @return array
     * @throws Exception
     */
    public function QueryOne($conds, $fields = []) {
        $callback = function($collection) use ($conds, $fields) {
            return $collection->findOne($conds, $fields);
        };
        try {
            $cursor = $this->_doAction($callback);
        } catch (Exception $ex) {
            throw new Exception(
                __METHOD__.' QueryOne fail! Exception code: '
                .$ex->getCode().' msg: '.$ex->getMessage(),
                self::ERRNO_FINDONE_FAIL
            );
        }
        $ret = $this->UnCompress($cursor);
        return $ret;
    }

    /**
     * 批量查询，把结果集全部load到内存，不能用来导表或者查询大量结果
     * @param $conds
     * @param array $fields
     * @param int $limit
     * @param int $offset
     * @param null $sort
     * @param bool $return_iterator
     * @return array|Cursor|null
     * @throws Exception
     */
    public function Query($conds, $fields = [], $limit = 0, $offset = 0, $sort = null, $return_iterator = false) {
        $callback = function($collection) use ($conds, $fields, $limit, $offset, $sort) {
            $cursor = $collection->find($conds, $fields);
            empty($offset) || $cursor = $cursor->skip($offset);
            empty($limit) || $cursor = $cursor->limit($limit);
            empty($sort) || $cursor = $cursor->sort($sort);
            return $cursor;
        };
        $ret = null;
        try{
            $cursor = $this->_doAction($callback);
            //$cursor->timeout(3000);
            $cursor->timeout(120000);
            if ($return_iterator) {
                $lmcursor = new Cursor($cursor);
                $lmcursor->set_callback([$this, 'UnCompress']);
                return $lmcursor;
            }
            $rset = iterator_to_array($cursor); 
            // 解压
            if(!empty($rset)){
                $ret = $rset;
                foreach($rset as $key => $single){
                    $ret[$key] = $this->UnCompress($single);
                }
            } else {
                $ret = $rset;
            }
            unset($rset);
        } catch(Exception $e) {
            throw new Exception(
                __METHOD__.' Query faild! code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_FIND_FAIL
            );
        }
        return $ret;
    }

    /**
     * distinct查询
     * @param $field
     * @param $conds
     * @return array
     * @throws Exception
     */
    public function Distinct($field, $conds) {
        $callback = function($collection) use ($field, $conds) {
            return $collection->Distinct($field, $conds);
        };
        try {
            MongoCursor::$timeout = 120000;
            $cursor = $this->_doAction($callback);
            //$cursor->timeout(120000);
        } catch (Exception $ex) {
            throw new Exception(
                __METHOD__.' Distinct fail! Exception code: '
                .$ex->getCode().' msg: '.$ex->getMessage(),
                self::ERRNO_FINDONE_FAIL
            );
        }
        $ret = $this->UnCompress($cursor);
        return $ret;
    }

    /**
     * 删除操作
     * @param $conds
     * @return array|null
     * @throws Exception
     */
    public function Delete($conds) {
        if(empty($conds)){
            throw new Exception(
                __METHOD__.' delete muse have conds! but we get:'. json_encode($conds),
                self::ERRNO_EMPTY_PARAMS
            );
        }
        $callback = function($collection) use ($conds) {
            $ret = $collection->remove($conds);
            return $ret;
        };
        try{
            $ret = $this->_doAction($callback);
        } catch(Exception $e) {
            throw new Exception(
                __METHOD__.' Delete faild! Exception code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_DELETE_FAIL
            );
        }
        return $ret;
    }

    /**
     * @param $cond
     * @param $dataSave
     * @param bool $multi
     * @param bool $upsert
     * @return array|null
     * @throws Exception
     */
    public function Update($cond, $dataSave, $multi = false, $upsert = false) {
        if(empty($dataSave) || empty($cond)){
            throw new Exception(
                __METHOD__.'empty param ! Data:'
                . json_encode($dataSave).', Cond:'
                .json_encode($cond),
                self::ERRNO_EMPTY_PARAMS
            );
        }
        $dataSave = $this->Compress($dataSave);
        $callback = function($collection) use ($dataSave, $cond, $multi, $upsert) {
            $ret = $collection->update($cond, ['$set' => $dataSave], ['upsert' => $upsert, 'multiple' => $multi]);
            return $ret;
        };
        try {
            $ret = $this->_doAction($callback);
            if (is_null($ret['err'])) {
                return $ret['n'];
            } else {
                $msg = '';
                if (isset($ret['code']) && isset($ret['errmsg'])) {
                    $msg = 'code: '.$ret['code'].' msg: '.$ret['errmsg'];
                } else {
                    $msg = json_encode($ret);
                }
                throw new Exception(
                    __METHOD__.' Update fail! code: '.$msg,
                    self::ERRNO_UPDATE_FAIL
                );
            }
        } catch (Exception $e) {
            throw new Exception(
                __METHOD__.' Update fail! Exception code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_UPDATE_FAIL
            );
        }
        return $ret;
    }

    /**
     * @param array $conds
     * @param int $limit
     * @param int $offset
     * @return array|null
     * @throws Exception
     */
    public function Count($conds = [], $limit = 0, $offset = 0) {
        $callback = function($collection) use ($conds, $limit, $offset) {
            $count = $collection->count($conds, $limit, $offset);
            return $count;
        };
        $ret = null;
        try{
            $count = $this->_doAction($callback);
        } catch(Exception $e) {
            throw new Exception(
                __METHOD__.' Query faild! code: '
                .$e->getCode().' msg: '.$e->getMessage(),
                self::ERRNO_FIND_FAIL
            );
        }
        return $count;
    }

    /**
     * @param array $cond
     * @return array
     */
    public function GetTables($cond = []) {
        $dbName = $this->_currentDbName;
        if (empty($this->_conn->$dbName)) {
            $this->ReConnect();
        }
        $collections = $this->_conn->$dbName->getCollectionNames($cond);
        return $collections;
    }
}
