<?php
if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    static $classMap = null;
    static $dynamicPrefixes = null;

    $prefix = 'NuclearEngagement\\';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    if ($classMap === null) {
        $classMap = [
            'NuclearEngagement\\Plugin' => '/includes/Plugin.php',
            'NuclearEngagement\\Loader' => '/includes/Loader.php',
            'NuclearEngagement\\Utils' => '/includes/Utils.php',
            'NuclearEngagement\\Activator' => '/includes/Activator.php',
            'NuclearEngagement\\Deactivator' => '/includes/Deactivator.php',
            'NuclearEngagement\\SettingsRepository' => '/includes/SettingsRepository.php',
            'NuclearEngagement\\SettingsSanitizer' => '/includes/SettingsSanitizer.php',
            'NuclearEngagement\\SettingsCache' => '/includes/SettingsCache.php',
            'NuclearEngagement\\SettingsAccessTrait' => '/includes/SettingsAccessTrait.php',
            'NuclearEngagement\PendingSettingsTrait' => '/includes/PendingSettingsTrait.php',
            'NuclearEngagement\\Container' => '/includes/Container.php',
            'NuclearEngagement\\ContainerRegistrar' => '/includes/ContainerRegistrar.php',
            'NuclearEngagement\\Defaults' => '/includes/Defaults.php',
            'NuclearEngagement\\OptinData' => '/includes/OptinData.php',
            'NuclearEngagement\\MetaRegistration' => '/includes/MetaRegistration.php',

            'NuclearEngagement\\Admin\\Admin' => '/admin/Admin.php',
            'NuclearEngagement\\Admin\\Dashboard' => '/admin/Dashboard.php',
            'NuclearEngagement\\Admin\\Onboarding' => '/admin/Onboarding.php',
            'NuclearEngagement\\Admin\\OnboardingPointers' => '/admin/OnboardingPointers.php',
            'NuclearEngagement\\Admin\\Settings' => '/admin/Settings.php',
            'NuclearEngagement\\Admin\\Setup' => '/admin/Setup.php',
            'NuclearEngagement\\Admin\\Controller\\OptinExportController' => '/admin/Controller/OptinExportController.php',

            'NuclearEngagement\\Admin\\Controller\\Ajax\\GenerateController' => '/admin/Controller/Ajax/GenerateController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\UpdatesController' => '/admin/Controller/Ajax/UpdatesController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\PointerController' => '/admin/Controller/Ajax/PointerController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\PostsCountController' => '/admin/Controller/Ajax/PostsCountController.php',
            'NuclearEngagement\\Admin\\Controller\\Ajax\\BaseController' => '/admin/Controller/Ajax/BaseController.php',

            'NuclearEngagement\\Front\\FrontClass' => '/front/FrontClass.php',
            'NuclearEngagement\\Front\\QuizShortcode' => '/front/QuizShortcode.php',
            'NuclearEngagement\\Front\\SummaryShortcode' => '/front/SummaryShortcode.php',
            'NuclearEngagement\\Front\\Controller\\Rest\\ContentController' => '/front/Controller/Rest/ContentController.php',

            'NuclearEngagement\\Services\\GenerationService' => '/includes/Services/GenerationService.php',
            'NuclearEngagement\\Services\\RemoteApiService' => '/includes/Services/RemoteApiService.php',
            'NuclearEngagement\\Services\\ContentStorageService' => '/includes/Services/ContentStorageService.php',
            'NuclearEngagement\\Services\\PointerService' => '/includes/Services/PointerService.php',
            'NuclearEngagement\\Services\\PostsQueryService' => '/includes/Services/PostsQueryService.php',
            'NuclearEngagement\\Services\\AutoGenerationService' => '/includes/Services/AutoGenerationService.php',

            'NuclearEngagement\\Requests\\ContentRequest' => '/includes/Requests/ContentRequest.php',
            'NuclearEngagement\\Requests\\GenerateRequest' => '/includes/Requests/GenerateRequest.php',
            'NuclearEngagement\\Requests\\PostsCountRequest' => '/includes/Requests/PostsCountRequest.php',
            'NuclearEngagement\\Requests\\UpdatesRequest' => '/includes/Requests/UpdatesRequest.php',
            'NuclearEngagement\\Responses\\GenerationResponse' => '/includes/Responses/GenerationResponse.php',
            'NuclearEngagement\\Responses\\UpdatesResponse' => '/includes/Responses/UpdatesResponse.php',

            'NuclearEngagement\\Admin\\Admin_Ajax' => '/admin/trait-admin-ajax.php',
            'NuclearEngagement\\Admin\\Admin_Assets' => '/admin/trait-admin-assets.php',
            'NuclearEngagement\\Admin\\Admin_AutoGenerate' => '/admin/trait-admin-autogenerate.php',
            'NuclearEngagement\\Admin\\Admin_Menu' => '/admin/trait-admin-menu.php',
            'NuclearEngagement\\Admin\\Admin_Quiz_Metabox' => '/admin/trait-admin-metabox-quiz.php',
            'NuclearEngagement\\Admin\\Admin_Summary_Metabox' => '/admin/trait-admin-metabox-summary.php',
            'NuclearEngagement\\Admin\\Admin_Metaboxes' => '/admin/trait-admin-metaboxes.php',
            'NuclearEngagement\\Admin\\SettingsPageCustomCSSTrait' => '/admin/trait-settings-custom-css.php',
            'NuclearEngagement\\Admin\\SettingsPageLoadTrait' => '/admin/trait-settings-page-load.php',
            'NuclearEngagement\\Admin\\SettingsPageSaveTrait' => '/admin/trait-settings-page-save.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeCoreTrait' => '/admin/trait-settings-sanitize-core.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeGeneralTrait' => '/admin/trait-settings-sanitize-general.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeOptinTrait' => '/admin/trait-settings-sanitize-optin.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeStyleTrait' => '/admin/trait-settings-sanitize-style.php',
            'NuclearEngagement\\Admin\\SettingsColorPickerTrait' => '/admin/SettingsColorPickerTrait.php',
            'NuclearEngagement\\Admin\\SettingsPageTrait' => '/admin/SettingsPageTrait.php',
            'NuclearEngagement\\Admin\\SettingsSanitizeTrait' => '/admin/SettingsSanitizeTrait.php',
            'NuclearEngagement\\Admin\\SetupHandlersTrait' => '/admin/SetupHandlersTrait.php',

            'NuclearEngagement\\Front\\AssetsTrait' => '/front/traits/AssetsTrait.php',
            'NuclearEngagement\\Front\\RestTrait' => '/front/traits/RestTrait.php',
            'NuclearEngagement\\Front\\ShortcodesTrait' => '/front/traits/ShortcodesTrait.php',
        ];

        $dynamicPrefixes = [
            'Admin\\' => '/admin/',
            'Front\\' => '/front/',
            'Services\\' => '/includes/Services/',
            'Requests\\' => '/includes/Requests/',
            'Responses\\' => '/includes/Responses/',
        ];
    }

    if (isset($classMap[$class])) {
        $file = NUCLEN_PLUGIN_DIR . ltrim($classMap[$class], '/');
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    $relative_class = substr($class, strlen($prefix));
    $subpath = '/includes/';
    foreach ($dynamicPrefixes as $nsPrefix => $path) {
        if (strpos($relative_class, $nsPrefix) === 0) {
            $subpath = $path;
            $relative_class = substr($relative_class, strlen($nsPrefix));
            break;
        }
    }

    $file = NUCLEN_PLUGIN_DIR . ltrim($subpath, '/') . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
