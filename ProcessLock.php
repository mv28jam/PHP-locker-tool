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
 * @version  1.2.1
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
     * 
     * @param string $in some name of process
     */
    public function __construct(string $in = '') 
    {
        if(!empty($in)){
            $this->setType($in);
        }
    }
    
    /**
     * echo or not to echo output data
     * @param bool $show 
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
            user_error('Trying to set not writable path for ProcessLock', E_USER_WARNING);
            return false;
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
     * check for lock of $type
     * free zombie lock
     * check for process running by pid
     * 
     * @return boolean false if locked, true if not
     */
    public function check()
    {
        $res = array();
        //---
        $this->nameGen();
        if(
            file_exists($this->file_name) 
            and 
            !empty($pid = file_get_contents($this->file_name))
            and 
            is_numeric($pid)
        ){    
            return $this->checkForPid($pid);
        }else{
            return true;
        }
    }
    
    /**
     * check for process with id is running
     * @param int $pid - id of process
     * @return bool
     * @throws E_USER_WARNING or E_USER_NOTICE
     */
    private function checkForPid(int $pid):bool
    {
        //define messages
        $er_mes = 'Can not check process id with "ps -p". LOCK is imaginary.';
        $er_mes_no_php='Process of pid exist, but not PHP precess';
        //get process with pid
        $res = array_filter(explode("\n",shell_exec('ps -p '.$pid)));
        //check for output
        //if empty 'ps -p' is forbidden
        //if in first line of output do not contains PID string
        if(empty($res) or strpos($res[0], self::PID)===false){
            //no valid ps -p output
            $this->lecho($er_mes);
            user_error($er_mes, E_USER_WARNING);
            return true;
        }else{
            //check for 'ps -p' output
            switch(true){
                case(count($res)<2):
                    //check for process output
                    $this->lecho('WARNING:Lock file is old. No process of this lock file.');
                    $this->free();
                    return true;
                case(true):
                    //check for process is php
                    //reset $res !
                    $res = array_filter(explode(' ',$res[1]));
                    if(strpos(end($res), self::PHP_MARKER)===false){
                        //process with pid exist but not PHP process / collision
                        $this->lecho($er_mes_no_php);
                        user_error($er_mes_no_php, E_USER_NOTICE);
                        $this->free();
                        return true;
                    }
                default:
                    $this->lecho('LOCKED. Lock is valid. Process of '.end($res).' '.$pid.' is running. ');
                    return false;
            }
        }
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
                file_put_contents($this->file_name,getmypid());
                $this->lecho('LOCKED.');
                return true;
            }else{
                $this->lecho('WARNING:Already locked.');
                return false;
            }
        }else{
            user_error('Can not write in default dir($this->dir)', E_USER_WARNING);
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
            $this->lecho('UNLOCKED.');
            return true;
        }else{
            $this->lecho('WARNING:Lock file does not exists - NO LOCK.');
            return false;
        }
    }
    
}    