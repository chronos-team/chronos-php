<?php

namespace Chronos\Base;
use Chronos\Utils\Inflector;

final class App
{
  public static function import($type = "", $file)
  {
      $nameFile = Inflector::camelize($file . "_controller");
      require_once "/var/www/html/core/Controllers/{$nameFile}.php";

  }
}
