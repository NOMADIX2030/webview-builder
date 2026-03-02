package com.webview.app;

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Environment;
import android.webkit.MimeTypeMap;
import java.io.UnsupportedEncodingException;
import java.net.URLDecoder;
import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * WebView 다운로드 요청 처리: PDF → Downloads, 이미지 → Pictures(갤러리)
 */
public class DownloadHelper {

    private static final Pattern CONTENT_DISPOSITION_FILENAME = Pattern.compile(
        "filename\\*?=(?:UTF-8'')?[\"']?([^\"';\\n]+)[\"']?",
        Pattern.CASE_INSENSITIVE
    );

    public static void startDownload(Context context, String url, String userAgent,
                                     String contentDisposition, String mimeType, long contentLength) {
        String filename = extractFilename(url, contentDisposition, mimeType);
        boolean isImage = mimeType != null && mimeType.toLowerCase(Locale.ROOT).startsWith("image/");

        DownloadManager.Request request;
        try {
            request = new DownloadManager.Request(Uri.parse(url));
        } catch (Exception e) {
            return;
        }

        request.setMimeType(mimeType);
        request.addRequestHeader("User-Agent", userAgent != null ? userAgent : "");
        request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);

        String dir = isImage ? Environment.DIRECTORY_PICTURES : Environment.DIRECTORY_DOWNLOADS;
        request.setDestinationInExternalPublicDir(dir, filename);

        request.setTitle(filename);
        request.setDescription("다운로드 중...");

        DownloadManager dm = (DownloadManager) context.getSystemService(Context.DOWNLOAD_SERVICE);
        if (dm != null) {
            dm.enqueue(request);
        }
    }

    public static String extractFilename(String url, String contentDisposition, String mimeType) {
        String filename = null;

        if (contentDisposition != null) {
            Matcher m = CONTENT_DISPOSITION_FILENAME.matcher(contentDisposition);
            if (m.find()) {
                try {
                    filename = URLDecoder.decode(m.group(1).trim(), "UTF-8");
                } catch (UnsupportedEncodingException e) {
                    filename = m.group(1).trim();
                }
            }
        }

        if (filename == null || filename.isEmpty()) {
            try {
                String path = Uri.parse(url).getPath();
                if (path != null && path.contains("/")) {
                    filename = path.substring(path.lastIndexOf('/') + 1);
                    try {
                        filename = URLDecoder.decode(filename, "UTF-8");
                    } catch (UnsupportedEncodingException ignored) {}
                }
            } catch (Exception ignored) {}
        }

        if (filename == null || filename.isEmpty()) {
            String ext = getExtensionFromMimeType(mimeType);
            filename = "download_" + System.currentTimeMillis() + ext;
        }

        if (!filename.contains(".")) {
            filename += getExtensionFromMimeType(mimeType);
        }

        return sanitizeFilename(filename);
    }

    private static String getExtensionFromMimeType(String mimeType) {
        if (mimeType == null) return "";
        String ext = MimeTypeMap.getSingleton().getExtensionFromMimeType(mimeType);
        return ext != null ? "." + ext : "";
    }

    private static String sanitizeFilename(String name) {
        return name.replaceAll("[\\\\/:*?\"<>|]", "_");
    }
}
