package club.campbuddy.shared.ui

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.Layout
import androidx.compose.ui.layout.layout
import androidx.compose.ui.unit.Constraints
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * The bottom bars' row of tabs, laid out like the website's tab bar (flex: 1 1 0 with each label's own
 * width as its minimum): every tab gets an equal share of the width, except a tab whose label needs more
 * ("Camp Card", "Contribute") keeps its own width and the others share what is left — so no label is ever
 * cut off. When even that does not fit (very large system text), each tab shrinks in proportion and its
 * label wraps onto a second line, and the bar grows taller instead of clipping it. At least 64dp high.
 * [byWord]: a tab's need is its longest word, not its whole label (labels that may take two lines, like
 * Explore's "Free Steals" tile) — a word is never broken in the middle.
 */
@Composable
internal fun FitTabsRow(modifier: Modifier = Modifier, byWord: Boolean = false, content: @Composable () -> Unit) {
    Layout(content, modifier.fillMaxWidth().heightIn(min = 64.dp)) { measurables, constraints ->
        val total = constraints.maxWidth
        val need = measurables.map { if (byWord) it.minIntrinsicWidth(Constraints.Infinity) else it.maxIntrinsicWidth(Constraints.Infinity) }
        val widths = fitShares(need, total)
        val tallest = measurables.mapIndexed { i, m -> m.minIntrinsicHeight(widths[i]) }.maxOrNull() ?: 0
        val height = maxOf(constraints.minHeight, tallest).coerceAtMost(constraints.maxHeight)
        val placeables = measurables.mapIndexed { i, m -> m.measure(Constraints.fixed(widths[i], height)) }
        layout(total, height) {
            var x = 0
            placeables.forEach {
                it.placeRelative(x, 0)
                x += it.width
            }
        }
    }
}

/**
 * Widths for tabs that each need at least [natural] pixels in a row [total] wide: equal shares, a tab that
 * needs more than its share keeps what it needs and the rest share the remainder; if they cannot all fit,
 * shares in proportion to need. Always sums to [total].
 */
internal fun fitShares(natural: List<Int>, total: Int): List<Int> {
    val n = natural.size
    if (n == 0) return emptyList()
    val need = natural.sum()
    if (need >= total) {
        // Not enough room for everyone's label on one line: in proportion, rounding left over to the last.
        val widths = natural.map { (it.toLong() * total / need.coerceAtLeast(1)).toInt() }.toMutableList()
        widths[n - 1] += total - widths.sum()
        return widths
    }
    val widths = IntArray(n)
    val open = natural.indices.toMutableList()
    var left = total
    while (open.isNotEmpty()) {
        val share = left / open.size
        val big = open.filter { natural[it] > share }
        if (big.isEmpty()) {
            val extra = left - share * open.size
            open.forEachIndexed { k, i -> widths[i] = share + if (k < extra) 1 else 0 }
            break
        }
        big.forEach {
            widths[it] = natural[it]
            left -= natural[it]
        }
        open.removeAll(big)
    }
    return widths.toList()
}

/**
 * Lets a row reach [by] past its parent's padding on both sides (padding cannot be negative): for a row of
 * tiles that each keep [by] of space around them, so their outer edges still line up with the text above.
 */
internal fun Modifier.bleedX(by: Dp): Modifier = layout { measurable, constraints ->
    val extra = if (constraints.hasBoundedWidth) (by * 2).roundToPx() else 0
    val placeable = measurable.measure(constraints.copy(minWidth = constraints.minWidth + extra, maxWidth = constraints.maxWidth + extra))
    layout(placeable.width - extra, placeable.height) { placeable.place(-extra / 2, 0) }
}
