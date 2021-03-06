<?php

/**
 * Copyright (c) 2014 Michael Berkovich, TranslationExchange.com
 *
 *  _______                  _       _   _             ______          _
 * |__   __|                | |     | | (_)           |  ____|        | |
 *    | |_ __ __ _ _ __  ___| | __ _| |_ _  ___  _ __ | |__  __  _____| |__   __ _ _ __   __ _  ___
 *    | | '__/ _` | '_ \/ __| |/ _` | __| |/ _ \| '_ \|  __| \ \/ / __| '_ \ / _` | '_ \ / _` |/ _ \
 *    | | | | (_| | | | \__ \ | (_| | |_| | (_) | | | | |____ >  < (__| | | | (_| | | | | (_| |  __/
 *    |_|_|  \__,_|_| |_|___/_|\__,_|\__|_|\___/|_| |_|______/_/\_\___|_| |_|\__,_|_| |_|\__, |\___|
 *                                                                                        __/ |
 *                                                                                       |___/
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$files = array(
    "Tr8n/Utils",
    "Tr8n/Base.php",
    "Tr8n",
    "Tr8n/Tokens",
    "Tr8n/RulesEngine",
    "Tr8n/Decorators/Base.php",
    "Tr8n/Decorators",
    "Tr8n/Cache/Base.php",
    "Tr8n/Cache",
    "Tr8n/Cache/Generators/Base.php",
    "Tr8n/Cache/Generators",
    "Tr8n/Includes/Tags.php"
);

foreach($files as $dir) {
    $path = dirname(__FILE__)."/".$dir;
    if (is_dir($path)) {
        foreach (scandir($path) as $filename) {
            $file = $path . "/" . $filename;
            if (is_file($file)) {
                require_once $file;
            }
        }
    } else {
        require_once $path;
    }
}

/**
 * @param null $host
 * @param null $key
 * @param null $secret
 * @return bool
 */
function tr8n_init_client_sdk($key = null, $secret = null, $host = null) {
    global $tr8n_page_t0;
    $tr8n_page_t0 = microtime(true);

    \Tr8n\Config::instance()->initApplication($key, $secret, $host);

    $locale = \Tr8n\Config::instance()->default_locale;
    $translator = null;

    if (\Tr8n\Config::instance()->isEnabled()) {

        $cookie_name = "tr8n_" . \Tr8n\Config::instance()->application->key;

        if (isset($_COOKIE[$cookie_name])) {
            \Tr8n\Logger::instance()->info("Cookie file $cookie_name found!");

            $cookie_params = \Tr8n\Config::instance()->decodeAndVerifyParams($_COOKIE[$cookie_name], \Tr8n\Config::instance()->application->secret);
    //        \Tr8n\Logger::instance()->info("Cookie params", $cookie_params);

            $locale = $cookie_params['locale'];
            if (isset($cookie_params['translator'])) {
                $translator = new \Tr8n\Translator(array_merge($cookie_params["translator"], array('application' => \Tr8n\Config::instance()->application)));
            }
        } else {
            \Tr8n\Logger::instance()->info("Cookie file $cookie_name not found!");

            // start with the browser
            $locale = tr8n_browser_default_locale();

            // check the session
            if (isset($_SESSION["locale"]))
                $locale = $_SESSION["locale"];
        }
    } else {
        \Tr8n\Logger::instance()->error("Tr8n application failed to initialize. Please verify if you set the host, key and secret correctly.");
        \Tr8n\Config::instance()->application = \Tr8n\Application::dummyApplication();
    }

    if (isset($_SERVER["REQUEST_URI"])) {
        $source = $_SERVER["REQUEST_URI"];
        $source = explode("?", $source);
        $source = $source[0];
    } else {
        $source = "unknown";
    }

    \Tr8n\Config::instance()->initRequest(array('locale' => $locale, 'translator' => $translator, 'source' => $source));
    return true;
}

/**
 * @param array $options
 */
function tr8n_complete_request($options = array()) {
    \Tr8n\Config::instance()->completeRequest($options);
    global $tr8n_page_t0;
    $milliseconds = round(microtime(true) - $tr8n_page_t0,3)*1000;
    \Tr8n\Logger::instance()->info("Page loaded in " . $milliseconds . " milliseconds");
}

/**
 * Finds the first available language based on browser and application combination
 */
function tr8n_browser_default_locale() {
    $accepted = \Tr8n\Utils\BrowserUtils::parseLanguageList($_SERVER['HTTP_ACCEPT_LANGUAGE']);
//    var_dump($accepted);

    $locales = array();
    foreach (tr8n_application()->languages as $lang) array_push($locales, $lang->locale);

    $available = \Tr8n\Utils\BrowserUtils::parseLanguageList(implode(', ', $locales));
//    var_dump($available);

    $matches = \Tr8n\Utils\BrowserUtils::findMatches($accepted, $available);
//    var_dump($matches);

    $keys = array_keys($matches);
    if (count($keys) == 0)
        $locale = \Tr8n\Config::instance()->default_locale;
    else
        $locale = $matches[$keys[0]][0];

    return $locale;
}

