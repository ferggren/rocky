/**
 * @file Provides ajax support for a framework
 *       Works with controllers that are extending AjaxController
 * @name Rocky
 * @author ferg <me@ferg.in>
 * @copyright 2016 ferg
 */

var Rocky = {
    /** debug flag */
    __debug: false,

    /** Requests queue */
    __ajax_queue: {},

    /** Request loading flag */
    __ajax_loading: false,

    /** Interval for XMLHTTP readyState watcher */
    __watch_interval: false,

    /**
     *  Send ajax request to a framework
     *
     *  @param {object} request List of ajax options
     *                       url - request url
     *                       success - success callback
     *                       error - error callback
     *                       data - string, object (key: value) or FormData object
     *                       async - how request are sending, if false the next request will not be sended before previos is completed
     *                       timeout - amount of time after which request will be aborted
     *                       progress - upload progress callback
     *  @return {boolean} Result of creating new request
     */
    ajax: function(request) {
        if (typeof request != 'object') {
            request = {};
        }

        if (typeof request.url == 'undefined') {
            return false;
        }

        if (typeof request.success != 'function') {
            request.success = function() {};
        }

        if (typeof request.progress != 'function') {
            request.progress = false;
        }

        if (typeof request.error != 'function') {
            request.error = function() {};
        }

        if (typeof request.data == 'undefined') {
            request.data = {};
        }

        if (typeof request.async == 'undefined') {
            request.async = false;
        }

        if (typeof request.timeout == 'undefined') {
            request.timeout = 60;
        }

        request.status = 'pending';
        request.id = Rocky.__makeAjaxRequestId();

        Rocky.__ajax_queue[request.id] = request;
        Rocky.__ajaxProcess();

        if (!Rocky.__watch_interval) {
            Rocky.__watch_interval = setInterval(
                Rocky.__ajaxWatchStateChange,
                10
            );
        }
    },

    /**
     *  Return uniq id for new request
     *  
     *  @return {number} Uniq id
     */
    __makeAjaxRequestId: function() {
        for (var i = 0; ; ++i) {
            if (typeof (Rocky.__ajax_queue[i]) == 'object') {
                continue;
            }

            return i;
        }
    },

    /**
    *   Find and send requests
    *   Requests that are async = false will be sended in order they was created
    *   Requests that are async = true will be sended straight away
    */
    __ajaxProcess: function() {
        var id = false;

        while (id = Rocky.__ajaxGetNextId()) {
            Rocky.__ajaxExec(id);
            break;
        }
    },

    /**
     *  Search for next request to be send
     */
    __ajaxGetNextId: function() {
        var keys = Object.keys(Rocky.__ajax_queue);

        for (var id in keys) {
            id = keys[id];

            if (Rocky.__ajax_queue[id].status != 'pending') {
                continue;
            }

            if (Rocky.__ajax_loading && !Rocky.__ajax_queue[id].async) {
                continue;
            }

            return id;
        }

        return false;
    },

    /**
     * Send ajax request
     *
     * @param {number} id Ajax request id
     */
    __ajaxExec: function(id) {
        if (typeof Rocky.__ajax_queue[id] != 'object') {
            delete Rocky.__ajax_queue[id];
            return false;
        }

        var request = Rocky.__ajax_queue[id];

        request.status = 'loading';

        // new xhr
        var xhr = Rocky.__ajaxMakeXHR();
        if (!xhr) {
            Rocky.__ajaxError(id);
            return false;
        }

        request.xhr = xhr;

        // abort timer
        request.__abort_timer = 0;
        if (parseInt(Rocky.__ajax_queue[id].timeout)) {
            request.__abort_timer = setTimeout(
                Rocky.__ajaxAbort,
                request.timeout * 1000,
                id
            );
        }

        // upload listener
        if (request.xhr.upload && request.xhr.upload.addEventListener) {
            request.xhr.upload.__request_id = id;
            request.xhr.upload.addEventListener(
                'progress',
                Rocky.__ajaxUpdateUploadProgress,
                false
            );
        }

        // ajax loading flag
        if (!request.async) {
            Rocky.__ajax_loading = true;
        }

        // send data
        Rocky.__ajaxSendXHR(id);

        // process next requests
        Rocky.__ajaxProcess();
    },

    /**
     * Watch request upload progress
     *
     *  @param {object} e ProgressEvent
     */
    __ajaxUpdateUploadProgress: function(e) {
        if (!e.lengthComputable) {
            return;
        }

        if (!e.target || !e.target.__request_id) {
            return;
        }

        var id = e.target.__request_id;

        if (!Rocky.__ajax_queue[id]) {
            return;
        }
        
        if (typeof Rocky.__ajax_queue[id].progress != 'function') {
            return;
        }

        Rocky.__ajax_queue[id].progress(e.loaded, e.total);
    },

    /**
     *  Watch for request readyState to change
     */
    __ajaxWatchStateChange: function() {
        var keys = Object.keys(Rocky.__ajax_queue);

        for (var id in keys) {
            id = keys[id];

            var request = Rocky.__ajax_queue[id];

            if (request.status != 'loading') {
                continue;
            }

            if (request.xhr.readyState != 4) {
                continue;
            }

            if (request.xhr.status != 200) {
                Rocky.__ajaxError(id);
                continue;
            }

            request.response = null;

            try {
                request.response = JSON.parse(
                    request.xhr.responseText
                );
            }
            catch(e) {
                Rocky.__ajaxError(id);
                continue;
            }

            if (typeof request.response != 'object') {
                Rocky.__ajaxError(id);
                continue;
            }

            if (typeof request.response.status == 'undefined') {
                Rocky.__ajaxError(id);
                continue;
            }

            if (request.response.status != 'success') {
                if (request.response.error) {
                    Rocky.__ajaxError(id, request.response.error);
                }
                else {
                    Rocky.__ajaxError(id);
                }
                continue;
            }

            Rocky.__ajaxSuccess(id);
        }
    },

    /**
     *  Make and return XMLHTTP object
     *
     *  @return {object} Return new XMLHTTP object
     */
    __ajaxMakeXHR: function() {
        try { return new XMLHttpRequest(); }
        catch(e) {}

        try { return new ActiveXObject("Msxml2.XMLHTTP"); }
        catch(e) {}
        
        try { return new ActiveXObject("Msxml2.XMLHTTP"); }
        catch(e) {}

        return false;
    },

    /**
     *  Send XMLHTTP Request
     *
     *  @param {number} id Request id
     */
    __ajaxSendXHR: function(id) {
        // post data process
        var csrf_token = parseInt(Math.random() * 100000000) + 100000000;
        Rocky.setCookie('token_' + csrf_token, csrf_token, 60);

        var isFormData = false;
        var lang = (typeof window.Lang == 'undefined') ? false : Lang.getLang();

        var request = Rocky.__ajax_queue[id];

        if (typeof request.data == 'object') {
            if (typeof request.data.append == 'function') {
                request.data.append('__csrf_token', csrf_token);

                if (lang) {
                    request.data.append('USER_LANG', lang);
                }

                isFormData = true;
            }
            else {
                var str = '';

                for (var key in request.data) {
                    str += '&';
                    str += encodeURIComponent(key);
                    str += '=';
                    str += encodeURIComponent(request.data[key]);
                }

                request.data = str;
            }
        }

        if (typeof request.data == 'string') {
            request.data += '&__csrf_token=' + csrf_token;

            if (lang) {
                request.data += '&USER_LANG=' + lang;
            }
        }

        request.xhr.open('POST', request.url);
        request.xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (!isFormData) {
            request.xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        }

        request.xhr.send(request.data);
    },

    /**
     *  Abort XMLHTTP Request
     *
     *  @param {number} id Request id
     */
    __ajaxAbort: function(id) {
        if (typeof Rocky.__ajax_queue[id] != 'object') {
            return;
        }

        error = 'Request timeout';

        if (typeof window.Lang != 'undefined') {
            error = Lang.get('ajax.request_timeout_error');
        }

        Rocky.__ajax_queue[id].xhr.abort();
        Rocky.__ajax_queue[id].__abort_timer = 0;
        Rocky.__ajaxError(id, error);
    },

    /**
     *  Call success callback of request
     *
     *  @param {number} id Request id
     */
    __ajaxSuccess: function(id) {
        if (typeof Rocky.__ajax_queue[id] != 'object') {
            return;
        }

        if (Rocky.__ajax_queue[id].__abort_timer) {
            clearTimeout(Rocky.__ajax_queue[id].__abort_timer);
            Rocky.__ajax_queue[id].__abort_timer = 0;
        }

        if (!Rocky.__ajax_queue[id].async) {
            Rocky.__ajax_loading = false;
        }

        try {
            Rocky.__ajax_queue[id].success(
                Rocky.__ajax_queue[id].response.response
            );
        }
        catch(e) {
            Rocky.logError(e);
        }

        Rocky.__ajaxRemoveRequest(id);

        setTimeout(Rocky.__ajaxProcess, 10);
    },

    /**
     *  Call error callback of request passing an error message
     *
     *  @param {number} id Request id
     *  @param {string} error Error message
     */
    __ajaxError: function(id, error) {
        if (typeof Rocky.__ajax_queue[id] != 'object') {
            return;
        }

        if (typeof error == 'undefined') {
            error = 'Request runtime error';

            if (typeof window.Lang != 'undefined') {
                error = Lang.get('ajax.request_runtime_error');
            }
        }

        if (Rocky.__ajax_queue[id].__abort_timer) {
            clearTimeout(Rocky.__ajax_queue[id].__abort_timer);
            Rocky.__ajax_queue[id].__abort_timer = 0;
        }

        if (!Rocky.__ajax_queue[id].async) {
            Rocky.__ajax_loading = false;
        }

        try {
            Rocky.__ajax_queue[id].error(error);
        }
        catch(e) {
            Rocky.logError(e);
        }

        Rocky.__ajaxRemoveRequest(id);
        
        setTimeout(Rocky.__ajaxProcess, 10);
    },

    /**
     *  Destroy XMLHTTP request
     *
     *  @param {number} id Request id
     */
    __ajaxRemoveRequest: function(id) {
        if (Rocky.__ajax_queue[id].xhr.upload && Rocky.__ajax_queue[id].xhr.upload.removeEventListener) {
            Rocky.__ajax_queue[id].xhr.upload.removeEventListener(
                'progress',
                Rocky.__ajaxUpdateUploadProgress
            );
        }

        Rocky.__ajax_queue[id].response = null;
        Rocky.__ajax_queue[id].data = null;
        Rocky.__ajax_queue[id].xhr = null;
        Rocky.__ajax_queue[id].error = null;
        Rocky.__ajax_queue[id].success = null;
        Rocky.__ajax_queue[id] = null;
        
        delete Rocky.__ajax_queue[id];
    },

    /**
     *  Enables debug mode
     */
    enableDebug: function() {
        Rocky.__debug = true;
    },

    /**
     *  Disables debug mode
     */
    disableDebug: function() {
        Rocky.__debug = false;
    },

    /**
     *  Check if debug is enabled
     *
     *  @return {boolean} Debug status
     */
    debugEnabled: function() {
        return !!Rocky.__debug;
    },

    /**
     *  Show error
     *
     *  @param {object} error Error object
     */
    logError: function(error) {
        if (!Rocky.debugEnabled()) {
            return;
        }

        if (typeof error == 'message') {
            console.log(error);
            return;
        }

        console.log(error.stack);
    },

    /**
     *  Set cookie
     *
     *  @param {string} name Cookie name
     *  @param {string} value Cookie value
     *  @param {number} expires Cookie expiration time
     */
    setCookie: function(name, value, expires) {
        expires = parseInt(expires);

        if (isNaN(expires)) {
            expires = "";
        }
        else {        
            var date = new Date();
            date.setTime(date.getTime() + (expires * 1000));
            expires = "; expires=" + date.toGMTString();
        }

        document.cookie = name + "=" + value + expires + "; path=/";
    },

    /**
     *  Remove cookie
     *
     *  @param {string} name Cookie name
     */
    removeCookie: function(name) {
        Rocky.setCookie(name, '', -86400);
    },

    /**
     *  Execute all passed callbacks when document is ready
     *  (readyState = interactive or readyState = complete)
     *
     *  @param {requestCallback} callback Callback
     */
    onload: (function() {
        var is_loaded = false;
        var callbacks = [];

        setTimeout(function() {
            var _init = function() {
                is_loaded = true;

                for(var callback in callbacks) {
                    callbacks[callback]();
                }

                callbacks = false;
            };
            
            if(document.readyState == 'interactive' || document.readyState == 'complete') {
                _init();
            }

            else if(window.addEventListener) {
                window.addEventListener(
                    'load',
                    function() {
                        _init();
                        window.removeEventListener('load', arguments.callee);
                    },
                    false
                );
            }

            else if (window.attachEvent) {
                window.attachEvent(
                    'onload',
                    function() {
                        _init();
                        window.detachEvent('onload', arguments.callee);
                    }
                );
            }

            else {
                __init();
            }
        }, 300);

        return function(callback) {
            if(is_loaded) {
                callback();
                return;
            }

            callbacks.push(callback);
        }
    })(),
};