<?php

/**
 * XTemplate (partial) implementation using compiled php scripts for speedup.
 * Interface is same as original implementation but does not support all xtpl
 * features and methods.
 */

class XTemplate {

  protected $tc = null;     // compiled template class instance

  /**
   * Prepares template from given file
   */
  function __construct($file) {
    $this->tc = $this->compiledTemplate($file);
  }

  /**
   * Assigns values to variables which can be then used in template
   * Possible uses:
   *  assign(array('key1' => 'value1', 'key2' => 'value2'))
   *    - sets key-value pairs as is
   *  assign('name', 'value')
   *    - sets single key-value pair as is
   *  assign('name', Templatable $object)
   *    - prepares object to be used - object must implement Templatable
   *      interface.
   *    - through this interface concrete keys are requested at runtime
   *      (TODO maybe do not request at runtime but allow direct compilation
   *      of simple getters)
   *    - temptable object may be used also as value in array mode (see above)
   */
  function assign($a, $b = null) {
    if(is_array($a) && $b === null) {
      $this->tc->context = array_merge($this->tc->context, $a);
    } else {
      $this->tc->context[$a] = $b;
    }
  }

  /**
   * Prints some block's parsed contents to output.
   * See parse for block naming conventions.
   * @todo add punch-trough mechanism to avoid caching of values
   */
  function out($block) {
    echo $this->text($block);
  }

  /**
   * Parses/prepares given block for output.
   * Assigned variables' values are "burned" into parsed text. Blockname is full
   * path from root, ie "myRootElement.someBlock.anotherBlock"
   */
  function parse($block) {
    $m = 'parse_' . strtr($block, '.', '_');
    $this->tc->$m();
  }

  /**
   * Returns block's parsed contents
   */
  function text($block) {
    $m = strtr($block, '.', '_');
    return $this->tc->$m();
  }

  ////////////////////////
  // Protected contents //
  ////////////////////////

  protected $outline = array();     // internal representation of source document
  protected $dependencies = array();// dependencies (included files) for outline
  protected static $class =         // template for whole compiled class
  '<?php
  class <name> {
    public $context = array();
    public $dependencies = array(<dependencies>);
    protected $buffer = array();
    protected $current = "";        // currently buffered block
    <methods>
  }
  ';
  protected static $blockMethod =   // template for compiled block (=> method)
  '
    function <name>() {
      if($this->current == "<name>") {
        if(!empty($this->buffer["<name>"])) {
          $out = $this->buffer["<name>"] . ob_get_clean();
          $this->buffer["<name>"] = "";
        } else {
          $out = ob_get_clean();
        }
        $this->current = "";
        return $out;
      } else {
        $out = @$this->buffer["<name>"];
        $this->buffer["<name>"] = "";
        return $out;
      }
    }

    function parse_<name>() {
      if($this->current != "<name>") {
        if($this->current) @$this->buffer[$this->current] .= ob_get_clean();
        ob_start();
        $this->current = "<name>";
      }
      ?><html><?php
    }
  ';

  /**
   * Converts xtemplate variable literals to php tags, returns converted text
   */
  protected function convertVariables($text) {
    $text = preg_replace('@{([a-zA-Z][a-zA-Z0-9_]*)}@', '<?=isset($this->context["$1"])?$this->context["$1"]:\'\'?>', $text);
    $text = preg_replace('@{([a-zA-Z]+)\.([a-zA-Z]+)}@', '<?=$this->context["$1"]->$2()?:\'\'?>', $text);
    return $text;
  }

  /**
   * Returns instance of compiled template class
   * @todo compiled class may not be required multiple times because of
   *  redeclaration issues. Some reset, debugs, ...?
   * @todo dependency injection of cache location
   */
  protected function compiledTemplate($file) {
    $cFile = SPEC . '/xtpl/' . md5($file) . '.php'; // comiled file name
    $cName = str_replace('-', '', ucfirst(basename($file, '.xtpl'))) . 'Tpl'; // compiled class name
    // main template modification check & load
    if( @filemtime($cFile) < filemtime($file) ) {
      $this->outlineRead($file);
      file_put_contents($cFile, $this->outlineCompiled($cName));
    }
    require_once($cFile);
    $t = new $cName();
    // dependecies modification check
    $modified = false;
    foreach($t->dependencies as $d) {
      if( filemtime($cFile) < filemtime($d) ) { // dependency enforced recompilation
        $this->outlineRead($file);
        file_put_contents($cFile, $this->outlineCompiled($cName));
        $modified = true;
      }
    }
    if($modified) throw new XTemplateRecompilationException();
    // return
    return $t;
  }

  /**
   * Adds referenced block with given name to outline. Target node in outline
   * is $node.
   */
  protected function nodePutblock($node, $blockname) {
    if(!$node) return; // skip root nodes
    $parent = $this->toIdent($node);
    $child = $this->toIdent(array_merge($node, array($blockname)));
    @$this->outline[$parent] .= '<?=$this->' . $child . '()?>';
  }

  /**
   * Adds text to specified node in outline.
   */
  protected function nodePuttext($node, $text) {
    if(!$node) return; // skip root nodes
    $node = $this->toIdent($node);
    $text = $this->convertVariables($text);
    @$this->outline[$node] .= $text;
  }

  /**
   * Compiles outline into (cached) php class used for output
   * @todo quotes
   * @todo class naming
   */
  protected function outlineCompiled($cName) {
    $methods = '';
    foreach ($this->outline as $ident => $block) {
      $methods .= strtr(self::$blockMethod, array(
        '<name>' => $ident,
        '<html>' => $this->reformat($block),
      ));
    }
    $class = strtr(self::$class, array(
      '<name>' => $cName,
      '<methods>' => $methods,
      '<dependencies>' => '"'.implode('","', $this->dependencies).'"',
    ));
    return $class;
  }

  /**
   * Reads given file into internal representation - outline
   */
  protected function outlineRead($file) {
    // split source file by block delimiters
    $delim = '<!-- (begin|end): ?([a-zA-Z][a-zA-Z0-9]*) -->';
    $f = file_get_contents($file);
    $f = preg_replace_callback('@{FILE "([^"]+)"}@', function($m){
      $this->dependencies[] = $m[1];
      return file_get_contents($m[1]);
    }, $f);
    $bloky = preg_split('@'.$delim.'@', $f, -1, PREG_SPLIT_DELIM_CAPTURE);
    // inits
    $len = count($bloky);
    $path = array();
    // walk through loaded blocks and delimiters
    for ($i = 0; $i < $len; $i++) {
      $blok = $bloky[$i];
      if ($blok == 'begin') {
        // beginning of block
        $i++; // skip to next token (which is block's name)
        array_push($path, $bloky[$i]);
      } else if ($blok == 'end') {
        // end of block
        $i++;
        $last = array_pop($path);
        $this->nodePutblock($path, $last);
      } else {
        // contents of block
        $this->nodePuttext($path, $blok);
      }
    }
  }

  /**
   * Reformats html code to make it readable in compiled class
   */
  protected function reformat($str) {
    $o = $str;
    //$o = trim($str); //TODO this also fails for example when multiple items should be separated by space
    //$o = preg_replace('@[ \t\n]+@', ' ', $o); //TODO this is not possible because of javascript comments to end of line
    return $o;
  }

  protected function toIdent($node) {
    $o = implode('_', $node);
    return $o;
  }

}

/**
 * General class for xtemplate exception
 */
abstract class XTemplateException extends Exception {}

/**
 * Already loaded template class had to be recompiled because of dependency
 * modification
 */
class XTemplateRecompilationException extends Exception {}
