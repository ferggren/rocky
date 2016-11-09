<?php
/**
 * @file Process, transtale & return templates
 * @name TemplatesParser
 * @author ferg <me@ferg.in>
 * @copyright 2015 ferg
 */

class TemplatesParser {
  /**
   *  Templates list
   */
  protected static $list = array();

  /**
   *  Uniq hash for current build
   */
  protected static $build_hash = false;

  /**
   *  Parse, process & return list of all templates
   */
  public static function parse() {
    self::$list = array();
    self::$build_hash = substr(md5(microtime(true)), 0, 8);

    self::__loadTemplates();
    self::__processTemplates();
    self::__checkViewsParentLoop();

    return self::$list;
  }

  /**
   *  Process & collects basic templates data
   */
  protected static function __loadTemplates($prefix = '/') {
    $path = ROOT_PATH . '/views/' . $prefix;

    if (!is_dir($path)) {
      return;
    }

    if (!($dir = opendir($path))) {
      return;
    }

    while ($file = readdir($dir)) {
      if ($file == '.' || $file == '..') {
        continue;
      }

      if (is_dir($path . $file)) {
        self::__loadTemplates($prefix . $file . '/');
        continue;
      }

      if (!preg_match('#^([0-9a-zA-Z_-]++)\.(?:php|html?)$#', $file, $data)) {
        continue;
      }

      $view = $prefix . $data[1];
      $view = strtolower($view);
      $view = trim($view, '/');
      $view = preg_replace('#/{2,}#', '/', $view);
      $view = str_replace('/', '.', $view);

      $info = self::__getTemplateMeta($prefix . $file);

      self::$list[$view] = $info;
    }
  }

  /**
   *  Return basic template info (parent & tmp path)
   *
   *  @param {string} path Path to a template
   *  @return {array} Template data
   */
  protected static function __getTemplateMeta($path) {
    $info = array(
      'parent' => '',
      'view_path' => $path,
    );

    $info['class'] = self::__makeTemplateClassName($path);
    $info['cache_path'] = self::__makeTemplateCachePath($path);

    return $info;
  }

  /**
   *  Generates template class name by template path
   *
   *  @param {string} path Template path
   *  @return {string} Template class name
   */
  protected static function __makeTemplateClassName($path) {
    return 'T_' . substr(md5($path), 0, 20);
  }

  /**
   *  Generates path for template cache file
   *
   *  @param {string} path Template path
   *  @return {string} Template cache path
   */
  protected static function __makeTemplateCachePath($path) {
    $tmp_path = ROOT_PATH . '/tmp/templates/';

    if (!is_dir($tmp_path)) {
      $oldumask = umask(0);

      mkdir(
        $tmp_path,
        octdec(str_pad("777", 4, '0', STR_PAD_LEFT)),
        true
      );

      umask($oldumask);

      if (!is_dir($tmp_path)) {
        trigger_error('Unable to create temproary dirrectory for templates cache');
        exit;
      }
    }

    $class = strtolower(self::__makeTemplateClassName($path));

    return '/tmp/templates/' . $class . '.php';
  }

  /**
   *  Process and translate templates into templates cache
   */
  protected static function __processTemplates() {
    foreach (array_keys(self::$list) as $view) {
      self::__processTemplate($view);
    }
  }

  /**
   *  Process template, generates template class and saves it into cache
   *
   *  @param {array} view Template basic info
   */
  protected static function __processTemplate($view) {
    $view_info = &self::$list[$view];

    $buffer = file_get_contents(ROOT_PATH . '/views/' . $view_info['view_path']);

    $regexp_name = '\(([\'"])([a-zA-Z0-9_.-]++)\\1\)';
    $regexp_extends = '#^\s*+@extends\s*+' . $regexp_name . '\s*+#s';

    if (preg_match($regexp_extends, $buffer, $data)) {
      $view_info['parent'] = $data[2];
      $buffer = str_replace($data[0], '', $buffer);
    }

    $sections = array();

    $buffer = self::__extractSections($buffer, $sections);

    if (!$view_info['parent']) {
      $sections['__main'] = $buffer;
    }

    $code = self::__template2code($view, $sections);

    $cache_path = ROOT_PATH . $view_info['cache_path'];

    $file = fopen($cache_path . '.tmp', 'wb');
    fwrite($file, $code);
    fclose($file);

    rename($cache_path . '.tmp', $cache_path);
  }

