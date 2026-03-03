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
        $platforms = $build->config_json['platforms'] ?? ['android'];

        if (! File::isDirectory($this->templatePath)) {
            throw new \RuntimeException('Capacitor 템플릿이 없습니다.');
        }

        File::deleteDirectory($projectPath);
        File::copyDirectory($this->templatePath, $projectPath);

        // 템플릿 node_modules는 복사하지 않고 매번 npm install로 새로 생성
        $projectNodeModules = "{$projectPath}/node_modules";
        if (File::isDirectory($projectNodeModules)) {
            File::deleteDirectory($projectNodeModules);
        }

        $this->injectConfig($projectPath, $build);

        if (in_array('android', $platforms)) {
            $this->injectAppDomainToOAuthWebViewClient($projectPath, $build);
            $this->injectExtraPermissions($projectPath, $build);
            $this->copyGoogleServicesJson($projectPath, $build);
            $this->injectFcmConfig($projectPath, $build);
        }

        $this->copyIcons($projectPath, $build, $platforms);

        $this->runNpmInstall($projectPath);

        if (in_array('android', $platforms)) {
            $keystorePath = $this->generateKeystore($buildDir, $build);
            $this->configureSigning($projectPath, $keystorePath);
            $this->runCapSync($projectPath, 'android');
            $apkPath = $this->runAndroidBuild($projectPath);

            if ($apkPath && File::exists($apkPath)) {
                $apkName = basename($apkPath);
                $destPath = "{$this->outputPath}/{$buildId}/{$apkName}";
                File::ensureDirectoryExists(dirname($destPath));
                File::move($apkPath, $destPath);
                $build->update(['apk_path' => "builds/{$buildId}/{$apkName}"]);
            }

            if (isset($keystorePath) && $keystorePath) {
                $build->update(['keystore_path' => "builds/{$buildId}/release.keystore"]);
            }
        }

        if (in_array('ios', $platforms)) {
            $this->injectConfigIos($projectPath, $build);
            $this->runCapSync($projectPath, 'ios');
            $ipaPath = $this->runIosBuild($projectPath, $buildDir);

            if ($ipaPath && File::exists($ipaPath)) {
                $ipaName = basename($ipaPath);
                $destPath = "{$this->outputPath}/{$buildId}/{$ipaName}";
                File::ensureDirectoryExists(dirname($destPath));
                File::move($ipaPath, $destPath);
                $build->update(['ipa_path' => "builds/{$buildId}/{$ipaName}"]);
            }
        }
    }

    private function injectConfig(string $projectPath, Build $build): void
    {
        $bundleId = $build->config_json['bundle_id'] ?? $build->package_id;
        $appHost = parse_url($build->web_url, PHP_URL_HOST) ?: '';
        $replacements = [
            '{{PACKAGE_ID}}' => $build->package_id,
            '{{APP_NAME}}' => $build->app_name,
            '{{WEB_URL}}' => $build->web_url,
        ];
        if ($appHost && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $appHost)) {
            $replacements['"{{APP_HOST}}"'] = '"' . $appHost . '"';
        } else {
            $replacements[",\n      \"{{APP_HOST}}\""] = '';
        }

        foreach (['capacitor.config.json', 'www/index.html'] as $file) {
            $path = "{$projectPath}/{$file}";
            if (File::exists($path)) {
                $content = str_replace(array_keys($replacements), array_values($replacements), File::get($path));
                File::put($path, $content);
            }
        }

        // iOS: capacitor.config appId는 bundle_id 사용, allowNavigation에 앱 도메인 포함
        $iosConfigPath = "{$projectPath}/ios/App/App/capacitor.config.json";
        if (File::exists($iosConfigPath)) {
            $content = File::get($iosConfigPath);
            $content = str_replace('{{PACKAGE_ID}}', $bundleId, $content);
            $content = str_replace('{{APP_NAME}}', $build->app_name, $content);
            $content = str_replace('{{WEB_URL}}', $build->web_url, $content);
            if ($appHost && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $appHost)) {
                $content = str_replace('"{{APP_HOST}}"', '"' . $appHost . '"', $content);
            } else {
                $content = str_replace(",\n\t\t\t\"{{APP_HOST}}\"", '', $content);
            }
            File::put($iosConfigPath, $content);
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
     * iOS 전용 설정 주입: Info.plist, project.pbxproj
     */
    private function injectConfigIos(string $projectPath, Build $build): void
    {
        $bundleId = $build->config_json['bundle_id'] ?? $build->package_id;

        $infoPlistPath = "{$projectPath}/ios/App/App/Info.plist";
        if (File::exists($infoPlistPath)) {
            $content = File::get($infoPlistPath);
            $content = preg_replace('/(<key>CFBundleDisplayName<\/key>\s*)<string>[^<]*<\/string>/s', '$1<string>' . htmlspecialchars($build->app_name, ENT_XML1) . '</string>', $content);
            File::put($infoPlistPath, $content);
        }

        $pbxPath = "{$projectPath}/ios/App/App.xcodeproj/project.pbxproj";
        if (File::exists($pbxPath)) {
            $content = File::get($pbxPath);
            $content = preg_replace('/PRODUCT_BUNDLE_IDENTIFIER = com\.webview\.app;/', 'PRODUCT_BUNDLE_IDENTIFIER = ' . $bundleId . ';', $content);
            $content = preg_replace('/MARKETING_VERSION = [^;]+;/', 'MARKETING_VERSION = ' . $build->version_name . ';', $content);
            $content = preg_replace('/CURRENT_PROJECT_VERSION = \d+;/', 'CURRENT_PROJECT_VERSION = ' . $build->version_code . ';', $content);
            File::put($pbxPath, $content);
        }
    }

    /**
     * 앱 서버 도메인(web_url) URL을 WebView 내에서 로드하도록 OAuthWebViewClient에 패턴 주입.
     * FCM 채팅 등 동일 도메인 URL이 브라우저로 열리는 것 방지 (카카오 OAuth와 동일한 방식).
     */
    private function injectAppDomainToOAuthWebViewClient(string $projectPath, Build $build): void
    {
        $path = "{$projectPath}/android/app/src/main/java/com/webview/app/OAuthWebViewClient.java";
        if (! File::exists($path)) {
            return;
        }
        $host = parse_url($build->web_url, PHP_URL_HOST);
        $pattern = 'null';
        if ($host && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $host)) {
            $escaped = str_replace('\\', '\\\\', preg_quote($host, '/'));
            $pattern = 'Pattern.compile("^https?://([^/]*\\\\.)?' . $escaped . '(/.*)?", Pattern.CASE_INSENSITIVE)';
        }
        $baseUrl = $this->getAppBaseUrl($build->web_url);
        $content = File::get($path);
        $content = str_replace('{{APP_DOMAIN_PATTERN}}', $pattern, $content);
        $content = str_replace('{{APP_BASE_URL}}', addslashes($baseUrl), $content);
        File::put($path, $content);
    }

    private function getAppBaseUrl(string $webUrl): string
    {
        $scheme = parse_url($webUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($webUrl, PHP_URL_HOST) ?: '';
        $port = parse_url($webUrl, PHP_URL_PORT);
        $base = $scheme . '://' . $host;
        if ($port && $port !== ($scheme === 'https' ? 443 : 80)) {
            $base .= ':' . $port;
        }
        return rtrim($base, '/');
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
     * FCM 사용 시 google-services.json을 android/app/에 복사.
     */
    private function copyGoogleServicesJson(string $projectPath, Build $build): void
    {
        $path = $build->config_json['google_services_path'] ?? null;
        if (! $path || ! ($build->config_json['fcm_enabled'] ?? false)) {
            return;
        }

        $srcPath = $this->resolveIconPath($path);
        if (! $srcPath || ! File::exists($srcPath)) {
            $candidates = [
                storage_path('app/public/' . ltrim($path, '/')),
                storage_path('app/private/' . ltrim($path, '/')),
            ];
            foreach ($candidates as $c) {
                if (File::exists($c)) {
                    $srcPath = $c;
                    break;
                }
            }
        }
        if (! $srcPath || ! File::exists($srcPath)) {
            return;
        }

        $destPath = "{$projectPath}/android/app/google-services.json";
        $json = json_decode(File::get($srcPath), true);
        if (is_array($json) && isset($json['client']) && is_array($json['client'])) {
            $packageId = $build->package_id;
            foreach ($json['client'] as &$client) {
                if (isset($client['client_info']['android_client_info']['package_name'])) {
                    $client['client_info']['android_client_info']['package_name'] = $packageId;
                }
            }
            unset($client);
            File::put($destPath, json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            File::copy($srcPath, $destPath);
        }
    }

    /**
     * FCM 사용 시 firebase-messaging 의존성 추가, Service 등록, MainActivity/OAuthWebViewClient에 FCM 코드 주입.
     */
    private function injectFcmConfig(string $projectPath, Build $build): void
    {
        $fcmEnabled = $build->config_json['fcm_enabled'] ?? false;

        if ($fcmEnabled) {
            $this->addFirebaseMessagingDependency($projectPath);
            $this->injectFcmManifest($projectPath);
            $this->injectFcmMainActivity($projectPath, $build);
            $this->injectFcmOAuthWebViewClient($projectPath, true);
            $this->ensureAppFirebaseMessagingService($projectPath, $build);
        } else {
            $this->injectFcmMainActivity($projectPath, null);
            $this->injectFcmOAuthWebViewClient($projectPath, false);
        }
    }

    private function addFirebaseMessagingDependency(string $projectPath): void
    {
        $buildGradlePath = "{$projectPath}/android/app/build.gradle";
        if (! File::exists($buildGradlePath)) {
            return;
        }
        $content = File::get($buildGradlePath);
        if (str_contains($content, 'firebase-messaging')) {
            return;
        }
        $content = preg_replace(
            '/implementation project\(\':capacitor-cordova-android-plugins\'\)/',
            "implementation project(':capacitor-cordova-android-plugins')\n    implementation 'com.google.firebase:firebase-messaging:23.4.1'",
            $content,
            1
        );
        File::put($buildGradlePath, $content);
    }

    private function injectFcmManifest(string $projectPath): void
    {
        $manifestPath = "{$projectPath}/android/app/src/main/AndroidManifest.xml";
        if (! File::exists($manifestPath)) {
            return;
        }
        $content = File::get($manifestPath);
        if (str_contains($content, 'AppFirebaseMessagingService')) {
            return;
        }
        $serviceBlock = "\n        <service\n            android:name=\".AppFirebaseMessagingService\"\n            android:exported=\"false\">\n            <intent-filter>\n                <action android:name=\"com.google.firebase.MESSAGING_EVENT\" />\n            </intent-filter>\n        </service>";
        $content = preg_replace(
            '/    <\/application>/',
            $serviceBlock . "\n    </application>",
            $content,
            1
        );
        if (! str_contains($content, 'default_notification_icon')) {
            $metaBlock = "\n        <meta-data\n            android:name=\"com.google.firebase.messaging.default_notification_icon\"\n            android:resource=\"@drawable/ic_stat_ic_notification\" />\n        <meta-data\n            android:name=\"com.google.firebase.messaging.default_notification_channel_id\"\n            android:value=\"@string/default_notification_channel_id\" />";
            $content = preg_replace(
                '/android:theme="@style\/AppTheme">/',
                'android:theme="@style/AppTheme">' . $metaBlock,
                $content,
                1
            );
        }
        File::put($manifestPath, $content);
    }

    private function injectFcmMainActivity(string $projectPath, ?Build $build): void
    {
        $path = "{$projectPath}/android/app/src/main/java/com/webview/app/MainActivity.java";
        if (! File::exists($path)) {
            return;
        }
        $enabled = $build !== null;
        $clickKey = $enabled ? ($build->config_json['fcm_click_url_key'] ?? 'action_url') : 'action_url';
        if (empty($clickKey) || ! preg_match('/^[a-zA-Z0-9_-]+$/', $clickKey)) {
            $clickKey = 'action_url';
        }
        $content = File::get($path);
        $content = str_replace('{{FCM_INIT_BLOCK}}', $enabled ? $this->getFcmInitBlock() : '// FCM disabled', $content);
        $content = str_replace('{{FCM_TOKEN_TO_WEB_BLOCK}}', $enabled ? $this->getFcmTokenToWebBlock() : '// FCM disabled', $content);
        $content = str_replace('{{FCM_RESUME_HANDLER}}', $enabled ? 'handleFcmClickUrl();' : '', $content);
        $baseUrl = $enabled ? $this->getAppBaseUrl($build->web_url) : '';
        $content = str_replace('{{FCM_CLICK_HANDLER}}', $enabled ? $this->getFcmClickHandlerBlock($clickKey, $baseUrl) : '// FCM disabled', $content);
        File::put($path, $content);
    }

    private function getFcmInitBlock(): string
    {
        return <<<'JAVA'
        initFcmAndPassTokenToWeb();
        handleFcmClickUrl();
JAVA;
    }

    private function getFcmTokenToWebBlock(): string
    {
        return <<<'JAVA'

    private static String fcmToken = null;

    private void initFcmAndPassTokenToWeb() {
        try {
            com.google.firebase.messaging.FirebaseMessaging.getInstance().getToken()
                .addOnCompleteListener(task -> {
                    if (!task.isSuccessful()) return;
                    String t = task.getResult();
                    if (t != null) { fcmToken = t; passFcmTokenToWeb(t); }
                });
        } catch (Exception ignored) {}
    }

    void passFcmTokenToWebIfReady(android.webkit.WebView wv) {
        if (fcmToken != null && wv != null) passFcmTokenToWeb(wv, fcmToken);
    }

    private void passFcmTokenToWeb(String token) {
        com.getcapacitor.Bridge b = getBridge();
        if (b != null) passFcmTokenToWeb(b.getWebView(), token);
    }

    private void passFcmTokenToWeb(android.webkit.WebView wv, String token) {
        if (wv == null || token == null) return;
        String escaped = token.replace("\\", "\\\\").replace("'", "\\'");
        wv.evaluateJavascript("(function(){if(typeof window.onFcmTokenReady==='function')window.onFcmTokenReady('" + escaped + "');})();", null);
    }
JAVA;
    }

    private function getFcmClickHandlerBlock(string $dataKey, string $baseUrl): string
    {
        $key = addslashes($dataKey);
        $base = addslashes($baseUrl);
        $charset = 'UTF-8';
        return <<<JAVA

    private static final String FCM_DATA_KEY = "{$key}";
    private static final String APP_BASE_URL = "{$base}";
    private static final String PREFS_AUTH_TOKEN = "app_auth_token";
    private static final int FCM_CLICK_RETRY_DELAY_MS = 150;
    private static final int FCM_CLICK_RETRY_MAX = 20;

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleFcmClickUrl();
    }

    private void handleFcmClickUrl() {
        handleFcmClickUrlWithRetry(0);
    }

    private void handleFcmClickUrlWithRetry(int attempt) {
        Intent i = getIntent();
        if (i == null) return;
        String actionUrl = i.getStringExtra("fcm_click_url");
        if (actionUrl == null || actionUrl.isEmpty()) actionUrl = i.getStringExtra(FCM_DATA_KEY);
        if (actionUrl == null || actionUrl.isEmpty()) return;
        Bridge b = getBridge();
        android.webkit.WebView wv = b != null ? b.getWebView() : null;
        if (wv != null) {
            i.removeExtra("fcm_click_url");
            i.removeExtra(FCM_DATA_KEY);
            String redirect = actionUrl.startsWith("http") ? actionUrl : (actionUrl.startsWith("/") ? actionUrl : "/" + actionUrl);
            String loadUrl;
            String token = getSharedPreferences("webview_app", MODE_PRIVATE).getString(PREFS_AUTH_TOKEN, null);
            try {
                String encRedirect = java.net.URLEncoder.encode(redirect, "{$charset}");
                if (token != null && !token.isEmpty()) {
                    loadUrl = APP_BASE_URL + "/auth/app-login?token=" + java.net.URLEncoder.encode(token, "{$charset}") + "&redirect=" + encRedirect;
                } else {
                    loadUrl = APP_BASE_URL + "/login?redirect=" + encRedirect;
                }
            } catch (java.io.UnsupportedEncodingException e) {
                loadUrl = APP_BASE_URL + redirect;
            }
            wv.loadUrl(loadUrl);
            return;
        }
        if (attempt < FCM_CLICK_RETRY_MAX) {
            getWindow().getDecorView().postDelayed(() -> handleFcmClickUrlWithRetry(attempt + 1), FCM_CLICK_RETRY_DELAY_MS);
        }
    }
JAVA;
    }

    private function injectFcmOAuthWebViewClient(string $projectPath, bool $enabled): void
    {
        $path = "{$projectPath}/android/app/src/main/java/com/webview/app/OAuthWebViewClient.java";
        if (! File::exists($path)) {
            return;
        }
        $content = File::get($path);
        $bridge = $enabled
            ? "if(view.getContext() instanceof MainActivity){((MainActivity)view.getContext()).passFcmTokenToWebIfReady(view);} view.evaluateJavascript(\"(function(){if(typeof window.onFcmTokenReady!=='function'){window.onFcmTokenReady=function(t){if(window._fcmTokenCb)window._fcmTokenCb(t);};}})();\", null);"
            : '// FCM disabled';
        $content = str_replace('{{FCM_BRIDGE_INJECT}}', $bridge, $content);
        File::put($path, $content);
    }

    private function ensureAppFirebaseMessagingService(string $projectPath, Build $build): void
    {
        $servicePath = "{$projectPath}/android/app/src/main/java/com/webview/app/AppFirebaseMessagingService.java";
        $pkgDir = dirname($servicePath);
        File::ensureDirectoryExists($pkgDir);
        $clickKey = $build->config_json['fcm_click_url_key'] ?? 'action_url';
        if (empty($clickKey) || ! preg_match('/^[a-zA-Z0-9_-]+$/', $clickKey)) {
            $clickKey = 'action_url';
        }
        File::put($servicePath, $this->getAppFirebaseMessagingServiceContent($clickKey));
    }

    private function getAppFirebaseMessagingServiceContent(string $clickUrlKey): string
    {
        $key = addslashes($clickUrlKey);
        return str_replace('{{FCM_CLICK_URL_KEY}}', $key, <<<'JAVA'
package com.webview.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.os.Build;
import androidx.core.app.NotificationCompat;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

public class AppFirebaseMessagingService extends FirebaseMessagingService {

    private static final String CHANNEL_ID = "fcm_default_channel";

    @Override
    public void onNewToken(String token) {
        super.onNewToken(token);
    }

    private static final String CLICK_URL_KEY = "{{FCM_CLICK_URL_KEY}}";

    @Override
    public void onMessageReceived(RemoteMessage message) {
        java.util.Map<String, String> data = message.getData();
        String title = "알림";
        String body = "";
        String url = data != null && data.containsKey(CLICK_URL_KEY) ? data.get(CLICK_URL_KEY) : null;

        if (message.getNotification() != null) {
            title = message.getNotification().getTitle() != null ? message.getNotification().getTitle() : title;
            body = message.getNotification().getBody() != null ? message.getNotification().getBody() : body;
        } else if (data != null) {
            if (data.containsKey("title")) title = data.get("title");
            if (data.containsKey("body")) body = data.get("body");
        }

        boolean shouldNotify = message.getNotification() != null || (url != null && !url.isEmpty());
        if (shouldNotify) {
            createNotificationChannel();
            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            Intent i = new Intent(this, MainActivity.class);
            i.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
            if (url != null && !url.isEmpty()) {
                i.putExtra("fcm_click_url", url);
            }
            PendingIntent pi = PendingIntent.getActivity(this, 0, i, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
            NotificationCompat.Builder b = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_stat_ic_notification)
                .setContentTitle(title != null ? title : "알림")
                .setContentText(body != null ? body : "")
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setContentIntent(pi)
                .setAutoCancel(true);
            nm.notify((int) System.currentTimeMillis(), b.build());
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel ch = new NotificationChannel(CHANNEL_ID, "알림", NotificationManager.IMPORTANCE_HIGH);
            ch.enableVibration(true);
            ch.enableLights(true);
            ((NotificationManager) getSystemService(NOTIFICATION_SERVICE)).createNotificationChannel(ch);
        }
    }
}
JAVA
        );
    }

    /**
     * @capacitor/assets로 아이콘 생성 (공식 도구, 품질 우수)
     * 실패 시 PHP GD로 폴백
     */
    private function copyIcons(string $projectPath, Build $build, array $platforms = ['android']): void
    {
        $iconPath = $this->resolveIconPath($build->app_icon_path);
        if (! $iconPath || ! File::exists($iconPath)) {
            return;
        }

        if (in_array('android', $platforms)) {
            if ($this->generateIconsWithCapacitorAssets($projectPath, $iconPath)) {
                $this->generateNotificationIconFromAppIcon($projectPath, $iconPath, $build);
            } else {
                $this->generateIconsWithPhpGd($projectPath, $iconPath);
                $this->generateNotificationIconFromAppIcon($projectPath, $iconPath, $build);
            }
        }

        if (in_array('ios', $platforms)) {
            $this->generateIconsForIos($projectPath, $iconPath);
        }
    }

    /**
     * iOS 앱 아이콘 생성 (@capacitor/assets 또는 PHP GD)
     */
    private function generateIconsForIos(string $projectPath, string $iconPath): void
    {
        if ($this->generateIconsWithCapacitorAssetsIos($projectPath, $iconPath)) {
            return;
        }
        $this->generateIconsForIosWithPhpGd($projectPath, $iconPath);
    }

    private function generateIconsWithCapacitorAssetsIos(string $projectPath, string $iconPath): bool
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
                '--ios',
                '--iosProject', 'ios/App',
                '--assetPath', 'assets',
                '--iconBackgroundColor', '#FFFFFF',
            ]);
        return $result->successful();
    }

    private function generateIconsForIosWithPhpGd(string $projectPath, string $iconPath): void
    {
        $appiconset = "{$projectPath}/ios/App/App/Assets.xcassets/AppIcon.appiconset";
        if (! File::isDirectory($appiconset)) {
            return;
        }
        $this->resizeAndSaveIcon($iconPath, "{$appiconset}/AppIcon-512@2x.png", 1024);
    }

    /**
     * FCM 사용 시 앱 아이콘에서 알림 아이콘(흰색 실루엣) 생성.
     * Android 트레이에는 흰색/투명 아이콘만 표시 가능.
     */
    private function generateNotificationIconFromAppIcon(string $projectPath, string $iconPath, Build $build): void
    {
        if (! ($build->config_json['fcm_enabled'] ?? false)) {
            return;
        }

        $drawablePath = "{$projectPath}/android/app/src/main/res/drawable";
        if (! File::isDirectory($drawablePath)) {
            File::ensureDirectoryExists($drawablePath);
        }

        $info = @getimagesize($iconPath);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return;
        }

        $src = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($iconPath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($iconPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($iconPath) : null,
            default => null,
        };
        if (! $src) {
            return;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $tmp = imagecreatetruecolor($w, $h);
        if (! $tmp) {
            imagedestroy($src);
            return;
        }
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $transparent);
        $white = imagecolorallocatealpha($tmp, 255, 255, 255, 0);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = @imagecolorat($src, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $luma = ($r + $g + $b) / 3;
                // 로고 영역만 흰색으로. 배경(투명/흰색) 제외. Android는 alpha 채널만 사용.
                $isOpaque = $a < 60;
                $isDarkOrColored = $luma < 250;
                $visible = ($info[2] === IMAGETYPE_PNG && $isOpaque && $isDarkOrColored)
                    || ($info[2] !== IMAGETYPE_PNG && $isDarkOrColored);
                if ($visible) {
                    imagesetpixel($tmp, $x, $y, $white);
                }
            }
        }

        $size = 96;
        $dst = imagecreatetruecolor($size, $size);
        if (! $dst) {
            imagedestroy($src);
            imagedestroy($tmp);
            return;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $tmp, 0, 0, 0, 0, $size, $size, $w, $h);
        imagedestroy($tmp);

        $outPath = "{$drawablePath}/ic_stat_ic_notification.png";
        imagepng($dst, $outPath, 9);
        imagedestroy($src);
        imagedestroy($dst);

        $xmlPath = "{$drawablePath}/ic_stat_ic_notification.xml";
        if (File::exists($xmlPath)) {
            File::delete($xmlPath);
        }
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

    private function runCapSync(string $projectPath, string $platform = 'android'): void
    {
        $result = Process::path($projectPath)
            ->env($this->processEnv)
            ->timeout(120)
            ->run("npx cap sync {$platform}");
        if (! $result->successful()) {
            throw new \RuntimeException("cap sync {$platform} 실패: " . $result->errorOutput());
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

    /**
     * iOS 시뮬레이터용 빌드 (서명 불필요). .app 생성 후 zip으로 저장.
     * 참고: generic/platform=iOS Simulator는 Xcode 14+에서 지원되지 않음. 특정 시뮬레이터 지정 필요.
     */
    private function runIosBuild(string $projectPath, string $buildDir): ?string
    {
        $iosPath = "{$projectPath}/ios/App";
        $xcodeProject = "{$iosPath}/App.xcodeproj";

        if (! File::exists($xcodeProject)) {
            throw new \RuntimeException('iOS 프로젝트가 없습니다. 템플릿에 ios/ 폴더가 있는지 확인하세요.');
        }

        $derivedData = "{$buildDir}/ios-derived";
        File::ensureDirectoryExists($derivedData);

        $env = $this->processEnv;
        $xcodePath = '/Applications/Xcode.app/Contents/Developer';
        if (is_dir($xcodePath)) {
            $env['DEVELOPER_DIR'] = $xcodePath;
        }

        $destination = $this->resolveIosSimulatorDestination($iosPath, $env);
        if (! $destination) {
            throw new \RuntimeException(
                '사용 가능한 iOS 시뮬레이터를 찾을 수 없습니다. ' .
                'Xcode → Window → Devices and Simulators에서 iOS 시뮬레이터 런타임을 설치해 주세요. ' .
                '(예: iOS 18.x 시뮬레이터)'
            );
        }

        $result = Process::path($iosPath)
            ->env($env)
            ->timeout(300)
            ->run([
                'xcodebuild',
                '-project', 'App.xcodeproj',
                '-scheme', 'App',
                '-configuration', 'Debug',
                '-sdk', 'iphonesimulator',
                '-destination', $destination,
                '-derivedDataPath', $derivedData,
                'build',
            ]);

        if (! $result->successful()) {
            $err = $result->errorOutput();
            $hint = '';
            if (str_contains($err, 'Unable to find a destination') || str_contains($err, 'is not installed')) {
                $hint = ' Xcode → Settings → Platforms에서 iOS 시뮬레이터 런타임을 설치해 주세요.';
            }
            throw new \RuntimeException('iOS 빌드 실패: ' . $err . $hint);
        }

        $appPath = "{$derivedData}/Build/Products/Debug-iphonesimulator/App.app";
        if (! File::isDirectory($appPath)) {
            return null;
        }

        $zipPath = "{$buildDir}/App-ios-simulator.zip";
        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $this->addDirToZip($zip, $appPath, 'App.app');
        $zip->close();

        return File::exists($zipPath) ? $zipPath : null;
    }

    /**
     * xcodebuild -showdestinations에서 사용 가능한 iOS 시뮬레이터 1개 반환.
     * generic/platform=iOS Simulator는 Xcode 14+ 미지원. placeholder(Any iOS Simulator Device) 제외.
     */
    private function resolveIosSimulatorDestination(string $iosPath, array $env): ?string
    {
        $result = Process::path($iosPath)
            ->env($env)
            ->timeout(30)
            ->run([
                'xcodebuild',
                '-project', 'App.xcodeproj',
                '-scheme', 'App',
                '-showdestinations',
            ]);

        if ($result->successful()) {
            $output = $result->output() . $result->errorOutput();
            // placeholder 제외: id에 'placeholder' 포함된 행은 건너뜀
            if (preg_match_all('/\{ platform:iOS Simulator, id:([^,]+), OS:([^,]+), name:([^}]+) \}/', $output, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $id = trim($m[1]);
                    $name = trim($m[3]);
                    if (str_contains($id, 'placeholder') || $name === 'Any iOS Simulator Device') {
                        continue;
                    }
                    // iPhone 우선 (시뮬레이터 빌드에 적합)
                    if (stripos($name, 'iPhone') !== false) {
                        return 'platform=iOS Simulator,id=' . $id;
                    }
                }
                // iPhone 없으면 첫 번째 실제 시뮬레이터 사용
                foreach ($matches as $m) {
                    $id = trim($m[1]);
                    $name = trim($m[3]);
                    if (str_contains($id, 'placeholder') || $name === 'Any iOS Simulator Device') {
                        continue;
                    }
                    return 'platform=iOS Simulator,id=' . $id;
                }
            }
        }

        return 'platform=iOS Simulator,name=iPhone 17';
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $localPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $baseLen = strlen(rtrim($dir, '/')) + 1;
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = $localPath . '/' . str_replace('\\', '/', substr($path, $baseLen));
            if ($item->isDir()) {
                $zip->addEmptyDir($relative . '/');
            } else {
                $zip->addFile($path, $relative);
            }
        }
    }
}
