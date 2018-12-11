<?php

use packager\{
    cli\Console, Event, JavaExec, Packager, Vendor
};
use php\io\Stream;
use php\lib\fs;
use compress\ZipArchive;
use compress\ZipArchiveEntry;
use php\lib\str;
use php\lang\System;

/**
 * Class AndroidPlugin
 * @jppm-task-prefix android
 * @jppm-task init as init
 * @jppm-task compile as build
 * @jppm-task compile as compile
 */
class AndroidPlugin
{
    /**
     * @param Event $e
     * @throws \php\io\IOException
     */
    public function init(Event $e)
    {
        if (!isset($packageData['deps']['jphp-android-ext'])) {
            Console::log('-> installing android extension ...');
            Tasks::run('add', [
                'jphp-android-ext'
            ]);
        }

        Console::log('-> install gradle ...');
        (new GradlePlugin($e))->install($e);

        // dirs
        fs::makeDir("./resources");

        // files
        fs::makeFile("./build.groovy");
        fs::makeFile("./resources/AndroidManifest.xml");

        $sdk = $_ENV["android.build.sdk"] ?: Console::read("sdkVersion :", 28);

        $settings = [
            "compileSdkVersion" => $sdk,
            "buildToolsVersion" => $_ENV["android.build.tools"] ?: Console::read("buildToolsVersion :", "28.0.3"),
            "targetSdkVersion" => $sdk,
            "appName" => $_ENV["android.app.name"] ?: Console::read("App name :", "test"),
            "applicationId" => $_ENV["android.app.id"] ?: Console::read("applicationId :", "org.venity.test"),
            "versionCode" => $_ENV["android.version.code"] ?: (int) Console::read("versionCode :", 1),
            "versionName" => $_ENV["android.version.name"] ?: Console::read("versionName :", "1.0"),
        ];

        $script = Stream::getContents("res://android/build.groovy");
        $xml = Stream::getContents("res://android/resources/AndroidManifest.xml");

        foreach ($settings as $key => $val)
            $script = str::replace($script, "%{$key}%", $val);

        foreach ($settings as $key => $val)
            $xml = str::replace($xml, "%{$key}%", $val);

        Stream::putContents("./build.gradle", $script);
        Stream::putContents("./resources/AndroidManifest.xml", $xml);

        $this->prepare_compiler();
    }

    public function prepare_compiler() {
        Console::log("-> prepare jphp compiler ...");
        fs::makeDir('./.venity/');
        fs::makeFile('./.venity/compiler.jar');
        Stream::putContents('./.venity/compiler.jar', Stream::getContents("res://jphp/compiler.jar"));
    }

    /**
     * @param Event $event
     * @throws \php\lang\IllegalArgumentException
     * @throws \php\lang\IllegalStateException
     */
    public function compile(Event $event)
    {
        $this->prepare_compiler();

        Console::log("-> compiling jphp ...");

        Tasks::run("app:build");

        $buildFileName = fs::abs("./build/{$event->package()->getName()}-{$event->package()->getVersion('last')}.jar");

        fs::makeDir('./build/out');

        $zip = new ZipArchive($buildFileName);
        $zip->readAll(function (ZipArchiveEntry $entry, ?Stream $stream) {
            if (str::endsWith(str::upper($entry->name), "META-INF/MANIFEST.MF")) return; // fix #2

            if (!$entry->isDirectory()) {
                fs::makeFile(fs::abs('./build/out/' . $entry->name));
                fs::copy($stream, fs::abs('./build/out/' . $entry->name));
            } else fs::makeDir(fs::abs('./build/out/' . $entry->name));
        });

        fs::delete($buildFileName);

        Console::log('-> starting compiler ...');
        $process = new \php\lang\Process([
            'java', '-jar', './.venity/compiler.jar',
            '--src', './build/out',
            '--dest', './libs/compile.jar'
        ], './');

        $exit = $process->inheritIO()->startAndWait()->getExitValue();

        if ($exit != 0) {
            Console::log("[ERROR] Error compiling jphp");
            exit($exit);
        } else Console::log(" -> done");

        Tasks::cleanDir("./build/out");

        $gradleTask = $event->flags()[0] ?? "build";

        /** @var \php\lang\Process $process */
        $process = (new GradlePlugin($event))->gradleProcess([
            $gradleTask
        ])->inheritIO()->startAndWait();

        $exit = $process->getExitValue();

        if ($exit != 0)
            exit($exit);

        fs::delete("./libs/compile.jar");
    }
}