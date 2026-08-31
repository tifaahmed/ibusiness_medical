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

/* Laravel takes the CSRF token from the XSRF-TOKEN cookie, which axios copies
   into the X-XSRF-TOKEN header — and this app ships no <meta name="csrf-token">,
   so that cookie is the only copy there is. It can fall out of step with the
   session it belongs to: a login in another tab rotates the token, and a stale
   duplicate left over from an earlier run on the same host (a different port, or
   localhost beside 127.0.0.1) is read first by document.cookie and wins. Every
   POST then answers 419 "CSRF token mismatch" until the cookie is cleared by
   hand, which is not something the page can explain to whoever hits it.

   Sanctum's endpoint reissues the cookie for the current session, so one silent
   retry turns a dead end into a hiccup. A request is retried once and only once:
   if the session itself is gone, the second answer is the redirect to the login
   screen, which is the honest one. */
window.axios.interceptors.response.use(null, async (error) => {
    const { config, response } = error;

    if (response?.status !== 419 || !config || config._csrfRetried) {
        return Promise.reject(error);
    }

    config._csrfRetried = true;

    try {
        await window.axios.get('/sanctum/csrf-cookie', { _csrfRetried: true });
    } catch {
        return Promise.reject(error); // could not refresh — let the 419 stand
    }

    // Drop the stale header so the retry is stamped with the cookie just issued.
    if (config.headers?.delete) config.headers.delete('X-XSRF-TOKEN');
    else if (config.headers) delete config.headers['X-XSRF-TOKEN'];

    return window.axios(config);
});
