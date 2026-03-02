<?php

namespace App\Services;

use App\Models\Build;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class CapacitorBuildService
{
    public function __construct(
        private string $templatePath,
        private string $outputPath
    ) {
        $this->templatePath = rtrim($this->resolvePath($templatePath, 'build-templates/webview-app'), '/');
        $this->outputPath = rtrim($this->resolvePath($outputPath, 'builds'), '/');
    }

    private array $processEnv = [];

    private function ensureBuildEnvironment(): void
    {
        $basePath = getenv('PATH') ?: '/usr/bin:/bin:/usr/sbin:/sbin';
        foreach (['/usr/local/bin', '/opt/homebrew/bin'] as $p) {
            if (is_dir($p) && ! str_contains($basePath, $p)) {
                $basePath = $p . ':' . $basePath;
            }
        }

        if (! getenv('JAVA_HOME')) {
            $javaPaths = ['/usr/local/opt/openjdk@17', '/opt/homebrew/opt/openjdk@17'];
            foreach ($javaPaths as $p) {
                if (is_dir($p)) {
                    putenv('JAVA_HOME=' . $p);
                    $basePath = $p . '/bin:' . $basePath;
                    break;
                }
            }
        }
        if (! getenv('ANDROID_HOME')) {
            $path = $this->detectAndroidSdk();
            if ($path) {
                putenv('ANDROID_HOME=' . $path);
            }
        }

        $nodePath = $this->resolveNodePath();
        if ($nodePath) {
            $nodeDir = dirname($nodePath);
            if (! str_contains($basePath, $nodeDir)) {
                $basePath = $nodeDir . ':' . $basePath;
            }
        }

        $baseEnv = array_filter([
            'PATH' => $basePath,
            'HOME' => getenv('HOME') ?: '/tmp',
            'ANDROID_HOME' => getenv('ANDROID_HOME') ?: $this->detectAndroidSdk() ?: '',
            'JAVA_HOME' => getenv('JAVA_HOME') ?: '',
        ], fn ($v) => $v !== '');
        $this->processEnv = array_merge($_ENV, $_SERVER, $baseEnv);
        $this->processEnv = array_filter($this->processEnv, fn ($v, $k) => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH);
    }

    private function detectAndroidSdk(): ?string
    {
        $paths = [
            getenv('HOME') . '/Library/Android/sdk',
            '/opt/homebrew/share/android-commandlinetools',
            '/usr/local/share/android-commandlinetools',
        ];
        foreach ($paths as $path) {
            if ($path && is_dir($path)) {
                return $path;
            }
        }

        return null;
    }

    /** 업로드 파일 경로 해석 (local=private, public 둘 다 확인) */
    private function resolveIconPath(string $path): ?string
    {
        $path = ltrim($path, '/');
        $candidates = [
            storage_path('app/public/' . $path),
            storage_path('app/private/' . $path),
            storage_path('app/' . $path),
        ];
        foreach ($candidates as $full) {
            if (File::exists($full)) {
                return $full;
            }
        }
        return null;
    }

    private function resolvePath(string $path, string $default): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $path = preg_replace('#^storage/app/#', '', $path) ?: $path;
        $path = $path ?: $default;
        return storage_path('app/' . ltrim($path, '/'));
    }

    public function build(Build $build): void
    {
        $this->ensureBuildEnvironment();

        $buildId = $build->id;
        $projectPath = "{$this->outputPath}/{$buildId}/project";
        $buildDir = "{$this->outputPath}/{$buildId}";

        if (! File::isDirectory($this->templatePath)) {
            throw new \RuntimeException('Capacitor 템플릿이 없습니다.');
        }

        File::deleteDirectory($projectPath);
        File::copyDirectory($this->templatePath, $projectPath);

        // 템플릿 node_modules는 복사하지 않고 매번 npm install로 새로 생성
        // (깨진 심볼릭 링크 등으로 copy 실패 방지)
        $projectNodeModules = "{$projectPath}/node_modules";
        if (File::isDirectory($projectNodeModules)) {
            File::deleteDirectory($projectNodeModules);
        }

        $this->injectConfig($projectPath, $build);
        $this->injectExtraPermissions($projectPath, $build);
        $this->copyIcons($projectPath, $build);

        $keystorePath = $this->generateKeystore($buildDir, $build);
        $this->configureSigning($projectPath, $keystorePath);

        $this->runNpmInstall($projectPath);
        $this->runCapSync($projectPath);
        $apkPath = $this->runAndroidBuild($projectPath);

        if ($apkPath && File::exists($apkPath)) {
            $apkName = basename($apkPath);
            $destPath = "{$this->outputPath}/{$buildId}/{$apkName}";
            File::ensureDirectoryExists(dirname($destPath));
            File::move($apkPath, $destPath);
            $build->update(['apk_path' => "builds/{$buildId}/{$apkName}"]);
        }

        if ($keystorePath) {
            $build->update(['keystore_path' => "builds/{$buildId}/release.keystore"]);
        }
    }

    private function injectConfig(string $projectPath, Build $build): void
    {
        $replacements = [
            '{{PACKAGE_ID}}' => $build->package_id,
            '{{APP_NAME}}' => $build->app_name,
            '{{WEB_URL}}' => $build->web_url,
        ];

        foreach (['capacitor.config.json', 'www/index.html'] as $file) {
            $path = "{$projectPath}/{$file}";
            if (File::exists($path)) {
                $content = str_replace(array_keys($replacements), array_values($replacements), File::get($path));
                File::put($path, $content);
            }
        }

        $buildGradlePath = "{$projectPath}/android/app/build.gradle";
        if (File::exists($buildGradlePath)) {
            $content = File::get($buildGradlePath);
            $content = str_replace('applicationId "com.webview.app"', 'applicationId "' . $build->package_id . '"', $content);
            $content = preg_replace('/versionCode \d+/', 'versionCode ' . $build->version_code, $content);
            $content = preg_replace('/versionName "[^"]+"/', 'versionName "' . $build->version_name . '"', $content);
            File::put($buildGradlePath, $content);
        }

        $stringsPath = "{$projectPath}/android/app/src/main/res/values/strings.xml";
        if (File::exists($stringsPath)) {
            $content = File::get($stringsPath);
            $content = preg_replace('/<string name="app_name">[^<]+<\/string>/', '<string name="app_name">' . htmlspecialchars($build->app_name, ENT_XML1) . '</string>', $content);
            $content = preg_replace('/<string name="title_activity_main">[^<]+<\/string>/', '<string name="title_activity_main">' . htmlspecialchars($build->app_name, ENT_XML1) . '</string>', $content);
            File::put($stringsPath, $content);
        }
    }

    /**
     * 2단계에서 선택한 추가 권한을 AndroidManifest 및 MainActivity에 주입.
     */
    private function injectExtraPermissions(string $projectPath, Build $build): void
    {
        $allowed = ['ACCESS_FINE_LOCATION', 'ACCESS_COARSE_LOCATION', 'CAMERA', 'RECORD_AUDIO', 'READ_CONTACTS', 'WRITE_CONTACTS', 'CALL_PHONE', 'READ_CALENDAR', 'WRITE_CALENDAR', 'SEND_SMS', 'RECEIVE_SMS', 'BLUETOOTH_CONNECT'];
        $selected = array_intersect($build->config_json['extra_permissions'] ?? [], $allowed);
        // 위치 선택 시 FINE + COARSE 둘 다 주입 (일반적으로 함께 사용)
        if (in_array('ACCESS_FINE_LOCATION', $selected, true)) {
            $selected[] = 'ACCESS_COARSE_LOCATION';
        }
        $extra = array_values(array_unique($selected));

        // AndroidManifest
        $xml = empty($extra)
            ? ''
            : implode("\n    ", array_map(fn ($p) => '<uses-permission android:name="android.permission.' . $p . '" />', array_unique($extra)));
        $manifestPath = "{$projectPath}/android/app/src/main/AndroidManifest.xml";
        if (File::exists($manifestPath)) {
            $content = File::get($manifestPath);
            $content = str_replace('<!-- {{EXTRA_PERMISSIONS}} -->', $xml, $content);
            File::put($manifestPath, $content);
        }

        // MainActivity: EXTRA_PERMISSIONS 배열 (런타임 요청용)
        $javaArray = empty($extra)
            ? '{}'
            : '{' . implode(', ', array_map(fn ($p) => '"android.permission.' . $p . '"', array_unique($extra))) . '}';
        $mainActivityPath = "{$projectPath}/android/app/src/main/java/com/webview/app/MainActivity.java";
        if (File::exists($mainActivityPath)) {
            $content = File::get($mainActivityPath);
            $content = str_replace('{{EXTRA_PERMISSIONS_ARRAY}}', $javaArray, $content);
            File::put($mainActivityPath, $content);
        }
    }

    /**
     * @capacitor/assets로 아이콘 생성 (공식 도구, 품질 우수)
     * 실패 시 PHP GD로 폴백
     */
    private function copyIcons(string $projectPath, Build $build): void
    {
        $iconPath = $this->resolveIconPath($build->app_icon_path);
        if (! $iconPath || ! File::exists($iconPath)) {
            return;
        }

        if ($this->generateIconsWithCapacitorAssets($projectPath, $iconPath)) {
            return;
        }

        $this->generateIconsWithPhpGd($projectPath, $iconPath);
    }

    private function generateIconsWithCapacitorAssets(string $projectPath, string $iconPath): bool
    {
        $assetsDir = "{$projectPath}/assets";
        File::ensureDirectoryExists($assetsDir);

        $logoPath = "{$assetsDir}/logo.png";
        if (! $this->copyOrConvertToPng($iconPath, $logoPath)) {
            return false;
        }

        $result = Process::path($projectPath)
            ->env($this->processEnv)
            ->timeout(60)
            ->run([
                'npx', '--yes', '@capacitor/assets',
                'generate',
                '--android',
                '--androidProject', 'android',
                '--assetPath', 'assets',
                '--iconBackgroundColor', '#FFFFFF',
            ]);

        return $result->successful();
    }

    private function copyOrConvertToPng(string $sourcePath, string $destPath): bool
    {
        $info = @getimagesize($sourcePath);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return File::copy($sourcePath, $destPath);
        }

        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default => null,
        };
        if (! $src) {
            return File::copy($sourcePath, $destPath);
        }

        $ok = imagepng($src, $destPath, 9);
        imagedestroy($src);

        return $ok;
    }

    /**
     * PHP GD 폴백: Android 공식 아이콘 규격 적용
     */
    private function generateIconsWithPhpGd(string $projectPath, string $iconPath): void
    {
        $resPath = "{$projectPath}/android/app/src/main/res";
        $sizes = [
            'mipmap-mdpi' => ['legacy' => 48, 'adaptive' => 108],
            'mipmap-hdpi' => ['legacy' => 72, 'adaptive' => 162],
            'mipmap-xhdpi' => ['legacy' => 96, 'adaptive' => 216],
            'mipmap-xxhdpi' => ['legacy' => 144, 'adaptive' => 324],
            'mipmap-xxxhdpi' => ['legacy' => 192, 'adaptive' => 432],
        ];

        foreach ($sizes as $dir => $px) {
            $targetDir = "{$resPath}/{$dir}";
            if (! File::isDirectory($targetDir)) {
                continue;
            }
            $this->resizeAndSaveIcon($iconPath, "{$targetDir}/ic_launcher.png", $px['legacy']);
            $this->resizeAndSaveIcon($iconPath, "{$targetDir}/ic_launcher_round.png", $px['legacy']);
            if (File::exists("{$targetDir}/ic_launcher_foreground.png")) {
                $this->resizeAndSaveIcon($iconPath, "{$targetDir}/ic_launcher_foreground.png", $px['adaptive']);
            }
        }

        $drawableV24 = "{$resPath}/drawable-v24/ic_launcher_foreground.xml";
        if (File::exists($drawableV24)) {
            File::delete($drawableV24);
        }
    }

    private function resizeAndSaveIcon(string $sourcePath, string $destPath, int $size): bool
    {
        $info = @getimagesize($sourcePath);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            File::copy($sourcePath, $destPath);
            return true;
        }

        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default => null,
        };
        if (! $src) {
            File::copy($sourcePath, $destPath);
            return true;
        }

        $dst = imagecreatetruecolor($size, $size);
        if (! $dst) {
            imagedestroy($src);
            File::copy($sourcePath, $destPath);
            return true;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
        $ok = imagepng($dst, $destPath, 9);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok;
    }

    private function generateKeystore(string $buildDir, Build $build): ?string
    {
        $keystorePath = "{$buildDir}/release.keystore";

        $result = Process::path($buildDir)
            ->env($this->processEnv)
            ->run([
                'keytool', '-genkey', '-v',
                '-keystore', 'release.keystore',
                '-alias', 'webview-build',
                '-keyalg', 'RSA', '-keysize', '2048', '-validity', '10000',
                '-storepass', 'webview123', '-keypass', 'webview123',
                '-dname', 'CN=Webview, OU=Build, O=Webview, L=Local, ST=Local, C=US',
            ]);

        return $result->successful() ? $keystorePath : null;
    }

    private function configureSigning(string $projectPath, ?string $keystorePath): void
    {
        if (! $keystorePath || ! File::exists($keystorePath)) {
            return;
        }

        $buildGradlePath = "{$projectPath}/android/app/build.gradle";
        if (! File::exists($buildGradlePath)) {
            return;
        }

        $content = File::get($buildGradlePath);

        if (str_contains($content, 'signingConfigs')) {
            return;
        }

        $signingBlock = <<<'GRADLE'

    signingConfigs {
        release {
            storeFile file("../../../release.keystore")
            storePassword "webview123"
            keyAlias "webview-build"
            keyPassword "webview123"
        }
    }
GRADLE;

        $content = preg_replace(
            '/    buildTypes \{/',
            $signingBlock . "\n    buildTypes {",
            $content,
            1
        );

        $content = preg_replace(
            '/        release \{\s*minifyEnabled false/',
            '        release {
            signingConfig signingConfigs.release
            minifyEnabled false',
            $content,
            1
        );

        File::put($buildGradlePath, $content);
    }

    private function resolveNodePath(): ?string
    {
        $paths = ['/usr/local/bin/node', '/opt/homebrew/bin/node'];
        $home = getenv('HOME') ?: '';
        if ($home) {
            $paths[] = "{$home}/.nvm/versions/node/*/bin/node";
            $paths[] = "{$home}/.fnm/current/bin/node";
        }
        foreach ($paths as $p) {
            if (str_contains($p, '*')) {
                $matches = glob($p);
                if (! empty($matches) && is_file($matches[0])) {
                    return $matches[0];
                }
                continue;
            }
            if (is_file($p)) {
                return $p;
            }
        }
        $out = @shell_exec('which node 2>/dev/null');
        if ($out && ($resolved = trim($out)) && is_file($resolved)) {
            return $resolved;
        }
        return null;
    }

    private function runNpmInstall(string $projectPath): void
    {
        $result = Process::path($projectPath)
            ->env($this->processEnv)
            ->timeout(180)
            ->run('npm install');
        if (! $result->successful()) {
            throw new \RuntimeException('npm install 실패: ' . $result->errorOutput());
        }
    }

    private function runCapSync(string $projectPath): void
    {
        $result = Process::path($projectPath)
            ->env($this->processEnv)
            ->timeout(120)
            ->run('npx cap sync android');
        if (! $result->successful()) {
            throw new \RuntimeException('cap sync 실패: ' . $result->errorOutput());
        }
    }

    private function runAndroidBuild(string $projectPath): ?string
    {
        $androidPath = "{$projectPath}/android";
        $gradlew = "{$androidPath}/gradlew";

        if (File::exists($gradlew)) {
            chmod($gradlew, 0755);
        }

        $sdkPath = getenv('ANDROID_HOME') ?: $this->detectAndroidSdk();
        if ($sdkPath) {
            $localProps = "{$androidPath}/local.properties";
            File::put($localProps, 'sdk.dir=' . str_replace('\\', '/', $sdkPath) . "\n");
        }

        $result = Process::path($androidPath)
            ->env($this->processEnv)
            ->timeout(300)
            ->run('./gradlew assembleRelease');

        if (! $result->successful()) {
            throw new \RuntimeException('Android 빌드 실패: ' . $result->errorOutput());
        }

        $apkPath = "{$androidPath}/app/build/outputs/apk/release/app-release.apk";

        return File::exists($apkPath) ? $apkPath : null;
    }
}
