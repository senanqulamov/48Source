// Single theme source for both Blade and Alpine components.
(function () {
    const key = '48source-theme';
    function apply(theme) {
        const value = theme === 'dark' ? 'dark' : 'light';
        const root = document.documentElement;
        root.dataset.theme = value;
        root.classList.toggle('dark', value === 'dark');
        root.style.colorScheme = value;
        window.dispatchEvent(new CustomEvent('app:theme-changed', {detail:{theme:value}}));
    }
    window.setAppTheme = function (theme) {
        try { localStorage.setItem(key, theme === 'dark' ? 'dark' : 'light'); } catch (_) {}
        apply(theme);
    };
    window.getAppTheme = function () { return document.documentElement.dataset.theme || 'light'; };
    // This is a Livewire SPA: apply the same theme after every navigation.
    document.addEventListener('livewire:navigated', function () {
        let theme = 'light';
        try { theme = localStorage.getItem(key) === 'dark' ? 'dark' : 'light'; } catch (_) {}
        apply(theme);
    });
})();
