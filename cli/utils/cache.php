<?php
/**
*   Rebuild framework caches
*/
class UtilsCache_CliController extends CliController {
  public function action_rebuild() {
    printf("Updating scripts cache... ");   
    CliControllersLoader::rebuildCache();
    printf("ok");

    printf("\nUpdating controllers cache... ");
    ControllersLoader::rebuildCache();
    printf("ok");

    CliControllersLoader::loadController('utils/lang', 'export');

    printf("\nUpdating templates cache... ");
    TemplatesLoader::rebuildCache();    
    printf("ok");
  }
}
?>