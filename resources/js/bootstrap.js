import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const noopChannel = () => ({
    listen: () => {},
    notification: () => {},
    stopListening: () => {},
});
window.Echo = {
    channel: noopChannel,
    private: noopChannel,
    leave: () => {},
    socketId: () => null,
};
