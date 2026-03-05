package com.webview.app;

import android.annotation.SuppressLint;
import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;
import android.webkit.WebResourceRequest;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import java.util.regex.Pattern;

/**
 * OAuth/소셜 로그인 URL을 WebView 내에서 로드하여 외부 브라우저 이탈 방지.
 * 카카오, 구글, 네이버 등 OAuth URL 감지 시 메인 WebView에서 처리.
 * 페이지 로드 시 blob 다운로드용 URL.createObjectURL 훅 주입.
 */
public class OAuthWebViewClient extends WebViewClient {

    /** 웹 페이지 범용 인터페이스: if(typeof window.saveImageToDevice==='function') 호출 시 동작 */
    private static final String JS_SAVE_IMAGE_BRIDGE = "(function(){if(window.saveImageToDevice)return;" +
        "window.saveImageToDevice=function(url,name){if(typeof AndroidBridge!=='undefined'&&AndroidBridge.saveDataUrl)" +
        "AndroidBridge.saveDataUrl(url,name||'image.png');};})();";

    /** @capacitor/splash-screen: 웹 로딩 완료 시 스플래시 숨김 (자연스러운 페이드아웃) */
    private static final String JS_SPLASH_HIDE = "(function(){try{var s=window.Capacitor&&(window.Capacitor.Plugins&&(window.Capacitor.Plugins.SplashScreen||window.Capacitor.Plugins['SplashScreen']));if(s&&typeof s.hide==='function')s.hide({fadeOutDuration:300});}catch(e){}})();";

    /** blob 훅 + blob/data: 링크 클릭 가로채기. DownloadListener 미호출. (iframe은 복잡한 이스케이프로 제외) */
    private static final String JS_BLOB_HOOK = "(function(){if(window.__blobHookInstalled)return;" +
        "window.__blobHookInstalled=true;" +
        "var o=URL.createObjectURL;URL.createObjectURL=function(b){window._lastBlob=b;return o.call(URL,b);};" +
        "document.addEventListener('click',function(e){var a=e.target.closest('a[download]');" +
        "if(!a||!a.href||typeof AndroidBridge==='undefined')return;" +
        "if(a.href.indexOf('data:')===0&&AndroidBridge.saveDataUrl){" +
        "e.preventDefault();e.stopPropagation();AndroidBridge.saveDataUrl(a.href,a.download||'image.png');}" +
        "else if(a.href.indexOf('blob:')===0&&window._lastBlob&&AndroidBridge.saveBlobData){" +
        "e.preventDefault();e.stopPropagation();var r=new FileReader();r.onload=function(){var d=r.result.split(',')[1];" +
        "AndroidBridge.saveBlobData(d,window._lastBlob.type||'image/png',a.download||'image.png');};r.readAsDataURL(window._lastBlob);}" +
        "},true);})();";

    private static final Pattern[] OAUTH_PATTERNS = {
        Pattern.compile("^https?://(?:kauth\\.kakao\\.com|kapi\\.kakao\\.com|accounts\\.kakao\\.com)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:accounts\\.google\\.com|www\\.google\\.com/accounts)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:nid\\.naver\\.com|naver\\.com/oauth)/", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:www\\.facebook\\.com/v[0-9.]+/dialog/oauth|facebook\\.com/login)", Pattern.CASE_INSENSITIVE),
        Pattern.compile("^https?://(?:appleid\\.apple\\.com|apple\\.com/auth)/", Pattern.CASE_INSENSITIVE),
    };

    /** 앱 서버 도메인: FCM/채팅 등 동일 도메인 URL을 WebView 내에서 로드 (브라우저 이탈 방지). 빌드 시 주입. */
    private static final Pattern APP_DOMAIN_PATTERN = {{APP_DOMAIN_PATTERN}};

    /** 앱 전용 인증 토큰 저장 키. cold start 시 세션 복원용. */
    private static final String PREFS_AUTH_TOKEN = "app_auth_token";
    private static final String APP_BASE_URL = "{{APP_BASE_URL}}";

    private final WebViewClient delegate;

    /** 스플래시 hide는 첫 페이지 로드 시 1회만 실행 */
    private static volatile boolean splashHidden = false;

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

    /** 앱 서버 도메인 URL이면 WebView 내에서 로드 (카카오 OAuth와 동일한 방식). */
    private static boolean isAppDomainUrl(String url) {
        if (url == null || url.isEmpty() || APP_DOMAIN_PATTERN == null) return false;
        return APP_DOMAIN_PATTERN.matcher(url).find();
    }

    /** /auth/app-token?token=... URL에서 토큰 추출 후 저장, redirect로 이동. */
    private static boolean handleAppTokenUrl(WebView view, String url) {
        if (url == null || !url.contains("/auth/app-token")) return false;
        Uri uri = Uri.parse(url);
        String token = uri.getQueryParameter("token");
        String redirect = uri.getQueryParameter("redirect");
        if (token == null || token.isEmpty()) return false;
        Context ctx = view.getContext().getApplicationContext();
        ctx.getSharedPreferences("webview_app", Context.MODE_PRIVATE).edit().putString(PREFS_AUTH_TOKEN, token).apply();
        String loadUrl = (redirect != null && !redirect.isEmpty())
            ? (redirect.startsWith("http") ? redirect : (APP_BASE_URL + (redirect.startsWith("/") ? redirect : "/" + redirect)))
            : APP_BASE_URL + "/";
        view.loadUrl(loadUrl);
        return true;
    }

    @Override
    @SuppressLint("NewApi")
    public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
        String url = request.getUrl() != null ? request.getUrl().toString() : null;
        if (handleAppTokenUrl(view, url)) return true;
        if (isOAuthUrl(url) || isAppDomainUrl(url)) {
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
        if (handleAppTokenUrl(view, url)) return true;
        if (isOAuthUrl(url) || isAppDomainUrl(url)) {
            view.loadUrl(url);
            return true;
        }
        if (delegate != null) {
            return delegate.shouldOverrideUrlLoading(view, url);
        }
        return false;
    }

    @Override
    public void onPageFinished(WebView view, String url) {
        if (delegate != null) {
            delegate.onPageFinished(view, url);
        }
        if (!splashHidden && url != null && !url.isEmpty() && !url.startsWith("about:")) {
            long launchTime = MainActivity.splashLaunchTime;
            long elapsed = System.currentTimeMillis() - (launchTime > 0 ? launchTime : System.currentTimeMillis());
            long remaining = Math.max(0, 2000 - elapsed);
            view.postDelayed(() -> {
                if (!splashHidden) {
                    view.evaluateJavascript(JS_SPLASH_HIDE, null);
                    splashHidden = true;
                }
            }, remaining);
        }
        view.evaluateJavascript(JS_SAVE_IMAGE_BRIDGE, null);
        view.evaluateJavascript(JS_BLOB_HOOK, null);
        {{FCM_BRIDGE_INJECT}}
    }
}
