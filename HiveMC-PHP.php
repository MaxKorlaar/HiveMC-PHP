<?php

    namespace HiveMCPHP;

    /**
     * Class HiveMCPHP
     *
     * @author    Max Korlaar
     * @copyright 2016 Max Korlaar
     * @license   MIT
     * @credit    to @Plancke for inspiring me in how some things are handled in his HypixelPHP, for example the folder structure for caching files.
     * @package   HiveMCPHP
     */
    class HiveMCPHP
    {
        private $settings = [];
        private $error = null;

        /**
         * @param array $settings
         */
        function __construct($settings = [])
        {
            $this->setSettings($settings);
            $this->initFolders();
        }

        /**
         * @param $url
         */
        function requestJSON($url)
        {
            // todo Implement cURL -> json
        }

        /**
         * @return array
         */
        public function getSettings()
        {
            return $this->settings;
        }

        /**
         * @param array $settings
         */
        public function setSettings($settings)
        {
            $defaultSettings = [
                'cache_location' => [
                    'userprofile' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HiveMC-PHP/userprofile'
                ],
                'cache_time'     => ''
            ];
            $this->settings  = array_merge($defaultSettings, $settings);
        }

        private function initFolders()
        {
            foreach ($this->settings['cache_location'] as $cacheLocation) {
                if (!file_exists($cacheLocation)) {
                    mkdir($cacheLocation, 0777, true);
                }
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
    class jsonFile
    {
        private $file;
        private $data;
        private $timestamp;
        private $content;

        /**
         * @param $fileLocation
         */
        function __construct($fileLocation)
        {
            $this->file = $fileLocation;
            if (is_file($fileLocation)) {
                $this->content = json_decode($this->readFile(), true);
                if ($this->content !== null) {
                    $this->timestamp = $this->content['timestamp'];
                    $this->data      = $this->content['data'];
                }
            }
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
        function update($data) {
            $this->data = $data;
            $this->timestamp = time();
            $this->content = ['timestamp' => $this->timestamp, 'data' => $this->data];
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
        private function writeFile() {
            $file = fopen($this->file, 'w+');
            fwrite($file, json_encode($this->content));
            fclose($file);
        }

    }

    ?>