  /**
   *  Find and extract all sections from template
   *
   *  @param {string} buffer Template
   *  @param {array} sections Saves extracted sections into that array
   */
  protected static function __extractSections($buffer, &$sections) {
    $regexp_name = '\(([\'"])([a-zA-Z0-9_.-]++)\\1\)';
    $regexp_sections = "#\s*@section\s*+{$regexp_name}\s*+((?:(?R)|.)*?)\s*+@(show|stop)\s*+#s";
    $regexp_yield = "#\s*+@yield\s*+{$regexp_name}#s";

    if (preg_match_all($regexp_sections, $buffer, $data, PREG_SET_ORDER)) {
      foreach ($data as $section) {
        $section_name = strtolower($section[2]);

        if ($section[4] == 'show') {
          $buffer = str_replace(
            $section[0],
            "<?php \$this->section_{$section_name}(); ?>",
            $buffer
          );
        }
        else {
          $buffer = str_replace(
            $section[0],
            '',
            $buffer
          );
        }

        $section_content = $section[3];
        $section_content = self::__extractSections($section_content, $sections);

        $sections[$section_name] = $section_content;
      }
    }

    if (preg_match_all($regexp_yield, $buffer, $data, PREG_SET_ORDER)) {
      foreach ($data as $section) {
        $section_name = strtolower($section[2]);

        $buffer = str_replace(
          $section[0],
          "<?php \$this->section_{$section_name}(); ?>",
          $buffer
        );

        $sections[$section_name] = '';
      }
    }

    return $buffer;
  }

  /**
   *  Translate template into php class
   *
   *  @param {string} view View name
   *  @param {array} sections Sections list
   *  @return {string} Php class code
   */
  protected static function __template2code($view, $sections) {
    $view_info = self::$list[$view];

    $code = "<?php\nclass " . $view_info['class'];

    if ($view_info['parent']) {
      if (!isset(self::$list[$view_info['parent']])) {
        trigger_error(sprintf(
          'View %s tries to extend undefined view %s',
          $view,
          $view_info['parent']
        ));

        exit;
      }

      $code .= ' extends ' . self::$list[$view_info['parent']]['class'];
    }
    else {
      $code .= ' extends BaseTemplate';
    }

    $code .= " {\n";

    foreach ($sections as $section_name => $section_content) {
      $code .= self::__section2code(
        $view,
        $section_name,
        $section_content
      );
    }

    $code .= "\n}";
    return $code;
  }

  /**
   *  Check views for self-extending loop
   */
  protected static function __checkViewsParentLoop() {
    foreach (array_keys(self::$list) as $view) {
      self::__checkViewParentLoop($view);
    }
  }

  /**
   *  Check view fro self-extending loop
   */
  protected static function __checkViewParentLoop($view) {
    $parent = $view;
    $parents = array();

    do {
      if (isset($parents[$parent])) {
        $loop = implode(array_keys($parents), ' -> ') . ' -> '.$parent;

        trigger_error(sprintf(
          'View %s contains self-extending loop: %s',
          $view,
          $loop
        ));

        exit;
      }

      $parents[$parent] = true;
      $parent = self::$list[$parent]['parent'];
    } while($parent);

    return true;
  }

  /**
   *  Translate section into class's method code
   *
   *  @param {string} view View name
   *  @param {string} section_name Section name
   *  @param {string} section_content Raw section content
   *  @return {string} Class's method code
   */
  protected static function __section2code($view, $section_name, $section_content) {
    $section_content = preg_replace(
      '#@parent(?![a-zA-Z0-9_-])#',
      "<?php parent::section_{$section_name}(); ?>",
      $section_content
    );

    $section_content = self::__removeSpaces($section_content, $view);
    $section_content = self::__parseLang($section_content, $view);
    $section_content = self::__parseVariables($section_content, $view);
    $section_content = self::__parseInclude($section_content, $view);
    $section_content = self::__parseController($section_content, $view);
    $section_content = self::__parseFor($section_content, $view);
    $section_content = self::__parseIf($section_content, $view);
    $section_content = self::__parseEval($section_content, $view);
    $section_content = self::__parseExport($section_content, $view);
    $section_content = self::__parseBuildHash($section_content, $view);
    $section_content = self::__parseFileHash($section_content, $view);
    $section_content = self::__parseNospaces($section_content, $view);

    $section_content = '?>' . $section_content . '<?php';
    $section_content = str_replace('?><?php', '', $section_content);

    if ($section_content) {
      $export  = 'foreach($this->args as $arg_name => $arg_val){';
      $export .= '$$arg_name = $arg_val;';
      $export .= "}\n";

      $section_content = $export . $section_content;
    }

    $method = "public function section_{$section_name}() {\n";
    $method .= $section_content;
    $method .= "\n}\n";

    return $method;
  }

