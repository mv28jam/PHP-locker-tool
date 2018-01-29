# Loc process tool
helps to check for running 1 process by uid of process

```php
//create lock with string uid
$locker= new ProcessLock('my_process_id');
//set tmp lock dir
$locker->setDir('my_project_tmp_dir');
//check for active lock and lock
$locker->checkAndLock();
//do not show echo messages from locker
$locker->setEcho(false);
//free lock after execution
$locker->free();
```
## Yii2 console process lock realisation example
```php
//...
class ControllerWithLock extends Controller{
  //$this->locker = new ProcessLock;
  public function beforeAction($action) 
  {
      if (!parent::beforeAction($action)) {
          return false;
      }
      //lock process//uid of process controller_id+action_id
      $this->locker->setType($action->controller->id.$action->id);
      //check for lock file
      if($this->locker->check()){
          //lock process
          $this->locker->lock();
          //
          return true;   
      }
      return false;
  }
  public function afterAction($action, $result)
  {
      $result = parent::afterAction($action, $result);
      //free lock 
      $this->locker->free();
      //
      return $result;
  }
```
