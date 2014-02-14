<?php
/**
 * 使用reids作为php的session存储介质
 * 
 * Depends on {@link http://github.com/nrk/predis/ Predis}
 * Depends on {@link https://github.com/ivanstojic/redis-session-php}
 * Depends on {@link http://www.php.net/manual/zh/function.session-set-save-handler.php}

 * @author felix
 * 
 * @license http://www.gnu.org/licenses/lgpl-3.0.html
 * 
 * <code>
 * <?php 
 *      require 'Predis/Autoloader.php';
 *      Predis\Autoloader::register();
 *      require_once 'RedisSession.php';
 *      $redisHandle    =    new Predis\Client();
 *      assert($redisHandle);
 *      RedisSession::init ($redisHandle);
 * ?>
 * </code>
 */
class RedisSession{
    private $_sessionKeyPre =   "SID:";// 节省内存 PHPSESSID::
    private $_id            =   "";
    /**
     * redis client handle
     * @var resourse
     */
    private $_redisHandle;
    
    /**
     * 初始化redissession 避免多次初始化
     * @var RedisSession
     */
    private static $_instance = null;
    
    public static function init ( $redis) {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self ( $redis );
        } else {
            throw new Exception ( 'RedisSession already initialized' );
        }
    }

    /**
     * 默认构造器.
     *
     * @param resourse $redisHandle
     * @param array $config 配置选项
     * 
     * @throws Exception
     */
    private function __construct ($redis) {
        $this->_redisHandle = $redis ;

        session_set_save_handler ( array ($this, 'open' ), array ( $this, 'close' ), array ( $this, 'read' ), array ( $this, 'write' ), array ( $this, 'destroy' ), array ( $this, 'gc' ) );

        session_start ();
    }

    /*
    The open callback works like a constructor in classes and is executed when the session is being opened. It is the first callback function executed when the session is started automatically or manually with session_start(). Return value is TRUE for success, FALSE for failure
    */
    function open($savePath, $sessionName){
        return true;
    }
    
    /*
    The close callback works like a destructor in classes and is executed after the session write callback has been called. It is also invoked when session_write_close() is called. Return value should be TRUE for success, FALSE for failure.
    */
    function close(){
        return true;
    }
    
    /*
    The read callback must always return a session encoded (serialized) string, or an empty string if there is no data to read.
    This callback is called internally by PHP when the session starts or when session_start() is called. Before this callback is invoked PHP will invoke the open callback.
    The value this callback returns must be in exactly the same serialized format that was originally passed for storage to the write callback. The value returned will be unserialized automatically by PHP and used to populate the $_SESSION superglobal. While the data looks similar to serialize() please note it is a different format which is speficied in the session.serialize_handler ini setting.
    */
    function read($id){
        return $this->_redisHandle->get ( $this->_sessionKeyPre.$id );
    }
    /*
    The write callback is called when the session needs to be saved and closed. This callback receives the current session ID a serialized version the $_SESSION superglobal. The serialization method used internally by PHP is specified in the session.serialize_handler ini setting.
    The serialized session data passed to this callback should be stored against the passed session ID. When retrieving this data, the read callback must return the exact value that was originally passed to the write callback.
    This callback is invoked when PHP shuts down or explicitly when session_write_close() is called. Note that after executing this function PHP will internally execute the close callback.
    Note:
    The "write" handler is not executed until after the output stream is closed. Thus, output from debugging statements in the "write" handler will never be seen in the browser. If debugging output is necessary, it is suggested that the debug output be written to a file instead.
    */
    function write($id, $data){
        $ttl = ini_get("session.gc_maxlifetime");
        //第一次写入，检测是否已经包含此id，如果包含，则表示此id重复（与phpsessionid生成机制相关），invalid 写入失败。
        if ($this->_id !== $id) {
            if ($this->_redisHandle->get ( $this->_sessionKeyPre.$id ) === false) {
                return false;
            }
            $this->_id = $id;
        } 
        $this->_redisHandle->setex ( $this->_sessionKeyPre.$id, $ttl , $data );
        return true;
    }

    /*
    This callback is executed when a session is destroyed with session_destroy() or with session_regenerate_id() with the destroy parameter set to TRUE. Return value should be TRUE for success, FALSE for failure.
    */
    function destroy($id){
        $this->_redisHandle->delete (  $this->_sessionKeyPre.$id );
        return true;
    }
    
    /*
    The garbage collector callback is invoked internally by PHP periodically in order to purge old session data. The frequency is controlled by session.gc_probability and session.gc_divisor. The value of lifetime which is passed to this callback can be set in session.gc_maxlifetime. Return value should be TRUE for success, FALSE for failure.
    */
    function gc($lifetime){
        return true;
    }
}
