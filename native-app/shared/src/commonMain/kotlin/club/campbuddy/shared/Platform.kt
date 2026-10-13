package club.campbuddy.shared

import androidx.compose.runtime.Composable

/** The system back gesture / button. Android handles it; iOS has swipe-back from the screen edge. */
@Composable
expect fun PlatformBackHandler(enabled: Boolean, onBack: () -> Unit)
