import { ref } from 'vue'

const STORAGE_KEY = 'marketplace-dark-mode'

const isDarkTheme = ref(false)

const getSystemPreference = () => (
    typeof window !== 'undefined'
    && window.matchMedia('(prefers-color-scheme: dark)').matches
)

const applyTheme = (enabled: boolean) => {
    if (typeof document === 'undefined') {
        return
    }

    document.documentElement.classList.toggle('app-dark', enabled)
    document.documentElement.style.colorScheme = enabled ? 'dark' : 'light'
    isDarkTheme.value = enabled
}

const initializeTheme = () => {
    if (typeof window === 'undefined') {
        return
    }

    const stored = localStorage.getItem(STORAGE_KEY)
    const enabled = stored === null ? getSystemPreference() : stored === 'true'

    applyTheme(enabled)
}

const toggleDarkMode = () => {
    const enabled = !isDarkTheme.value

    applyTheme(enabled)
    localStorage.setItem(STORAGE_KEY, enabled ? 'true' : 'false')
}

export const useTheme = () => ({
    isDarkTheme,
    toggleDarkMode,
})

export { initializeTheme }
