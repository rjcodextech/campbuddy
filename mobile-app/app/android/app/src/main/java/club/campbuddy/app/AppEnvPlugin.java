package club.campbuddy.app;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/** What this build can do, for the page to ask before it tries (native-bridge.js). */
@CapacitorPlugin(name = "AppEnv")
public class AppEnvPlugin extends Plugin {

    @PluginMethod
    public void info(PluginCall call) {
        JSObject result = new JSObject();
        result.put("firebase", !MainActivity.firebaseIsPlaceholder);
        call.resolve(result);
    }
}
