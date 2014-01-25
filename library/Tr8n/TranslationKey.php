<?php
/**
 * Copyright (c) 2014 Michael Berkovich, http://tr8nhub.com
 *
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

namespace Tr8n;

use Tr8n\Tokens\DataTokenizer;
use Tr8n\Tokens\DecorationTokenizer;

class TranslationKey extends Base {
    /**
     * @var Application
     */
    public $application;

    /**
     * @var Language
     */
    public $language;

    /**
     * @var Translation[]
     */
    public $translations;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var int
     */
    public $level;

    /**
     * @var bool
     */
    public $locked;

    /**
     * @var Tokens\DataToken[]
     */
    public $tokens;

    /**
     * @var string[]
     */
    private $decoration_tokens;

    /**
     * @var object[]
     */
    private $data_tokens;

    /**
     * @var string[]
     */
    private $data_token_names;

    /**
     * @param array $attributes
     */
    public function __construct($attributes=array()) {
        parent::__construct($attributes);

        if ($this->key == null) {
		    $this->key = self::generateKey($this->label, $this->description);
        }

        if ($this->locale == null) {
            $this->locale = \Tr8n\Config::instance()->blockOption("locale");
            if ($this->locale == null && $this->application)
                $this->locale = $this->application->default_locale;
        }

        if ($this->language == null && $this->application) {
            $this->language = $this->application->language($this->locale);
        }

        $this->translations = array();
        if (isset($attributes['translations'])) {
            foreach($attributes["translations"] as $locale => $translations) {
                $language = $this->application->language($locale);

                if (!array_key_exists($locale, $this->translations))
                    $this->translations[$locale] = array();

                foreach($translations as $translation_hash) {
                    $t = new Translation(array_merge($translation_hash, array("translation_key"=>$this, "locale"=>$language->locale)));
                    array_push($this->translations[$locale], $t);
                }
            }
        }
    }

    /**
     * @param string $label
     * @param string $description
     * @param string $locale
     * @return string
     */
    public static function cacheKey($label, $description, $locale) {
        return "t@_[" . $locale . "]_[" . self::generateKey($label, $description) . "]";
    }

    /**
     * @param string $label
     * @param string $description
     * @return string
     */
    public static function generateKey($label, $description) {
		return md5($label . ";;;" . $description);
	}

    /**
     * @return bool
     */
    public function isLocked() {
        return ($this->locked == true);
    }

    /**
     * @param Language $language
     * @return bool
     */
    public function hasTranslations($language) {
        return count($this->translations($language)) > 0;
    }

    /**
     * @param Language $language
     * @param array $options
     * @return $this|null|TranslationKey
     */
    public function fetchTranslations($language, $options = array()) {
        if ($this->id && $this->hasTranslations($language))
            return $this;

        if (array_key_exists("dry", $options) ? $options["dry"] : Config::instance()->blockOption("dry")) {
            return $this->application->cacheTranslationKey($this);
        }

        $translation_key = $this->application->post("translation_key/translations",
                                array("key"=>$this->key, "label"=>$this->label, "description"=>$this->description, "locale" => $language->locale),
                                array("class"=>'\Tr8n\TranslationKey', "attributes"=>array("application"=>$this->application)));

        /** @var $translation_key TranslationKey */
        return $this->application->cacheTranslationKey($translation_key);
    }


    /*
     * Re-assigns the ownership of the application and translation key
     *
     * @param Application $application
     */
    public function setApplication($application) {
        $this->application = $application;
        foreach($this->translations as $locale => $translations) {
            foreach($translations as $translation) {
                $translation->translation_key = $this;
            }
        }
    }

    /**
     * @param Language $language
     * @param Translation[] $translations
     */
    public function setLanguageTranslations($language, $translations) {
        foreach($translations as $translation) {
            $translation->setTranslationKey($this);
        }
        $this->translations[$language->locale] = $translations;
    }

    /**
     * @param Language $language
     * @return Translation[]
     */
    public function translations($language) {
        if ($this->translations == null) return array();
        if (!array_key_exists($language->locale, $this->translations)) return array();
        return $this->translations[$language->locale];
    }

    /**
     * @param Translation $a
     * @param Translation $b
     * @return bool
     */
    public function compareTranslations($a, $b) {
        return $a->precedence >= $b->precedence;
    }

    /**
     * @param Language $language
     * @param mixed[] $token_values
     * @return null|Translation
     */
    protected function findFirstValidTranslation($language, $token_values) {
        $translations = $this->translations($language);

        usort($translations, array($this, 'compareTranslations'));

        foreach($translations as $translation) {
            if ($translation->isValidTranslation($token_values)) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param Language $language
     * @param mixed[] $token_values
     * @param array $options
     * @return string
     */
    public function translate($language, $token_values = array(), $options = array()) {
        if (Config::instance()->isDisabled() || ($language->locale == $this->language->locale)) {
            return $this->substituteTokens($this->label, $token_values, $this->language, $options);
        }

        $translation = $this->findFirstValidTranslation($language, $token_values);
        $decorator = Decorators\Base::decorator();

        if ($translation != null) {
            $processed_label = $this->substituteTokens($translation->label, $token_values, $translation->language, $options);
            return $decorator->decorate($this, $translation->language, $processed_label, array_merge($options, array("translated" => true)));
        }

        $processed_label =  $this->substituteTokens($this->label, $token_values, $this->language, $options);
        return $decorator->decorate($this, $this->language, $processed_label, array_merge($options, array("translated" => false)));
	}

    /**
     * Returns an array of decoration tokens from the translation key
     * @return \string[]
     */
    public function decorationTokens() {
        if ($this->decoration_tokens == null) {
            $dt = new DecorationTokenizer($this->label);
            $dt->parse();
            $this->decoration_tokens = $dt->tokens;
        }

        return $this->decoration_tokens;
    }

    /**
     * Returns an array of data tokens from the translation key
     *
     * @return \mixed[]
     */
    public function dataTokens() {
        if ($this->data_tokens == null) {
            $dt = new DataTokenizer($this->label);
            $this->data_tokens = $dt->tokens;
        }

        return $this->data_tokens;
    }

    /**
     * @return array|\string[]
     */
    public function dataTokenNamesMap() {
        if ($this->data_token_names == null) {
            $this->data_token_names = array();
            foreach($this->dataTokens() as $token) {
                $this->data_token_names[$token->name()] = true;
            }
        }

        return $this->data_token_names;
    }

    /**
     * @param string $label
     * @param mixed[] $token_values
     * @param Language $language
     * @param array $options
     * @return string
     */
    public function substituteTokens($label, $token_values, $language, $options = array()) {
        if (strpos($label, '{') !== FALSE) {
            $dt = new DataTokenizer($label, $token_values, array("allowed_tokens" => $this->dataTokenNamesMap()));
            $label = $dt->substitute($language, $options);
        }

        if (strpos($label, '[') === FALSE) return $label;
        $dt = new Tokens\DecorationTokenizer($label, $token_values, array("allowed_tokens" => $this->decorationTokens()));
        return $dt->substitute();
    }

    /**
     * @return mixed[]
     */
    public function toArray($keys=array()) {
        $info = parent::toArray(array("id", "key", "label", "description", "locale", "level"));
        if (count($this->translations) > 0) {
            $info["translations"] = array();
            foreach($this->translations as $locale=>$locale_translations) {
                $info["translations"][$locale] = array();
                foreach($locale_translations as $translation) {
                    /**  @var Translation $translation */
                    array_push($info["translations"][$locale], $translation->toArray());
                }
            }
        }
        return $info;
    }

}
