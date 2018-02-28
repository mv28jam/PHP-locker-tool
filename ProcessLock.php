<?php

//namespace

/**
 * Creates lock file in tmp(by def) with pid to prevent double exec
 * check for process with pid is live
 * deletes lock file with dead process
 * 
 * local execution // local file system
 * 
 * @author   Mihail Ershov - mv28jam <mv28jam@yandex.ru>
 * @version  1.2.4
 */
class ProcessLock{
        
    /**
     * ps output marker
     */
    const PID = 'PID';
    /**
     * php marker
     */
    const PHP_MARKER = 'php';
    /**
     * locker error messages
     */
    const LCR_ER_WRITE = 'Trying to set not writable path for ProcessLock';
    const LCR_ER_WRITE_TMP = 'Can not write in default dir($this->dir)';
    const LCR_ER_PID = 'Can not check process id with "ps -p". LOCK is imaginary.';
    const LCR_ER_CHK_FAIL = 'Process check result unexpected.';
    const LCR_ER_CHK_FAIL2 = 'Process check return status 0, otherwice unexpected lines count < 2.';
    const LCR_ER_NO_PHP = 'Process of pid exist, but not PHP process';
    const LCR_ER_OLD = 'WARNING:Lock file is old. No process of this lock file.';
    /**
     * locker messages
     */
    const LCR_LOСKED = 'LOCKED.';
    const LCR_UNLOСKED = 'UNLOCKED.';
    const LCR_ALREADY_LOСKED = 'WARNING:Already locked.';
    const LCR_LOСK_NO_EXIST = 'WARNING:Lock file does not exists - NO LOCK.';
    const LCR_LOСK_VALID = 'Lock is valid. Process of %1 %2 is running. ';
    
    /**git 
     * @var string name of lock file = id of work
     */
    protected $type = 'default';
    /**
     * @var int pid of process
     */
    protected $pid = null;
    /**
     * @var string full filename
     */
    protected $file_name = '';
    /**
     * @var boolean echo lock/unlock messages
     */
    protected $is_echo = true;    
    /**
     * @var string locks file directory 
     */
    protected $dir = '/tmp';
    /**
     * disable check pid of locked process
     * @warning ctrl or unexpected end of process and lock is forever live
     * @var boolean 
     */
    protected $check_pid = true;
    /**
     * disable for executable files 
     * for files with #!/usr/bin/env php
     * @var boolean if true check for php in launch command 
     */
    public $check_marker = true;
    /**
     * Paranoid or simple-jack strategy
     * in case of error (we do not know process state):
     * - false: free lock, exec script
     * - true: do NOT touch uncheckable lock, do NOT run new process
     * @var boolean flag
     */
    public $paranoid_strategy = false;
    
    
    /**
     * @param string $in some name of process
     */
    public function __construct(string $in = '') 
    {
        if(!empty($in)){
            $this->setType($in);
        }
    }
    
    /**
     * Enable pid live check
     * @return void 
     */
    public function enableProcessIDCheck(bool $show)
    {
        $this->check_pid = true;
    }
    
    /**
     * Do not check dead lock
     * @warning ctrl or unexpected end of process and lock is forever live
     * @return void  
     */
    public function disableProcessIDCheck(bool $show)
    {
        $this->check_pid = false;
    }
    
    /**
     * echo or not to echo output data
     * @param bool $show 
     * @return void 
     */
    public function setEcho(bool $show)
    {
        $this->is_echo = $show;
    }
    
    /**
     * set some name of process
     * @param type $in unique name of process (input clean data)
     * @return string name of
     */
    public function setType(string $in):string
    {
        $this->type = $in;
        return $this->type;
    }
    
    /**
     * set path to store lock files
     * @param string $in path to dir with locks
     * @return bool true on on writable dir
     * @throws E_USER_WARNING
     */
    public function setDir(string $in):bool
    {
        if(is_writable($in)){
            $this->$dir = $in; 
            return true;
        }else{
            user_error(self::LCR_ER_WRITE, E_USER_WARNING);
            return false;
        }
        
    }
    
    /**
     * return true and free due to strategy
     * @return bool
     */
    protected function errorStrategy() : bool{
        if($this->paranoid_strategy){
            return true;
        }else{
            $this->free();
            return true;
        }
    }
    
