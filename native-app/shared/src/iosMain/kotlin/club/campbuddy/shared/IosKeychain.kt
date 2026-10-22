package club.campbuddy.shared

import kotlinx.cinterop.BetaInteropApi
import kotlinx.cinterop.ExperimentalForeignApi
import kotlinx.cinterop.alloc
import kotlinx.cinterop.memScoped
import kotlinx.cinterop.ptr
import kotlinx.cinterop.value
import platform.CoreFoundation.CFDictionaryAddValue
import platform.CoreFoundation.CFDictionaryCreateMutable
import platform.CoreFoundation.CFMutableDictionaryRef
import platform.CoreFoundation.CFRelease
import platform.CoreFoundation.CFTypeRef
import platform.CoreFoundation.CFTypeRefVar
import platform.CoreFoundation.kCFBooleanTrue
import platform.CoreFoundation.kCFTypeDictionaryKeyCallBacks
import platform.CoreFoundation.kCFTypeDictionaryValueCallBacks
import platform.Foundation.CFBridgingRelease
import platform.Foundation.CFBridgingRetain
import platform.Foundation.NSData
import platform.Foundation.NSString
import platform.Foundation.NSUTF8StringEncoding
import platform.Foundation.create
import platform.Foundation.dataUsingEncoding
import platform.Security.SecItemAdd
import platform.Security.SecItemCopyMatching
import platform.Security.SecItemDelete
import platform.Security.errSecSuccess
import platform.Security.kSecAttrAccessible
import platform.Security.kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly
import platform.Security.kSecAttrAccount
import platform.Security.kSecAttrService
import platform.Security.kSecClass
import platform.Security.kSecClassGenericPassword
import platform.Security.kSecMatchLimit
import platform.Security.kSecMatchLimitOne
import platform.Security.kSecReturnData
import platform.Security.kSecValueData

/** Small secrets (the organizer sign-in) in the iOS Keychain, this device only. Every call is false/null on failure. */
@OptIn(ExperimentalForeignApi::class, BetaInteropApi::class)
internal object IosKeychain {
    private const val SERVICE = "club.campbuddy.app"

    /** A Keychain query for [key] (or for every CampBuddy item when null); [more] adds pairs. Caller releases it. */
    private fun query(key: String?, more: (CFMutableDictionaryRef?) -> Unit = {}): CFMutableDictionaryRef? {
        val dict = CFDictionaryCreateMutable(null, 0, kCFTypeDictionaryKeyCallBacks.ptr, kCFTypeDictionaryValueCallBacks.ptr)
        CFDictionaryAddValue(dict, kSecClass, kSecClassGenericPassword)
        add(dict, kSecAttrService, NSString.create(string = SERVICE))
        key?.let { add(dict, kSecAttrAccount, NSString.create(string = it)) }
        more(dict)

        return dict
    }

    /** Adds an Objective-C value; the dictionary keeps its own reference. */
    private fun add(dict: CFMutableDictionaryRef?, name: CFTypeRef?, value: Any) {
        val bridged = CFBridgingRetain(value)
        CFDictionaryAddValue(dict, name, bridged)
        CFRelease(bridged)
    }

    fun get(key: String): String? = runCatching {
        memScoped {
            val dict = query(key) {
                CFDictionaryAddValue(it, kSecReturnData, kCFBooleanTrue)
                CFDictionaryAddValue(it, kSecMatchLimit, kSecMatchLimitOne)
            }
            val out = alloc<CFTypeRefVar>()
            val status = SecItemCopyMatching(dict, out.ptr)
            CFRelease(dict)
            if (status != errSecSuccess) return@memScoped null

            val data = CFBridgingRelease(out.value) as? NSData ?: return@memScoped null
            NSString.create(data = data, encoding = NSUTF8StringEncoding)?.toString()
        }
    }.getOrNull()

    fun put(key: String, value: String): Boolean = runCatching {
        remove(key)
        val bytes = NSString.create(string = value).dataUsingEncoding(NSUTF8StringEncoding) ?: return false
        val dict = query(key) {
            add(it, kSecValueData, bytes)
            CFDictionaryAddValue(it, kSecAttrAccessible, kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly)
        }
        val status = SecItemAdd(dict, null)
        CFRelease(dict)
        status == errSecSuccess
    }.getOrDefault(false)

    fun remove(key: String) {
        runCatching {
            val dict = query(key)
            SecItemDelete(dict)
            CFRelease(dict)
        }
    }

    fun clear() {
        runCatching {
            val dict = query(null)
            SecItemDelete(dict)
            CFRelease(dict)
        }
    }
}
