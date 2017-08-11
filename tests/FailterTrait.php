<?php  declare(strict_types = 1); namespace Test;

trait FailterTrait {

 private $refInstance, $targetRefInstance;

 public function reflect(&$obj) {
  $this->refInstance = new \ReflectionClass(get_class($obj));
  $this->targetRefInstance = $obj;
 }

/* all protected/private method of a class.
 *
 * @param object &$object    Instantiated object that we will run method on.
 * @param string $methodName Method name to call
 * @param array  $parameters Array of parameters to pass into method.
 *
 * @return mixed Method return.
 */
  public function callMethod($methodName, array $parameters = []) {
      $reflection = $this->refInstance;
      $method = $reflection->getMethod($methodName);
      $method->setAccessible(true);

      return $method->invokeArgs($this->targetRefInstance, $parameters);
  }

/* Return value of a private property using ReflectionClass
 *
 * @param self $instance
 * @param string $property
 *
 * @return mixed
 */
  private function getProperty($property = '_data') {
    $reflector = $this->refInstance;;
    $reflector_property = $reflector->getProperty($property);
    $reflector_property->setAccessible(true);

    return $reflector_property->getValue($this->targetRefInstance);
  }

}
