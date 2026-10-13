package club.campbuddy.app;

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.webkit.CookieManager;
import android.webkit.URLUtil;
import android.widget.Toast;

import com.getcapacitor.BridgeActivity;
import com.google.firebase.FirebaseApp;
import com.google.firebase.FirebaseOptions;

public class MainActivity extends BridgeActivity {

    /** True when this build has no google-services.json and runs on a stand-in Firebase app. */
    static boolean firebaseIsPlaceholder = false;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // Push registration asks Firebase for its default app, and the plugin crashes the
        // whole process when there is none. A build made before the Firebase files exist
        // (mobile-app/SETUP.md) gets an inert stand-in so a push request fails quietly
        // instead; AppEnv.info() tells the page not to ask at all.
        if (FirebaseApp.getApps(this).isEmpty()) {
            FirebaseApp.initializeApp(this, new FirebaseOptions.Builder()
                .setApplicationId("1:000000000000:android:0000000000000000")
                .setApiKey("not-configured")
                .setProjectId("not-configured")
                .build());
            firebaseIsPlaceholder = true;
        }

        registerPlugin(AppEnvPlugin.class);
        super.onCreate(savedInstanceState);

        // A link to a file (a server export, for example) is saved to Downloads, signed in
        // as the person is. blob: links are handled by the page (native-downloads.js).
        getBridge().getWebView().setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
            if (url.startsWith("blob:") || url.startsWith("data:")) {
                return;
            }
            try {
                String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
                DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
                request.setMimeType(mimeType);
                request.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url));
                request.addRequestHeader("User-Agent", userAgent);
                request.setTitle(name);
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
                ((DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE)).enqueue(request);
                Toast.makeText(this, "Saving " + name + " to Downloads", Toast.LENGTH_SHORT).show();
            } catch (Exception e) {
                Toast.makeText(this, "Could not save the file", Toast.LENGTH_SHORT).show();
            }
        });
    }
}
