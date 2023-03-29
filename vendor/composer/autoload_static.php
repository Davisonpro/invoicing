<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8b6d4385c391849a80038f0b0e87c8b5
{
    public static $files = array (
        'e8d544c98e79f913e13eae1306ab635e' => __DIR__ . '/..' . '/ayecode/wp-ayecode-ui/ayecode-ui-loader.php',
        '24583d3588ebda5228dd453cfaa070da' => __DIR__ . '/..' . '/ayecode/wp-font-awesome-settings/wp-font-awesome-settings.php',
        '42671a413efb740d7040437ff2a982cd' => __DIR__ . '/..' . '/ayecode/wp-super-duper/sd-functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'MaxMind\\Db\\' => 11,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'MaxMind\\Db\\' => 
        array (
            0 => __DIR__ . '/..' . '/maxmind-db/reader/src/MaxMind/Db',
        ),
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static $classMap = array (
        'AyeCode_Connect_Helper' => __DIR__ . '/..' . '/ayecode/ayecode-connect-helper/ayecode-connect-helper.php',
        'AyeCode_Deactivation_Survey' => __DIR__ . '/..' . '/ayecode/wp-deactivation-survey/wp-deactivation-survey.php',
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'WP_Super_Duper' => __DIR__ . '/..' . '/ayecode/wp-super-duper/wp-super-duper.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8b6d4385c391849a80038f0b0e87c8b5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8b6d4385c391849a80038f0b0e87c8b5::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8b6d4385c391849a80038f0b0e87c8b5::$classMap;

        }, null, ClassLoader::class);
    }
}
