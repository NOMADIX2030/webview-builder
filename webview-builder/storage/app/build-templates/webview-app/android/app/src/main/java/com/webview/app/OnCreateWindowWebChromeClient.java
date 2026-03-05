package com.webview.app;

import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Message;
import android.view.View;
import android.webkit.ConsoleMessage;
import android.webkit.GeolocationPermissions;
import android.webkit.PermissionRequest;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebView;

/**
 * window.open / target="_blank" 시 부모 WebView에 로드하여 외부 브라우저 이탈 방지.
 * 기존 WebChromeClient(Capacitor 등)를 래핑하여 onCreateWindow만 오버라이드.
 */
public class OnCreateWindowWebChromeClient extends WebChromeClient {

    private final WebChromeClient delegate;

    public OnCreateWindowWebChromeClient(WebChromeClient delegate) {
        this.delegate = delegate;
    }

    @Override
    public boolean onCreateWindow(WebView view, boolean isDialog, boolean isUserGesture, Message resultMsg) {
        if (resultMsg == null || !(resultMsg.obj instanceof WebView.WebViewTransport)) {
            return delegate != null ? delegate.onCreateWindow(view, isDialog, isUserGesture, resultMsg) : false;
        }
        // 임시 WebView로 URL을 캡처한 뒤 부모 WebView에 loadUrl
        WebView popupWebView = new WebView(view.getContext());
        popupWebView.setWebViewClient(new android.webkit.WebViewClient() {
            @Override
            public void onPageStarted(WebView pw, String url, android.graphics.Bitmap favicon) {
                if (url != null && !url.equals("about:blank")) {
                    view.loadUrl(url);
                    pw.stopLoading();
                }
            }
        });
        WebView.WebViewTransport transport = (WebView.WebViewTransport) resultMsg.obj;
        transport.setWebView(popupWebView);
        resultMsg.sendToTarget();
        return true;
    }

    @Override
    public void onProgressChanged(WebView view, int newProgress) {
        if (delegate != null) delegate.onProgressChanged(view, newProgress);
    }

    @Override
    public void onReceivedTitle(WebView view, String title) {
        if (delegate != null) delegate.onReceivedTitle(view, title);
    }

    @Override
    public void onReceivedIcon(WebView view, Bitmap icon) {
        if (delegate != null) delegate.onReceivedIcon(view, icon);
    }

    @Override
    public void onReceivedTouchIconUrl(WebView view, String url, boolean precomposed) {
        if (delegate != null) delegate.onReceivedTouchIconUrl(view, url, precomposed);
    }

    @Override
    public void onShowCustomView(View view, CustomViewCallback callback) {
        if (delegate != null) delegate.onShowCustomView(view, callback);
    }

    @Override
    public void onShowCustomView(View view, int requestedOrientation, CustomViewCallback callback) {
        if (delegate != null) delegate.onShowCustomView(view, requestedOrientation, callback);
    }

    @Override
    public void onHideCustomView() {
        if (delegate != null) delegate.onHideCustomView();
    }

    @Override
    public boolean onJsAlert(WebView view, String url, String message, android.webkit.JsResult result) {
        return delegate != null && delegate.onJsAlert(view, url, message, result);
    }

    @Override
    public boolean onJsConfirm(WebView view, String url, String message, android.webkit.JsResult result) {
        return delegate != null && delegate.onJsConfirm(view, url, message, result);
    }

    @Override
    public boolean onJsPrompt(WebView view, String url, String message, String defaultValue, android.webkit.JsPromptResult result) {
        return delegate != null && delegate.onJsPrompt(view, url, message, defaultValue, result);
    }

    @Override
    public boolean onJsBeforeUnload(WebView view, String url, String message, android.webkit.JsResult result) {
        return delegate != null && delegate.onJsBeforeUnload(view, url, message, result);
    }

    @Override
    public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
        if (delegate != null) delegate.onGeolocationPermissionsShowPrompt(origin, callback);
    }

    @Override
    public void onPermissionRequest(PermissionRequest request) {
        if (delegate != null) delegate.onPermissionRequest(request);
    }

    @Override
    public void onPermissionRequestCanceled(PermissionRequest request) {
        if (delegate != null) delegate.onPermissionRequestCanceled(request);
    }

    @Override
    public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
        return delegate != null && delegate.onShowFileChooser(webView, filePathCallback, fileChooserParams);
    }

    @Override
    public boolean onConsoleMessage(ConsoleMessage consoleMessage) {
        return delegate != null && delegate.onConsoleMessage(consoleMessage);
    }
}
