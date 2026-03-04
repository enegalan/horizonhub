export function withLivewireInitialized(callback) {
    document.addEventListener('livewire:initialized', () => {
        if (typeof window.Livewire === 'undefined') return;
        callback(window.Livewire);
    });
}

export function onLivewireNavigated(callback) {
    document.addEventListener('livewire:navigated', () => {
        callback();
    });
}

export function onLivewireRequestSuccess(callback) {
    document.addEventListener('livewire:initialized', () => {
        if (typeof window.Livewire === 'undefined') return;

        window.Livewire.hook('request', ref => {
            var succeed = ref.succeed;
            if (succeed) succeed(callback);
        });
    });
}
