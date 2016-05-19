<?php
class TemplatesParser {
    protected static $list = array();
    protected static $build_hash = false;

    public static function parse() {
        self::$list = array();
        self::$build_hash = substr(md5(microtime(true)), 0, 10);

        self::loadTemplates();
        self::processTemplates();
        self::checkViewsParentLoop();

        return self::$list;
    }

    /**
    * Building a list of all views with basic meta info
    */
    protected static function loadTemplates($prefix = '/') {
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
                self::loadTemplates($prefix . $file . '/');
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

            $info = self::getTemplateMeta($prefix . $file);

            self::$list[$view] = $info;
        }
    }

    /**
    * Makes basic view info
    */
    protected static function getTemplateMeta($path) {
        $info = array(
            'parent' => '',
            'view_path' => $path,
        );

        $info['class'] = self::makeTemplateClassName($path);
        $info['cache_path'] = self::makeTemplateTmpPath($path);

        return $info;
    }

    protected static function makeTemplateClassName($path) {
        return 'T_' . substr(md5($path), 0, 20);
    }

    /**
    * Makes path to view's cache
    */
    protected static function makeTemplateTmpPath($path) {
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
                trigger_error('unable to create temproary dirrectory for templates cache');
                exit;
            }
        }

        $class = strtolower(self::makeTemplateClassName($path));

        return '/tmp/templates/' . $class . '.php';
    }

    protected static function processTemplates() {
        foreach (array_keys(self::$list) as $view) {
            self::processTemplate($view);
        }
    }

    /**
    * Processing view, generating class and putting it into cache
    */
    protected static function processTemplate($view) {
        $view_info = &self::$list[$view];

        $buffer = file_get_contents(ROOT_PATH . '/views/' . $view_info['view_path']);

        $regexp_name = '\(?[\'"]?([a-zA-Z0-9_.-]++)[\'"]?\)?';
        $regexp_extends = '#^\s*+@extends\s*+' . $regexp_name . '\s*+#s';

        if (preg_match($regexp_extends, $buffer, $data)) {
            $view_info['parent'] = $data[1];
            $buffer = str_replace($data[0], '', $buffer);
        }

        $sections = array();

        $buffer = self::processSections($buffer, $sections);

        if (!$view_info['parent']) {
            $sections['__main'] = $buffer;
        }

        $code = self::makeTemplateCode($view, $sections);

        $cache_path = ROOT_PATH . $view_info['cache_path'];

        $file = fopen($cache_path . '.tmp', 'wb');
        fwrite($file, $code);
        fclose($file);

        rename($cache_path . '.tmp', $cache_path);
    }

    /**
    * Extract all sections from buffer and returns what was left
    * @param (sections) extracted sections will be writed into that array
    */
    protected static function processSections($buffer, &$sections) {
        $regexp_name = '\(?[\'"]?([a-zA-Z0-9_.-]++)[\'"]?\)?';
        $regexp_sections = "#\s*@section\s*+{$regexp_name}\s*+((?:(?R)|.)*?)\s*+@(show|stop)\s*+#s";
        $regexp_yield = "#\s*+@yield\s*+{$regexp_name}#s";

        if (preg_match_all($regexp_sections, $buffer, $data, PREG_SET_ORDER)) {
            foreach ($data as $section) {
                $section_name = strtolower($section[1]);

                if ($section[3] == 'show') {
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

                $section_content = $section[2];
                $section_content = self::processSections($section_content, $sections);

                $sections[$section_name] = $section_content;
            }
        }

        if (preg_match_all($regexp_yield, $buffer, $data, PREG_SET_ORDER)) {
            foreach ($data as $section) {
                $section_name = strtolower($section[1]);

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
    * Making code for view's class with specified sections
    */
    protected static function makeTemplateCode($view, $sections) {
        $view_info = self::$list[$view];

        $code = "<?php\nclass " . $view_info['class'];

        if ($view_info['parent']) {
            if (!isset(self::$list[$view_info['parent']])) {
                trigger_error("view {$view} extends undefined view {$view_info['parent']}");
                exit;
            }

            $code .= ' extends ' . self::$list[$view_info['parent']]['class'];
        }
        else {
            $code .= ' extends BaseTemplate';
        }

        $code .= " {\n";

        foreach ($sections as $section_name => $section_content) {
            $code .= self::section2code(
                $view,
                $section_name,
                $section_content
            );
        }

        $code .= "\n}";
        return $code;
    }

    /**
    * Check if view contains itself somwhere in parent tree
    */
    protected static function checkViewsParentLoop() {
        foreach (array_keys(self::$list) as $view) {
            self::checkViewParentLoop($view);
        }
    }

    /**
    * Check if view contains itself somwhere in parent tree
    */
    protected static function checkViewParentLoop($view) {
        $parent = $view;
        $parents = array();

        do {
            if (isset($parents[$parent])) {
                $loop = implode(array_keys($parents), ' -> ') . ' -> '.$parent;
                trigger_error("view {$view} contains parents loop: {$loop}");
                exit;
            }

            $parents[$parent] = true;
            $parent = self::$list[$parent]['parent'];
        } while($parent);

        return true;
    }

    /**
    * Process and translate section into code
    */
    protected static function section2code($view, $section_name, $section_content) {
        $section_content = preg_replace(
            '#@parent(?![a-zA-Z0-9_-])#',
            "<?php parent::section_{$section_name}(); ?>",
            $section_content
        );

        $section_content = self::parseVariables($section_content, $view);
        $section_content = self::parseInclude($section_content, $view);
        $section_content = self::parseController($section_content, $view);
        $section_content = self::parseFor($section_content, $view);
        $section_content = self::parseIf($section_content, $view);
        $section_content = self::parseEval($section_content, $view);
        $section_content = self::parseExport($section_content, $view);
        $section_content = self::parseBuildHash($section_content, $view);
        $section_content = self::parseLang($section_content, $view);

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

    protected static function parseVariables($section_content, $view) {
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

    protected static function parseInclude($section_content, $view) {
        $regexp_name = '\(?[\'"]?([a-zA-Z0-9_.-]++)[\'"]?\)?';
        $regexp_include = "#\s*@include\s*+{$regexp_name}#s";

        $section_content = preg_replace(
            $regexp_include,
            '<?php TemplatesLoader::printView("$1", $this->args); ?>',
            $section_content
        );

        return $section_content;
    }

    protected static function parseController($section_content, $view) {
        $regexp_name = '\(?[\'"]?([a-zA-Z0-9_.-]++)[\'"]?\)?';
        $regexp_controller = "#\s*@controller\s*+{$regexp_name}#s";

        $section_content = preg_replace(
            $regexp_controller,
            '<?php ControllersLoader::load("$1", false); ?>',
            $section_content
        );

        return $section_content;
    }

    protected static function parseFor($section_content, $view) {
        //for & foreach
        $regexp = '#\s*@(for|foreach)\s*+(\((?:(?2)|.)*?\))\s*+((?:(?R)|.)*?)@end(?:\\1)(?![a-zA-Z0-9_-])#s';

        if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
            foreach ($data as $for) {
                $content = self::parseFor($for[3], $view);
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
        $regexp = '#\s*@while\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@endwhile(?![a-zA-Z0-9_-])#s';

        if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
            foreach ($data as $while) {
                $content = self::parseFor($while[2], $view);
                $cond = $while[1];

                $section_content = str_replace(
                    $while[0],
                    "<?php while {$cond} { ?>$content<?php } ?>",
                    $section_content
                );
            }
        }

        $regexp = "#\s*@forelse\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@empty((?:(?R)|.)*?)@endforelse(?![a-zA-Z0-9_-])#s";

        if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
            foreach ($data as $forelse) {
                $content = self::parseFor($forelse[2], $view);
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

    protected static function parseIf($section_content, $view) {
        $regexp = "#@if\s*+(\((?:(?1)|.)*?\))\s*+((?:(?R)|.)*?)@endif(?![a-zA-Z0-9_-])#s";

        if (preg_match_all($regexp, $section_content, $data, PREG_SET_ORDER)) {
            foreach ($data as $block) {
                $content = self::parseIf($block[2], false);

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

    protected static function parseEval($section_content, $view) {
        $regexp = '#\s*@eval\s*+((?:(?R)|.)*?)@endeval#s';

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

    protected static function parseExport($section_content, $view) {
        $regexp = '#\s*@export\s*+(\((?:(?1)|.)*?\))#s';

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

    protected static function parseBuildHash($section_content, $view) {
        $regexp = '#@build_hash(?![0-9a-zA-Z])#s';

        $section_content = preg_replace(
            $regexp,
            self::$build_hash,
            $section_content
        );

        return $section_content;
    }

    protected static function parseLang($section_content, $view) {
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
}
?>