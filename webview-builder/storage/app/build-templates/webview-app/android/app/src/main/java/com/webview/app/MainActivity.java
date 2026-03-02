package com.webview.app;

import android.Manifest;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.activity.OnBackPressedCallback;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    private static final long BACK_PRESS_INTERVAL_MS = 2000;
    private static final int PERMISSION_REQUEST_STORAGE = 1001;

    private long lastBackPressTime = 0;
    private boolean oauthClientInstalled = false;
    private boolean downloadListenerInstalled = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestStoragePermissionIfNeeded();
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
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQUEST_STORAGE && grantResults.length > 0
                && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            // 저장 권한 허용됨
        }
    }

    /**
     * PDF/이미지 저장을 위한 저장소 권한 요청 (API 23~32).
     */
    private void requestStoragePermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Build.VERSION.SDK_INT > Build.VERSION_CODES.S_V2) {
            return;
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this,
                new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE, Manifest.permission.READ_EXTERNAL_STORAGE},
                PERMISSION_REQUEST_STORAGE);
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

        if (!downloadListenerInstalled) {
            webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) ->
                DownloadHelper.startDownload(this, url, userAgent, contentDisposition, mimeType, contentLength)
            );
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
    }
}
