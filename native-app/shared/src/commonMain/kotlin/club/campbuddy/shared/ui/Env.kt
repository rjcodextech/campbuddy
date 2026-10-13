package club.campbuddy.shared.ui

import androidx.compose.runtime.compositionLocalOf
import club.campbuddy.shared.AppDeps
import club.campbuddy.shared.data.ApiClient
import club.campbuddy.shared.data.Repository
import club.campbuddy.shared.data.UserData

/** What every screen needs, handed down once from App(). */
class Env(
    val deps: AppDeps,
    val api: ApiClient,
    val repository: Repository,
    val user: UserData,
)

val LocalEnv = compositionLocalOf<Env> { error("LocalEnv not provided") }
