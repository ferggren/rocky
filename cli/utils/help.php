<?php
class UtilsHelp_CliController extends CliController {
    public function action_default($script, $method) {
        if ($script && $method) {
            return self::show_script_method($script, $method);
        }

        if ($script) {
            return self::show_script_methods($script);
        }

        return self::show_scripts();
    }

    protected static function show_script_method($script, $method) {
        $script = strtolower($script);
        $method = strtolower($method);

        if (!is_array($scripts = CliControllersLoader::getControllers())) {
            trigger_error("Unable to load scripts list");
            exit;
        }

        if (!isset($scripts[$script])) {
            printf(
                "Script %s doesn't exists",
                $script
            );
            exit;
        }

        $script = $scripts[$script];

        if (!isset($script['actions'][$method])) {
            printf(
                "Method %s:%s doesn't exists",
                $script['script'], $method
            );
            exit;
        }

        $method = $script['actions'][$method];

        printf(
            "%s:%s argument(s) [%d]:",
            $script['script'],
            $method['action'],
            count($method['arguments'])
        );

        foreach ($method['arguments'] as $argument) {
            printf("\n --%s", $argument['name']);

            if (!is_null($argument['value'])) {
                printf(" [default = %s]", $argument['value']);
            }
        }
    }

    protected static function show_script_methods($script) {
        $script = strtolower($script);

        if (!is_array($scripts = CliControllersLoader::getControllers())) {
            trigger_error("Unable to load scripts list");
            exit;
        }

        if (!isset($scripts[$script])) {
            printf(
                "Script %s doesn't exists",
                $script
            );
            exit;
        }

        $script = $scripts[$script];

        ksort($script['actions']);

        printf("%s medhod(s) [%d]:", $script['script'], count($script['actions']));

        foreach ($script['actions'] as $action) {
            printf("\n %s", $action['action']);
        }
    }

    protected static function show_scripts() {
        if (!is_array($scripts = CliControllersLoader::getControllers())) {
            trigger_error("Unable to load scripts list");
            exit;
        }

        ksort($scripts);

        printf("Scripts list [%d]:", count($scripts));

        foreach ($scripts as $script) {
            printf("\n %s", $script['script']);
        }
    }
}
?>