  /**
   *  Remove extra spaces from view
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __removeSpaces($section_content, $view) {
    static $tags = false;

    if (!$tags) {
      $tags = implode('|', array(
        'table', 'td', 'tr', 'tbody', 'thead',
        'div',
        'ul', 'li', 'ol',
        'd[dtl]',
        'script', 'style', 'meta', 'body', 'html', 'head', 'title', 'link',
        'form',
        'img',
        'br',
        'p',
        'a',
        'h[123456]',
      ));
    }

    $section_content = preg_replace(
      "#\s++(</?(?:{$tags})(?=[\s<>])[^<>]*+>)#uis",
      "$1",
      $section_content
    );

    $section_content = preg_replace(
      "#(</?(?:{$tags})(?=[\s<>])[^<>]*+>)\s++#uis",
      "$1",
      $section_content
    );

    return $section_content;
  }

  /**
   *  {{...}}
   *  {!!...!!}
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseVariables($section_content, $view) {
    $section_content = preg_replace(
      '#(?<!@){{(.*?)}}#',
      '<?php echo htmlspecialchars($1); ?>',
      $section_content
    );

    $section_content = preg_replace(
      '#(?<!@){!!(.*?)!!}#',
      '<?php echo($1); ?>',
      $section_content
    );

    return $section_content;
  }

  /**
   *  @include (...)
   *  @include (..., array(...))
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseInclude($section_content, $view) {
    $regexp_name = '\(?[\'"]?([a-zA-Z0-9_.-]++)[\'"]?\)?';
    $regexp_args = '(array\((?:(?2)|.)*?\)|[$][a-zA-Z][a-zA-Z0-9_-]*)';
    $regexp = "#@include\s*+\(\s*+($regexp_name)\s*+(?:,\s*+$regexp_args)?\s*+\)#s";

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $include) {
        $args = isset($include[3]) ? $include[3] : '$this->args';

        $section_content = str_replace(
          $include[0],
          '<?php TemplatesLoader::printView("'.$include[2].'", '.$args.'); ?>',
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  @controller (...)
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseController($section_content, $view) {
    $regexp_name = '\(([\'"])([a-zA-Z0-9_.-]++)\\1\)';
    $regexp_controller = "#\s*@controller\s*+{$regexp_name}#s";

    $section_content = preg_replace(
      $regexp_controller,
      '<?php ControllersLoader::load("$2", false); ?>',
      $section_content
    );

    return $section_content;
  }

  /**
   *  @for (...) ... @endfor
   *  @foreach (...) ... @endforeach
   *  @while (...) ... @endwhile
   *  @forelse (...) @empty ... @endforelse
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseFor($section_content, $view) {
    //for & foreach
    $regexp = '#@(for|foreach)\s*+(\((?:(?2)|.)*?\))\s*+((?:(?R)|.)*?)@end(?:\\1)(?![a-zA-Z0-9_-])#s';

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $for) {
        $content = self::__parseFor($for[3], $view);
        $cond = $for[2];
        $type = $for[1];

        $section_content = str_replace(
          $for[0],
          "<?php {$type} {$cond} { ?>$content<?php } ?>",
          $section_content
        );
      }
    }

    //while
    $regexp = '#@while\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@endwhile(?![a-zA-Z0-9_-])#s';

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $while) {
        $content = self::__parseFor($while[2], $view);
        $cond = $while[1];

        $section_content = str_replace(
          $while[0],
          "<?php while {$cond} { ?>$content<?php } ?>",
          $section_content
        );
      }
    }

    $regexp = "#@forelse\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@empty((?:(?R)|.)*?)@endforelse(?![a-zA-Z0-9_-])#s";

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $forelse) {
        $content = self::__parseFor($forelse[2], $view);
        $empty = $forelse[3];
        $cond = $forelse[1];

        if(!preg_match('#^\(\s*+(.+?) as #', $cond, $var)) {
          trigger_error("Incorrect forelse in view {$view}");
          exit;
        }

        $var = $var[1];

        $section_content = str_replace(
          $forelse[0],
          "<?php if(is_array($var) && count($var)) foreach {$cond} { ?>$content<?php } else { ?> $empty <?php }?>",
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  @if (...) ... @endif
   *  @if (...) ... @else ... @endif
   *  @if (...) ... @elseif (...) ... @endif
   *  @if (...) ... @elseif (...) ... @else ... @endif
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseIf($section_content, $view) {
    $regexp = "#@if\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@endif(?![a-zA-Z0-9_-])#s";

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $block) {
        $content = self::__parseIf($block[2], $view);

        $matches = preg_split(
          '#@((?:else(?![a-zA-Z0-9_-])|elseif\s*+(\((?:(?1)|.)*?\))))#is',
          $content,
          -1,
          PREG_SPLIT_DELIM_CAPTURE
        );

        if (count($matches) < 1) {
          return $section_content;
        }

        $blocks = array(
          array($block[1], array_shift($matches)),
        );

        while(count($matches)) {
          $type = array_shift($matches);

          if($blocks[count($blocks) - 1][0] === false) {
            break;
          }

          if($type == "else") {
            $blocks[] = array(
              false, array_shift($matches),
            );

            continue;
          }

          if(count($matches) < 2) {
            trigger_error("WTF?");
            exit;
          }

          $cond = array_shift($matches);
          $body = array_shift($matches);

          $blocks[] = array(
            $cond, $body,
          );
        }

        $if_section = "";

        foreach ($blocks as $if) {
          if(!$if[0]) {
            $if_section .= "<?php else { ?>{$if[1]}<?php } ?>";
            continue;
          }

          $op = $if_section ? "elseif" : "if";
          $if_section .= "<?php $op {$if[0]} { ?>{$if[1]}<?php } ?>";
        }

        $section_content = str_replace(
          $block[0], 
          $if_section,
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  @eval ... @endeval
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseEval($section_content, $view) {
    $regexp = '#@eval\s*+((?:(?R)|.)*?)@endeval#s';

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $eval) {
        $section_content = str_replace(
          $eval[0],
          "<?php {$eval[1]} ?>",
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  @export (...)
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseExport($section_content, $view) {
    $regexp = '#@export\s*+(\((?:(?1)|.)*?\))#s';

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $export) {
        $code  = '<?php ';
        $code .= 'if (is_array($__tmp = '.$export[1].')) { ';
        $code .= 'foreach ($__tmp as $__key => $__val) { ';
        $code .= '$this->args[$__key] = $$__key = $__val; ';
        $code .= '} } ?>';

        $section_content = str_replace(
          $export[0],
          $code,
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  @build_hash
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseBuildHash($section_content, $view) {
    $regexp = '#@build_hash(?![0-9a-zA-Z])#s';

    $section_content = preg_replace(
      $regexp,
      self::$build_hash,
      $section_content
    );

    return $section_content;
  }

  /**
   *  @lang($name)
   *  @lang($name, array(...))
   *  @lang
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseLang($section_content, $view) {
    $regexp_name = '(?:[\'"]([a-zA-Z0-9_.-]++)[\'"])|([$][a-zA-Z][a-zA-Z0-9_]*+)';
    $regexp_array = "(array\((?:(?2)|.)*?\)|[$][a-zA-Z][a-zA-Z0-9_-]*)";

    $regexp = "#@lang\s*+\(\s*+($regexp_name)\s*+(?:,\s*+$regexp_array)?\s*+\)#";

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $match) {
        if (isset($match[4])) {
          $replace = 'Lang::get(' . $match[1] . ', ' . $match[4] . ')';
        }
        else {
          $replace = 'Lang::get(' . $match[1] . ')';
        }

        $section_content = str_replace(
          $match[0],
          '<?php echo ' . $replace . '; ?>',
          $section_content
        );
      }
    }

    $regexp = '#@lang(?![0-9a-zA-Z])#s';

    $section_content = preg_replace(
      $regexp,
      '<?php echo Lang::getLang(); ?>',
      $section_content
    );

    return $section_content;
  }

  /**
   *  @nospaces ... @endnospaces
   *  @nospaces
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseNospaces($section_content, $view) {
    $regexp = "#@nospaces(?![0-9a-zA-Z_-])((?:(?R)|.)*?)@endnospaces(?![a-zA-Z0-9_-])#s";

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $block) {
        $content = self::__parseNospaces($block[1], $view);

        $content = preg_replace(
          '#\s++<(?![?])#ui',
          '<',
          $content
        );

        $content = preg_replace(
          '#(?<![?])>\s++#ui',
          '>',
          $content
        );
        
        $content = preg_replace('#\?>\s++<\?php#us', '', $content);

        $section_content = str_replace(
          $block[0],
          $content,
          $section_content
        );
      }
    }

    $section_content = preg_replace(
      '#\s*+@nospaces(?![0-9a-zA-Z_-])\s*+#us',
      '',
      $section_content
    );

    return $section_content;
  }

  /**
   *  @file_hash(...)
   *
   *  @param {string} section_content Section content
   *  @param {string} view Section's view
   *  @return {string} Processed section
   */
  protected static function __parseFileHash($section_content, $view) {
    $regexp = '#@file_hash\s*+\(([\'"])([^\'"]++)\\1\)#us';

    if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
      foreach ($data as $file) {
        $section_content = str_replace(
          $file[0],
          self::__getFileHash($file[2]),
          $section_content
        );
      }
    }

    return $section_content;
  }

  /**
   *  Return file hash
   *  If templates cache is disabled returns build_hash instead
   *
   *  @param {string} file_path Path to a file
   *  @return {string} File hash
   */
  protected static function __getFileHash($file_path) {
    if (!file_exists(ROOT_PATH . $file_path)) {
      return self::$build_hash;
    }
    
    if (!Config::get('app.cache_templates')) {
      return self::$build_hash;
    }

    return substr(md5(md5_file(ROOT_PATH . $file_path)), 0, 8);
  }
}
?>