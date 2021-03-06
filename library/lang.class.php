<?php

/* * ************************************************************ */
/* Translations - a PHP translation library 

  Software License Agreement (BSD License)

  Copyright (C) 2007, Edward Eliot & Stuart Colville.
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright
  notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
  notice, this list of conditions and the following disclaimer in the
  documentation and/or other materials provided with the distribution.
 * Neither the name of Edward Eliot nor the names of its contributors 
  may be used to endorse or promote products derived from this software
  without specific prior written permission of Edward Eliot.

  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS" AND ANY
  EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY
  DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
  (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
  ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
  SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

  Last Updated:  17th October 2007
 */
/* * ************************************************************ */

define('TRANSLATIONS_DEFAULT_LANG', 'en');
define('TRANSLATIONS_EXT', 'tpl');
define('TRANSLATIONS_PATH', ROOT . DS . 'app' . DS . 'language' . DS);
define('TRANSLATIONS_CACHE', ROOT . DS . 'app' . DS . 'cache' . DS);
define('TRANSLATIONS_ALLOW_SHOW_KEYS', true);

class Lang extends Rex {

    protected $sFile;
    protected $sCacheFile;
    protected $aTranslations = array();

    public function __construct($sLang = TRANSLATIONS_DEFAULT_LANG, $sTranslationsPath = TRANSLATIONS_PATH) {
        // set up file paths
        $this->sFile = TRANSLATIONS_PATH . "$sLang." . TRANSLATIONS_EXT;
        $this->sCacheFile = TRANSLATIONS_CACHE . md5($sLang) . '.cache';

        // fallback to default language if current doesn't exist
        if (!file_exists($this->sFile)) {
            $this->sFile = TRANSLATIONS_PATH . TRANSLATIONS_DEFAULT_LANG . '.' . TRANSLATIONS_EXT;
        }

        // after first pass translations are stored in serialised PHP array for speed
        // does a cache exist for the selected language
        if (file_exists($this->sCacheFile)) {
            // grab current cache
            $aCache = unserialize(file_get_contents($this->sCacheFile));

            // if the recorded timestamp varies from the selected language file then the translations have changed
            // update the cache
            if ($aCache['timestamp'] == filemtime($this->sFile) && (!isset($aCache['parent-filename']) || $aCache['parent-timestamp'] == filemtime($aCache['parent-filename']))) { // not changed
                $this->aTranslations = $aCache['translations'];
            } else { // changed
                $this->process();
            }
        } else {
            $this->process();
        }
    }

    protected function process() {
        // create array for serialising
        $aCache = array();

        // read translation file into array
        $aFile = file($this->sFile);

        // does this translation file inherit from another
        $aInheritsMatches = array();
        if (isset($aFile[0]) && preg_match("/^\s*{inherits\s+([^}]+)}.*$/", $aFile[0], $aInheritsMatches)) {
            $sParentFile = TRANSLATIONS_PATH . trim($aInheritsMatches[1]) . '.' . TRANSLATIONS_EXT;
            // read parent file into array
            $aParentFile = file($sParentFile);
            // merge lines from parent file into main file array, lines in the main file override lines in the parent
            $aFile = array_merge($aParentFile, $aFile);
            // store filename of parent
            $aCache['parent-filename'] = $sParentFile;
            // store timestamp of parent
            $aCache['parent-timestamp'] = filemtime($sParentFile);
        }

        // read language array line by line
        foreach ($aFile as $sLine) {
            $aTranslationMatches = array();

            // match valid translations, strip comments - both on their own lines and at the end of a translation
            // literal hashes (#) should be escaped with a backslash
            if (preg_match("/^\s*([0-9a-z\._-]+)\s*=\s*((\\\\#|[^#])*).*$/iu", $sLine, $aTranslationMatches)) {
                $this->aTranslations[$aTranslationMatches[1]] = trim(str_replace('\#', '#', $aTranslationMatches[2]));
            }
        }
        // add current timestamp of translation file
        $aCache['timestamp'] = filemtime($this->sFile);
        // add translations
        $aCache['translations'] = $this->aTranslations;
        // write cache
        file_put_contents($this->sCacheFile, serialize($aCache));
    }

    public function get($sKey) {
        $sTranslation = '';

        if (array_key_exists($sKey, $this->aTranslations)) { // key / value pair exists
            $sTranslation = $this->aTranslations[$sKey];

            // number of arguments can be variable as user can pass any number of substitution values
            $iNumArgs = func_num_args();
            if ($iNumArgs > 1) { // complex translation, substitution values to process
                $vFirstArg = func_get_arg(1);
                if (is_array($vFirstArg)) { // named substitution variables
                    foreach ($vFirstArg as $sKey => $sValue) {
                        $sTranslation = str_replace('{' . $sKey . '}', $sValue, $sTranslation);
                    }
                } else { // numbered substitution variables
                    for ($i = 1; $i < $iNumArgs; $i++) {
                        $sParam = func_get_arg($i);
                        // replace current substitution marker with value
                        $sTranslation = str_replace('{' . ($i - 1) . '}', $sParam, $sTranslation);
                    }
                }
            }

            // whilst translating the user has the option to switch out all values with the corresponding key
            // this helps to see what translated text will appear where
            // set ALLOW_SHOW_KEYS false to disable - might be preferable in production
            if (!TRANSLATIONS_ALLOW_SHOW_KEYS || !isset($_REQUEST['showKeys'])) {
                return $sTranslation;
            }
        }
        // key / value doesn't exist, show the key instead
        return $sKey;
    }

}

?>