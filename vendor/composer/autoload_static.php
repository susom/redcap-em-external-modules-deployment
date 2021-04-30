<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc63780bc37518e81f992e9c8c9ff2181
{
    public static $prefixLengthsPsr4 = array(
        'F' =>
            array(
                'Firebase\\JWT\\' => 13,
            ),
    );

    public static $prefixDirsPsr4 = array(
        'Firebase\\JWT\\' =>
            array(
                0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
            ),
    );

    public static $classMap = array(
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc63780bc37518e81f992e9c8c9ff2181::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc63780bc37518e81f992e9c8c9ff2181::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitc63780bc37518e81f992e9c8c9ff2181::$classMap;

        }, null, ClassLoader::class);
    }
}
