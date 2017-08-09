<?php  declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Innermond\Failter as Me;

class OnTest extends TestCase {

  public function invalidArgumentsForOn() {
    return [
      [''],
      ['    '],
    ];
  }

  /**
   * @dataProvider invalidArgumentsForOn
   */
  public function testOnWithNonStringArguments($nil) {
    $this->expectException(\InvalidArgumentException::class);
    $fail = new Me\Failter();
    $fail->on($nil);
  }

  public function testOnWithoutArgument() {
    $this->expectException(\TypeError::class);
    $fail = new Me\Failter();
    $fail->on();
  }
}
