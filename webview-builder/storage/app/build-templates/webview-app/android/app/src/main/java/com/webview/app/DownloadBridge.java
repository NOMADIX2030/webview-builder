package com.webview.app;

import android.content.Context;
import android.media.MediaScannerConnection;
import android.os.Environment;
import android.util.Base64;
import android.util.Log;
import android.webkit.JavascriptInterface;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.util.Locale;

/**
 * blob URL 등 WebView에서 직접 전달받은 데이터를 파일로 저장.
 * DownloadManager는 blob: URL을 처리할 수 없으므로, JS에서 fetch → base64 → 이 인터페이스로 전달.
 */
public class DownloadBridge {

    private static final String TAG = "DownloadBridge";

    private final Context context;

    public DownloadBridge(Context context) {
        this.context = context.getApplicationContext();
    }

    /**
     * data: URL 직접 저장. html2canvas toDataURL 패턴 대응.
     */
    @JavascriptInterface
    public void saveDataUrl(String dataUrl, String filename) {
        if (dataUrl == null || !dataUrl.startsWith("data:")) return;
        int comma = dataUrl.indexOf(',');
        if (comma < 0) return;
        String base64 = dataUrl.substring(comma + 1);
        String mimeType = "image/png";
        String header = dataUrl.substring(0, comma);
        int base64Idx = header.indexOf(";base64");
        if (base64Idx > 5) {
            mimeType = header.substring(5, base64Idx).trim();
        }
        saveBlobData(base64, mimeType, filename);
    }

    @JavascriptInterface
    public void saveBlobData(String base64Data, String mimeType, String filename) {
        if (base64Data == null || base64Data.isEmpty()) return;
        try {
            byte[] bytes = Base64.decode(base64Data, Base64.DEFAULT);
            boolean isImage = mimeType != null && mimeType.toLowerCase(Locale.ROOT).startsWith("image/");
            File dir = isImage
                ? Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_PICTURES)
                : Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS);
            if (!dir.exists()) dir.mkdirs();
            File out = getUniqueFile(dir, sanitizeFilename(filename));
            try (FileOutputStream fos = new FileOutputStream(out)) {
                fos.write(bytes);
            }
            if (isImage) {
                MediaScannerConnection.scanFile(context, new String[]{out.getAbsolutePath()}, null, null);
            }
            Log.d(TAG, "Saved: " + out.getAbsolutePath());
        } catch (IOException e) {
            Log.e(TAG, "saveBlobData failed", e);
        }
    }

    private static String sanitizeFilename(String name) {
        if (name == null) return "download_" + System.currentTimeMillis();
        return name.replaceAll("[\\\\/:*?\"<>|]", "_");
    }

    /** 중복 시 파일명(1).확장자, 파일명(2).확장자 형태로 저장 */
    private static File getUniqueFile(File dir, String filename) {
        File out = new File(dir, filename);
        if (!out.exists()) return out;
        int dot = filename.lastIndexOf('.');
        String base = dot > 0 ? filename.substring(0, dot) : filename;
        String ext = dot > 0 ? filename.substring(dot) : "";
        for (int i = 1; i < 1000; i++) {
            out = new File(dir, base + "(" + i + ")" + ext);
            if (!out.exists()) return out;
        }
        return new File(dir, base + "_" + System.currentTimeMillis() + ext);
    }
}
