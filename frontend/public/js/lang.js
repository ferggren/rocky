/**
 * @file Provides lang support
 * @name Lang
 * @author ferg <me@ferg.in>
 * @copyright 2016 ferg
 */
 
var Lang = {
    /** Current lang */
    lang: false,

    /**
     *  Find associated to a lang_string string, replace variables and return it
     *
     *  @param {string} lang_string String id ($prefix.$id)
     *  @param {object} variables List of variables (name: value)
     *  @return {string} Processed string associated to lang_string
     */
    get: function(lang_string, variables) {
        if (typeof variables != 'object') {
            variables = {};
        }

        if (typeof lang_string != 'string') {
            return 'incorrect_string';
        }

        var pos = lang_string.indexOf('.');
        if (pos <= 0) {
            return lang_string;
        }

        var string_prefix = lang_string.substring(0, pos);
        var string_id = lang_string.substring(pos + 1);

        var strings = Lang.getStrings(string_prefix);

        if (typeof(strings) != 'object') {
            return lang_string;
        }

        if (typeof strings[string_id] == 'undefined') {
            return lang_string;
        }

        var string = strings[string_id];

        if (!Object.keys(variables).length) {
            return string;
        }

        return Lang.processString(string, variables);
    },

    /**
     *  Changes current lang
     *
     *  @param {string} New lang 
     *  @return {boolean} Result of operation
     */
    setLang: function(lang) {
        if (typeof lang != 'string') {
            return false;
        }

        if (!lang.match(/^[a-zA-Z0-9_-]{1,8}$/g)) {
            return false;
        }

        Lang.lang = lang;
        return true;
    },

    /**
     *  Return current lang
     *
     *  @return {string} Current lang
     */
    getLang: function() {
        return Lang.lang;
    },

    /**
     *  Return all strings for specified prefix
     *
     *  @param {string} prefix Strings prefix
     *  @return {object} Strings list
     */
    getStrings: function(prefix) {
        if (typeof window.LangStrings != 'object') {
            return {};
        }

        if (typeof LangStrings[Lang.lang] != 'object') {
            return {};
        }

        if (typeof LangStrings[Lang.lang][prefix] != 'object') {
            return {};
        }

        return LangStrings[Lang.lang][prefix];
    },

    /**
     *  Process string – replace variables & pluralize
     *
     *  @param {string} string Input string
     *  @param {object} List of replacements (name: value)
     *  @return {string} Processed string
     */
    processString: function(string, replacements) {
        if (typeof replacements != 'object') {
            replacements = {};
        }

        for (var key in replacements) {
            var regexp = new RegExp(Lang.__escapeRegexp('%'+key+'%'), 'g');
            string = string.replace(regexp, replacements[key]);
        }

        if (string.indexOf('rupluralize') >= 0) {
            var regexp = /rupluralize\((\d+(?:\.\d+)?)\s+['\"]([^'\"]+)['\"]\s+['\"]([^'\"]+)['\"]\s+['\"]([^'\"]+)['\"]\)/g;
            var match;
            
            while((match = regexp.exec(string)) !== null) {
                string = string.replace(
                    new RegExp(Lang.__escapeRegexp(match[0]), 'g'),
                    Lang.rupluralize(match[1], match[2], match[3], match[4])
                );

                regexp.lastIndex = 0;
            }
        }

        if (string.indexOf('pluralize') >= 0) {
            var regexp = /pluralize\((\d+(?:\.\d+)?)\s+['\"]([^'\"]+)['\"]\s+['\"]([^'\"]+)['\"]\)/g;
            var match;
            
            while((match = regexp.exec(string)) !== null) {
                string = string.replace(
                    new RegExp(Lang.__escapeRegexp(match[0]), 'g'),
                    Lang.pluralize(match[1], match[2], match[3])
                );

                regexp.lastIndex = 0;
            }
        }

        return string;
    },

    /**
     *  Return correct word form for specified number
     *
     *  @param {number} amount 
     *  @param {string} one Word form for single amount
     *  @param {string} many Word form for plural amount
     *  @return {string} Correct word form for number
     */
    pluralize: function(amount, one, many) {
        return amount == 1 ? one : many;
    },


    /**
     *  Return correct russian word form for specified number
     *
     *  @param {number} amount 
     *  @param {string} first Word form for first form (один тест)
     *  @param {string} second  Word form for second form (два теста)
     *  @param {string} third  Word form for second form (пять тестов)
     *  @return {string} Correct word form for number
     */
    rupluralize: function(amount, first, second, third) {
        amount %= 100;

        if (amount >= 10 && amount <= 20) {
            return third;
        }

        amount %= 10;

        if (amount == 1) {
            return first;
        }

        if (amount > 1 && amount < 5) {
            return second;
        }

        return third;
    },

    /**
     *  Escape regexp special symbols in string
     *
     *  @param {string} string Escaping string
     *  @return {string} Regexp-ready string
     */
    __escapeRegexp: function(string) {
        return string.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
    },
}