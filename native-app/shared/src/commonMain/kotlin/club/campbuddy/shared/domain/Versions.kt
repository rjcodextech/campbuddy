package club.campbuddy.shared.domain

/** "1.10.0" is newer than "1.9.0": negative, zero or positive. Same rule as the website bridge. */
fun compareVersions(a: String, b: String): Int {
    val pa = a.split('.').map { it.toIntOrNull() ?: 0 }
    val pb = b.split('.').map { it.toIntOrNull() ?: 0 }
    for (i in 0 until 3) {
        val diff = (pa.getOrNull(i) ?: 0) - (pb.getOrNull(i) ?: 0)
        if (diff != 0) return diff
    }

    return 0
}
