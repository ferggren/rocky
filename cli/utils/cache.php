<?php
/**
*   Rebuild framework caches
*/
class UtilsCache_CliController extends CliController {
    public function action_rebuild() {
        CliControllersLoader::loadController('utils/lang', 'export');

        printf("\nUpdating templates cache... ");
        TemplatesLoader::rebuildCache();    
        printf("ok");

        printf("\nUpdating controllers cache... ");
        ControllersLoader::rebuildCache();
        printf("ok");

        printf("\nUpdating scripts cache... ");   
        CliControllersLoader::rebuildCache();
        printf("ok");
    }
}
?>