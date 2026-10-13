package club.campbuddy.app;

import android.os.Bundle;

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
    }
}
