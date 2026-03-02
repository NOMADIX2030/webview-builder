package com.webview.app;

import android.annotation.SuppressLint;
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

    @Override
    public void onPageFinished(WebView view, String url) {
        if (delegate != null) {
            delegate.onPageFinished(view, url);
        }
        view.evaluateJavascript(JS_SAVE_IMAGE_BRIDGE, null);
        view.evaluateJavascript(JS_BLOB_HOOK, null);
        {{FCM_BRIDGE_INJECT}}
    }
}