/**
 * Includes Tr8n JavaScript library
 */
function tr8n_scripts() {
  include(__DIR__ . '/Tr8n/Includes/HeaderScripts.php');
}

/**
 * Includes Tr8n footer scripts
 */
function tr8n_footer() {
  include(__DIR__ . '/Tr8n/Includes/FooterScripts.php');
}

/**
 * @return null|\Tr8n\Application
 */
function tr8n_application() {
    return \Tr8n\Config::instance()->application;
}

/**
 * @return \Tr8n\Language
 */
function tr8n_current_language() {
    return \Tr8n\Config::instance()->current_language;
}

/**
 * @return \Tr8n\Translator
 */
function tr8n_current_translator() {
    return \Tr8n\Config::instance()->current_translator;
}

/**
 * @param array $options
 */
function tr8n_begin_block_with_options($options = array()) {
    \Tr8n\Config::instance()->beginBlockWithOptions($options);
}

/**
 * @return null
 */
function tr8n_finish_block_with_options() {
    return \Tr8n\Config::instance()->finishBlockWithOptions();
}

/**
 * There are three ways to call this method:
 *
 * 1. tr($label, $description = "", $tokens = array(), options = array())
 * 2. tr($label, $tokens = array(), $options = array())
 * 3. tr($params = array("label" => label, "description" => "", "tokens" => array(), "options" => array()))
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 * @return mixed
 */
function tr($label, $description = "", $tokens = array(), $options = array()) {
    $params = \Tr8n\Utils\ArrayUtils::normalizeTr8nParameters($label, $description, $tokens, $options);

    try {
        // Translate individual sentences
        if (isset($params["options"]['split'])) {
            $sentences = \Tr8n\Utils\StringUtils::splitSentences($params["label"]);
            foreach($sentences as $sentence) {
                $params["label"] = str_replace($sentence, tr8n_current_language()->translate($sentence, $params["description"], $params["tokens"], $params["options"]), $params["label"]);
            }
            return $label;
        }

        // Remove html and translate the content
        if (isset($params["options"]["strip"])) {
            $stripped_label = str_replace(array("\r\n", "\n"), '', strip_tags(trim($params["label"])));
            $translation = tr8n_current_language()->translate($stripped_label, $params["description"], $params["tokens"], $params["options"]);
            $label = str_replace($stripped_label, $translation, $params["label"]);
            return $label;
        }

        return tr8n_current_language()->translate($params["label"], $params["description"], $params["tokens"], $params["options"]);
    } catch(\Tr8n\Tr8nException $ex) {
        \Tr8n\Logger::instance()->error("Failed to translate " . $params["label"] . ": " . $ex);
        return $label;
    } catch(\Exception $ex) {
        \Tr8n\Logger::instance()->error("ERROR: Failed to translate " . $params["label"] . ": " . $ex);
        throw $ex;
    }
}

/**
 * Translates a label and prints it to the page
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function tre($label, $description = "", $tokens = array(), $options = array()) {
    echo tr($label, $description, $tokens, $options);
}

/**
 * Translates a label while suppressing its decorations
 * The method is useful for translating alt tags, list options, etc...
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 * @return mixed
 */
function trl($label, $description = "", $tokens = array(), $options = array()) {
    $params = \Tr8n\Utils\ArrayUtils::normalizeTr8nParameters($label, $description, $tokens, $options);
    $params["options"]["skip_decorations"] = true;
	return tr($params);
}

/**
 * Same as trl, but with printing it to the page
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trle($label, $description = "", $tokens = array(), $options = array()) {
    echo trl($label, $description, $tokens, $options);
}

/**
 * Translates a block of html, converts it to TML, translates it and then converts it back to HTML
 *
 * @param string $html
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trh($label, $description = "", $tokens = array(), $options = array()) {
    $params = \Tr8n\Utils\ArrayUtils::normalizeTr8nParameters($label, $description, $tokens, $options);

    $html = trim($params["label"]);
    $ht = new \Tr8n\Utils\HtmlTranslator($html, array(), $params["options"]);
    return $ht->translate();
}

/**
 * Translates a block of html, converts it to TML, translates it and then converts it back to HTML
 *
 * @param string $html
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trhe($label, $description = "", $tokens = array(), $options = array()) {
    $params = \Tr8n\Utils\ArrayUtils::normalizeTr8nParameters($label, $description, $tokens, $options);
    echo trh($params);
}
