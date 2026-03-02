package com.webview.app;

import android.annotation.SuppressLint;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import java.util.regex.Pattern;

/**
 * OAuth/소셜 로그인 URL을 WebView 내에서 로드하여 외부 브라우저 이탈 방지.
 * 카카오, 구글, 네이버 등 OAuth URL 감지 시 메인 WebView에서 처리.
 */
public class OAuthWebViewClient extends WebViewClient {

    private static final Pattern[] OAUTH_PATTERNS = {
        Pattern.compile("^https?://(?:kauth\\.kakao\\.com|kapi\\.kakao\\.com|accounts\\.kakao\\.com)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:accounts\\.google\\.com|www\\.google\\.com/accounts)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:nid\\.naver\\.com|naver\\.com/oauth)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:www\\.facebook\\.com/v[0-9.]+/dialog/oauth|facebook\\.com/login)", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:appleid\\.apple\\.com|apple\\.com/auth)/", Pattern.CASE_INSENSITIVE),
    };

    private final WebViewClient delegate;

    public OAuthWebViewClient(WebViewClient delegate) {
        this.delegate = delegate;
    }

    public static boolean isOAuthUrl(String url) {
        if (url == null || url.isEmpty()) return false;
        for (Pattern p : OAUTH_PATTERNS) {
            if (p.matcher(url).find()) return true;
        }
        return false;
    }

    @Override
    @SuppressLint("NewApi")
    public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
        String url = request.getUrl() != null ? request.getUrl().toString() : null;
        if (isOAuthUrl(url)) {
            view.loadUrl(url);
            return true;
        }
        if (delegate != null) {
            return delegate.shouldOverrideUrlLoading(view, request);
        }
        return false;
    }

    @Override
    @SuppressWarnings("deprecation")
    public boolean shouldOverrideUrlLoading(WebView view, String url) {
        if (isOAuthUrl(url)) {
            view.loadUrl(url);
            return true;
        }
        if (delegate != null) {
            return delegate.shouldOverrideUrlLoading(view, url);
        }
        return false;
    }
}
