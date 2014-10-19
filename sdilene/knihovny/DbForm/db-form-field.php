<?php

abstract class DbFormField {

  protected $d; // array with database column description
  private $value = null;
  private $postPrefix = 'cDbFormFiled_change_or_remove_this';

  // display types
  const STANDARD = 1;
  const RAW      = 2;
  const CUSTOM   = 3;

  /** Creates field based on standard description row generated by MySQL */
  function __construct($description) {
    $this->d = $description;
  }

  /** How field wishes to be displayed */
  function display() {
    return self::STANDARD;
  }

  /** Should return html code of editable area (ie input element) */
  abstract function html();

  /** Human readable name of this field */
  function label() {
    return ucfirst(strtr($this->name(), '_', ' '));
  }

  /** Should set value based on post data */
  abstract function loadPost();

  /** Database column name, field's identifier */
  function name() {
    return $this->d['Field'];
  }

  /**
   * For given identifier $n returns name of post variable dedicated to this
   * class.
   * @todo should tehre be $this->name() added to generated name? It depends
   *  if we're assuming prefix is dedicated to instance or class. Instance
   *  sounds more reasonable, on the other hand it has to be set then, otherwise
   *  collisions appear.
   * @see postPrefix
   * @todo array indexes converted to string here must reflect access via
   *  postValue(), unify this somehow
   */
  protected function postName($n = null) {
    if(!$n) $n = 'default-some_default_change_or_remove_this';
    return $this->postPrefix() . '[' . $this->name() . '][' . $n . ']';
  }

  /** Gets/sets post variable name prefix, where field's data may be sent */
  private function postPrefix() {
    if(func_num_args() == 1) $this->postPrefix = func_get_arg(0);
    else return $this->postPrefix;
  }

  /**
   * Returns custom post data for this field optionally identified with name $n,
   * returns null if such data do not exist.
   */
  protected function postValue($n = null) {
    if(!$n) $n = 'default-some_default_change_or_remove_this';
    if(isset($_POST[$this->postPrefix()][$this->name()][$n]))
      return $_POST[$this->postPrefix()][$this->name()][$n];
    else return null;
  }

  /** Executed by DbForm after new row is inserted / updated. To override. */
  function postInsert() {
  }

  /** Executed by DbForm before new row is inserted / updated. To override. */
  function preInsert() {
  }

  /** Gets/sets value of the field. Represents real value stored in database. */
  function value() {
    if(func_num_args() == 1) $this->value = func_get_arg(0);
    else return $this->value;
  }

}
