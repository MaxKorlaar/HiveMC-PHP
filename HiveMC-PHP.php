<?php

    namespace HiveMCPHP;

    /**
     * Class HiveMCPHP 
     * Capital 'H' because of the name Hive, which refers to The Hive.
     *
     * @author    Max Korlaar
     * @copyright 2016 Max Korlaar
     * @license   MIT
     * @credit    to @Plancke for inspiring me in how some things are handled in his HypixelPHP, for example the folder structure for caching files.
     * @package   HiveMCPHP
     */
    class HiveMCPHP
    {
        protected $options = [];
        protected $error = null;

        /**
         * @param array $settings
         */
        function __construct($settings = [])
        {
            $this->setOptions($settings);
            $this->initFolders();
        }

        /**
         * @param $url
         *
         * @return array|false
         */
        function requestJSON($url)
        {
            $timeout = $this->options['timeout'];
            $cURL = curl_init();
            curl_setopt($cURL, CURLOPT_URL, $url);
            curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($cURL, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($cURL, CURLOPT_TIMEOUT_MS, $timeout * 1000);
            curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT_MS, $timeout * 1000);
            $result = curl_exec($cURL);
            if ($result === false) {
                $this->error = ['cURL' => curl_error($cURL)];
                curl_close($cURL);
                return false;
            }
            $statusCode = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
            curl_close($cURL);
            $this->error = ['cURL' => null, 'statusCode' => $statusCode];
            if ($statusCode !== 200) {
                return false;
            }
            return json_decode($result, true);
        }

        /**
         * @return array
         */
        public function getOptions()
        {
            return $this->options;
        }

        /**
         * @param array $options
         */
        public function setOptions($options)
        {
            $defaultSettings = [
                'cache_location' => [
                    'userprofile' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HiveMC-PHP/userprofile',
                    'games_advanced' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HiveMC-PHP/game'
                ],
                'cache_time'     => 300,
                'timeout' => 1.5
            ];
            $this->options  = array_merge($defaultSettings, $options);
        }

        private function initFolders()
        {
            foreach ($this->options['cache_location'] as $cacheLocation) {
                if (!file_exists($cacheLocation)) {
                    mkdir($cacheLocation, 0777, true);
                }
            }
        }

        /**
         * Heavily inspired by Plancke's implementation of this - Note that I'm not using DIRECTORY_SEPARATOR. I've not made this for WAMP stacks.
         * @param $name
         *
         * @return string
         */
        protected function getCacheName($name) {
            $name = strtolower($name);
            $name = trim($name);
            $name = urlencode($name);
            if (strlen($name) < 3) {
                return implode('/', str_split($name, 1)) . '.json';
            }
            return substr($name, 0, 1) . '/' . substr($name, 1, 1) . '/' . substr($name, 2, 1) . '/' . substr($name, 3) . '.json';
        }

        /**
         * @param $nameOrUUID
         *
         * @return player
         */
        public function getProfile($nameOrUUID) {
            $uuid = $nameOrUUID; // todo implement name -> uuid cache using https://github.com/MaxKorlaar/mc-skintools/blob/master/includes/MojangAPI.php
            $player = new player($this->options['cache_location']['userprofile'] . '/' . $this->getCacheName($uuid), $this->options);
            if($player->getTimestamp() !== null && ($player->getCachedTime() < $this->options['cache_time'])) {
                return $player;
            } else {
                $data = $this->requestJSON('http://hivemc.com/json/userprofile/' . $uuid);
                if($data === false) return null;
                if(isset($data['error'])) {
                    $this->error['return'] = $data['error'];
                    return null;
                }
                $player->update($data);
                return $player;
            }
        }

        /**
         * @return null|array
         */
        public function getError()
        {
            return $this->error;
        }

    }

    /**
     * Class jsonFile
     *
     * @package HiveMCPHP
     */
    class jsonFile extends HiveMCPHP
    {
        private $file;
        private $data;
        private $timestamp;
        private $content;
        protected $options = [];

        /**
         * @param array $fileLocation
         * @param       $options
         */
        function __construct($fileLocation, $options)
        {
            $this->file = $fileLocation;
            $this->options = $options;
            if (is_file($fileLocation)) {
                $this->content = json_decode($this->readFile(), true);
                if ($this->content !== null) {
                    $this->timestamp = $this->content['timestamp'];
                    $this->data      = $this->content['data'];
                }
            }
            parent::__construct($options);
        }

        /**
         * @return int
         */
        function getCachedTime()
        {
            return time() - $this->timestamp;
        }

        /**
         * @param $data
         */
        function update($data)
        {
            $this->data      = $data;
            $this->timestamp = time();
            $this->content   = ['timestamp' => $this->timestamp, 'data' => $this->data];
            $this->writeFile();
        }

        /**
         * @return array
         */
        function getRawContent()
        {
            return $this->content;
        }

        /**
         * @param      $what
         * @param null $default
         *
         * @return null
         */
        function get($what, $default = null)
        {
            if ($this->data !== null) {
                if (isset($this->data[$what])) {
                    return $this->data[$what];
                } else {
                    return $default;
                }
            }
            return null;
        }

        /**
         * @return int
         */
        public function getTimestamp()
        {
            return $this->timestamp;
        }

        /**
         * @return null|string
         */
        private function readFile()
        {
            $fileContent = null;
            $file        = fopen($this->file, 'r+');
            $size        = filesize($this->file);
            if ($size !== 0) {
                $fileContent = fread($file, $size);
            }
            fclose($file);
            return $fileContent;
        }

        private function writeFile()
        {
            if(!file_exists(dirname($this->file))) { // Make sure the directory exists since we're using a nested folder structure
                mkdir(dirname($this->file), 0777, true);
            }
            $file = fopen($this->file, 'w+');
            fwrite($file, json_encode($this->content));
            fclose($file);
        }

    }

    /**
     * Class player
     *
     * @package HiveMCPHP
     */
    class player extends jsonFile {
        /**
         * @return string|null
         */
        function getName() {
            return $this->get('username');
        }

        /**
         * @return string|null
         */
        function getRankName() {
            return $this->get('rankName');
        }

        /**
         * @return int|null
         */
        function getServerCacheTime() {
            return $this->get('cached');
        }

        /**
         * @param $gameName
         *
         * @return null
         */
        function getAdvancedGameStats($gameName) {
            $gameArray = $this->get($gameName);
            if($gameArray === null) return null;
            if(isset($gameArray['advanced'])) {
                $gameStats = new jsonFile($this->options['cache_location']['games_advanced'] . '/' . $gameName . '/'. $this->getCacheName($this->get('UUID')), $this->options);
                if ($gameStats->getTimestamp() !== null && ($gameStats->getCachedTime() < $this->options['cache_time'])) {
                    return $gameStats;
                } else {
                    $data = $this->requestJSON($gameArray['advanced']);
                    if ($data === false) return null;
                    if (isset($data['error'])) {
                        $this->error['return'] = $data['error'];
                        return null;
                    }
                    $gameStats->update($data);
                    return $gameStats;
                }
            }
            return null;
        }
    }

    ?>
