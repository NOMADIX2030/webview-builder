package com.webview.app;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.activity.OnBackPressedCallback;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import java.util.ArrayList;
import java.util.List;

public class MainActivity extends BridgeActivity {

    private static final long BACK_PRESS_INTERVAL_MS = 2000;
    private static final int PERMISSION_REQUEST_ALL = 1001;

    /** 2단계에서 선택한 추가 권한 (빌드 시 주입) */
    private static final String[] EXTRA_PERMISSIONS = {{EXTRA_PERMISSIONS_ARRAY}};

    private long lastBackPressTime = 0;
    private boolean oauthClientInstalled = false;
    private boolean downloadListenerInstalled = false;
    private boolean chromeClientInstalled = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Edge-to-edge: 헤더가 상단 엣지까지 붙음. 상태바(시간·배터리) 숨김.
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        WindowInsetsControllerCompat compat = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (compat != null) {
            compat.hide(WindowInsetsCompat.Type.statusBars());
        }
        requestRequiredPermissions();
        {{FCM_INIT_BLOCK}}
        installWebViewHandlers();
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                Bridge bridge = getBridge();
                if (bridge != null) {
                    WebView webView = bridge.getWebView();
                    if (webView != null) {
                        if (webView.canGoBack()) {
                            webView.goBack();
                            return;
                        }
                        // 302 리다이렉트 등으로 히스토리가 비었을 때: 서브 경로면 origin(홈)으로 이동
                        String url = webView.getUrl();
                        if (url != null && !url.isEmpty()) {
                            try {
                                Uri uri = Uri.parse(url);
                                String path = uri.getPath();
                                boolean isAtRoot = path == null || path.isEmpty() || "/".equals(path);
                                if (!isAtRoot) {
                                    String scheme = uri.getScheme();
                                    String host = uri.getHost();
                                    if (scheme != null && host != null) {
                                        int port = uri.getPort();
                                        String homeUrl = scheme + "://" + host
                                                + (port > 0 && port != (scheme.equals("https") ? 443 : 80) ? ":" + port : "")
                                                + "/";
                                        webView.loadUrl(homeUrl);
                                        return;
                                    }
                                }
                            } catch (Exception ignored) {
                            }
                        }
                    }
                }
                // 홈(루트)에서 뒤로가기: 두 번 연속 누르면 종료 (알림 없음)
                long now = System.currentTimeMillis();
                if (now - lastBackPressTime < BACK_PRESS_INTERVAL_MS) {
                    lastBackPressTime = 0;
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                } else {
                    lastBackPressTime = now;
                }
            }
        });
    }

    @Override
    public void onResume() {
        super.onResume();
        installWebViewHandlers();
        {{FCM_RESUME_HANDLER}}
    }

    @Override
    public void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQUEST_ALL) {
            // 권한 요청 결과 처리 (필요 시 추가 로직)
        }
    }

    /**
     * 앱 실행 시 필수 권한 요청: 저장소, 알림.
     * 2단계에서 선택한 추가 권한(위치, 카메라, 마이크 등)도 함께 요청.
     */
    private void requestRequiredPermissions() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return;

        List<String> toRequest = new ArrayList<>();

        // 저장소 (API 23~32)
        if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.S_V2) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
                toRequest.add(Manifest.permission.WRITE_EXTERNAL_STORAGE);
                toRequest.add(Manifest.permission.READ_EXTERNAL_STORAGE);
            }
        } else {
            // API 33+: READ_MEDIA_IMAGES (갤러리/이미지 저장)
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES) != PackageManager.PERMISSION_GRANTED) {
                toRequest.add(Manifest.permission.READ_MEDIA_IMAGES);
            }
        }

        // 알림 (API 33+)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                toRequest.add(Manifest.permission.POST_NOTIFICATIONS);
            }
        }

        // 추가 권한 (2단계에서 선택)
        for (String p : EXTRA_PERMISSIONS) {
            if (ContextCompat.checkSelfPermission(this, p) != PackageManager.PERMISSION_GRANTED) {
                toRequest.add(p);
            }
        }

        if (!toRequest.isEmpty()) {
            ActivityCompat.requestPermissions(this, toRequest.toArray(new String[0]), PERMISSION_REQUEST_ALL);
        }
    }

    /**
     * OAuth, 다운로드 처리 등 WebView 핸들러 설치.
     */
    private void installWebViewHandlers() {
        Bridge bridge = getBridge();
        if (bridge == null) return;
        WebView webView = bridge.getWebView();
        if (webView == null) return;

        // 모바일 웹과 동일하게 viewport 적용. 미설정 시 확대되어 드래그 필요.
        WebSettings settings = webView.getSettings();
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);

        CookieManager cm = CookieManager.getInstance();
        cm.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cm.setAcceptThirdPartyCookies(webView, true);
        }

        if (!downloadListenerInstalled) {
            webView.addJavascriptInterface(new DownloadBridge(this), "AndroidBridge");
            webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
                if (url == null) return;
                if (url.startsWith("data:")) {
                    handleDataUrlDownload(webView, url, contentDisposition);
                } else if (url.startsWith("blob:")) {
                    handleBlobDownload(webView, url, contentDisposition, mimeType);
                } else {
                    DownloadHelper.startDownload(this, url, userAgent, contentDisposition, mimeType, contentLength);
                }
            });
            downloadListenerInstalled = true;
        }

        if (!oauthClientInstalled && Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            WebViewClient currentClient;
            try {
                currentClient = webView.getWebViewClient();
            } catch (Exception e) {
                return;
            }
            webView.setWebViewClient(new OAuthWebViewClient(currentClient));
            oauthClientInstalled = true;
        }

        if (!chromeClientInstalled) {
            settings.setSupportMultipleWindows(true);
            settings.setJavaScriptCanOpenWindowsAutomatically(true);
            WebChromeClient current = webView.getWebChromeClient();
            webView.setWebChromeClient(new OnCreateWindowWebChromeClient(current));
            chromeClientInstalled = true;
        }
    }

    /**
     * data: URL (html2canvas toDataURL 패턴). DownloadManager 미지원 → Java에서 직접 파싱·저장.
     */
    private void handleDataUrlDownload(WebView webView, String dataUrl, String contentDisposition) {
        String filename = DownloadHelper.extractFilename("data:image/png", contentDisposition, "image/png");
        new DownloadBridge(this).saveDataUrl(dataUrl, filename);
    }

    /**
     * blob: URL은 DownloadManager로 처리 불가.
     * 이중 전략: 1) fetch(blobUrl) → base64 → saveBlobData
     *           2) fetch 실패 시 window._lastBlob 폴백 (URL.createObjectURL 훅으로 캡처됨)
     */
    private void handleBlobDownload(WebView webView, String blobUrl, String contentDisposition, String mimeType) {
        String filename = DownloadHelper.extractFilename(blobUrl, contentDisposition, mimeType);
        String escapedUrl = escapeForJs(blobUrl);
        String escapedMime = escapeForJs(mimeType != null ? mimeType : "image/png");
        String escapedFilename = escapeForJs(filename);
        String script = "(function(){var u='" + escapedUrl + "',m='" + escapedMime + "',f='" + escapedFilename + "';" +
            "function save(b){var rd=new FileReader();rd.onload=function(){var d=rd.result.split(',')[1];" +
            "if(typeof AndroidBridge!='undefined'&&AndroidBridge.saveBlobData)AndroidBridge.saveBlobData(d,m,f);};" +
            "rd.readAsDataURL(b);}" +
            "fetch(u).then(function(r){return r.blob();}).then(save).catch(function(e){" +
            "if(window._lastBlob){save(window._lastBlob);}else{console.error('blob save failed',e);}" +
            "});})();";
        webView.evaluateJavascript(script, null);
    }

    private static String escapeForJs(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("'", "\\'").replace("\n", "\\n").replace("\r", "\\r");
    }
{{FCM_TOKEN_TO_WEB_BLOCK}}
{{FCM_CLICK_HANDLER}}
}
