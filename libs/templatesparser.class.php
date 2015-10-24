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

    protected static function processSections($buffer, &$sections) {
        return $buffer;
    }

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

    protected static function checkViewsParentLoop() {
        foreach (array_keys(self::$list) as $view) {
            self::checkViewParentLoop($view);
        }
    }

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

    protected static function section2code($view, $section_name, $section_content) {
        return '';
    }
}
?>