    /**
     * echo with echo on check
     * @param string $in string to echo
     */
    protected function lEcho(string $in)
    {
        if($this->is_echo){
            echo $in."\n";
        }
    }
    
    /**
     * generate file full name of lock file
     * @return void
     */
    private function nameGen()
    {
        $this->file_name = $this->dir.'/lock_'.$this->type.'.lock';
    }
    
    /**
     * alia to check then lock
     * @see lock 
     */
    public function checkAndLock():bool
    {
        if($this->check()){
            return $this->lock();
        }
        return false;
    }
    
    /**
     * check for lock of $this->type
     * free zombie lock
     * check for process running by pid
     * 
     * @return boolean false if locked, true if not
     */
    public function check()
    {
        $this->nameGen();
        if(
            file_exists($this->file_name) 
            and 
            !empty($pid = file_get_contents($this->file_name))
            and 
            is_numeric($pid)
        ){  
            if($this->check_pid === true){
                return $this->checkForPid($pid);
            }else{
                return true;
            }  
        }else{
            return true;
        }
    }
    
    /**
     * check for process with id is running
     * @param int $pid - id of process
     * @return bool // true if pid is dead and process can lock, false otherwice 
     * @throws E_USER_WARNING or E_USER_NOTICE
     */
    private function checkForPid(int $pid):bool
    {
        //get process with pid
        exec('ps -p '.$pid, $res, $status);
        //
        switch(true){
            //'ps -p' is forbidden or ktulhu
            case($status > 1):
                $this->errorActions(self::LCR_ER_PID);
                return $this->errorStrategy();
            //command executed with result 0, but output is undefined or in wrong format
            case(!isset($res[0]) or strpos($res[0], self::PID)===false):
                //result is unexpected
                $this->errorActions(self::LCR_ER_CHK_FAIL);
                return $this->errorStrategy();    
            //command executed with no result    
            case($status === 1):
                //so process is dead
                $this->lecho(self::LCR_ER_OLD);
                $this->free();
                return true;
            //command executed with result 0, but output is undefined or in wrong format
            case(count($res)<2):
                //result is unexpected
                $this->errorActions(self::LCR_ER_CHK_FAIL2);
                return $this->errorStrategy();        
            //command executed success 0 result and check for php process
            case($status === 0 and $this->check_marker):
                if(strpos(end($res), self::PHP_MARKER)===false){
                    //process with pid exist but not PHP process / collision
                    $this->lecho(self::LCR_ER_NO_PHP);
                    user_error(self::LCR_ER_NO_PHP, E_USER_NOTICE);
                    $this->free();
                    return true;
                }    
            //command executed success 0 result and check
            case($status === 0):
                $res_out = explode(' ', end($res));
                $this->lecho(str_replace(['%1','%2'], [end($res_out), $pid], self::LCR_LOСK_VALID));
                unset($res_out);
                return false;
            default:
                //result is VERY unexpected
                $this->errorActions(self::LCR_ER_CHK_FAIL);
                return $this->errorStrategy();  
        }
    }
    
    /**
     * action if error happens
     * @param string $er_txt 
     */
    protected function errorActions(string $er_txt){
        $this->lecho($er_txt);
        user_error($er_txt, E_USER_WARNING);
    }
    
    
    /**
     * locks process with this name $type
     * @return boolean true if lock
     * @throws E_USER_WARNING
     */
    public function lock(): bool
    {
        $this->nameGen();
        //check for write
        if(is_writeable($this->dir)){
            if(!file_exists($this->file_name)){
                file_put_contents($this->file_name, getmypid());
                $this->lecho(self::LCR_LOСKED);
                return true;
            }else{
                $this->lecho(self::LCR_ALREADY_LOСKED);
                return false;
            }
        }else{
            user_error(self::LCR_ER_WRITE_TMP, E_USER_WARNING);
            return false; 
        }
    }
    
    /**
     * free lock by deleting lock file
     * @return boolean false if no lock file
     */
    public function free() : bool
    {
        $this->nameGen();
        if(file_exists($this->file_name)){
            unlink($this->file_name);
            $this->lecho(self::LCR_UNLOСKED);
            return true;
        }else{
            $this->lecho(self::LCR_LOСK_NO_EXIST);
            return false;
        }
    }
    
}    