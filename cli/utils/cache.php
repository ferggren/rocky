<?php
class UtilsCache_CliController extends CliController {
    public function action_rebuild() {
        printf("Updating templates cache... ");
        TemplatesLoader::rebuildCache();    
        printf("ok\n");

        printf("Updating controllers cache... ");
        ControllersLoader::rebuildCache();
        printf("ok\n");

        printf("Updating scripts cache... ");   
        CliControllersLoader::rebuildCache();
        printf("ok\n");
    }
}
?>