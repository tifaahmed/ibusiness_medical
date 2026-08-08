import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add response interceptor to debug HTTPS redirect issue
window.axios.interceptors.response.use(
    (response) => {
        console.log('=== Axios Response Interceptor ===');
        console.log('Status:', response.status);
        console.log('Status Text:', response.statusText);
        console.log('Headers:', response.headers);
        console.log('Config URL:', response.config.url);
        console.log('Response URL:', response.request.responseURL);
        console.log('Location header:', response.headers.location || response.headers.Location);
        console.log('Full response:', response);
        return response;
    },
    (error) => {
        console.log('=== Axios Error Interceptor ===');
        if (error.response) {
            console.log('Error Status:', error.response.status);
            console.log('Error Headers:', error.response.headers);
            console.log('Error Data:', error.response.data);
            console.log('Location header:', error.response.headers.location || error.response.headers.Location);
        } else if (error.request) {
            console.log('Error Request:', error.request);
            console.log('Request Response URL:', error.request.responseURL);
        }
        console.log('Error Config:', error.config);
        console.log('Error Message:', error.message);
        return Promise.reject(error);
    }
);
