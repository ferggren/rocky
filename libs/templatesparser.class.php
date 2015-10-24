<?php
class TemplatesParser {
    protected static $list = array();

    public static function parse() {
        self::$list = array();

        self::loadTemplates();
        self::processTemplates();
        self::checkViewsParentLoop();

        print_r(self::$list);
        exit;

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

            if (!preg_match('#^([0-9a-zA-Z]++)\.(?:php|html?)$#', $file, $data)) {
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
}
?>