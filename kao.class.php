<?php
    /**
     * Kao - simple to use and lightweight PHP caching class
     *
     * @author Dávid Benko (https://github.com/DavidB137)
     * @copyright Dávid Benko (https://github.com/DavidB137)
     * @version 1.0
     * @license MIT (https://opensource.org/licenses/MIT)
     */

    class Kao {
        /**
         * CONFIGURATION
         * Change with your values before use!
         */
        protected $config = array(
            /**
             * (string) Directory where cache will be stored.
             * - default: __DIR__ . "/cache"
             */
            "dirCache" => __DIR__ . "/cache",

            /**
             * (string) Hashing algorithm used to hash identifier
             * - default: md5
             * - values: any hashing algorithm supported by your version of PHP, e.g.: md5, sha256,...
             */
            "id_hashAlgo" => "md5",

            /**
             * (string) Type of path (relative or absolute) used in return values of functions
             * - default: absolute
             * - values: relative or absolute
             */
            "returnPathType" => "absolute",
        );

        protected $id_hash, $dirCache, $dataType;

        /**
         * Default construct function
         *
         * @param string $id (identifier)
         * @param string $dataType (one of these: www, www_json, string, array)
         *
         * @return void
         *
         * @since 1.0
         */
        function __construct($id, $dataType){
            $this->dirCache = realpath($this->config["dirCache"]);

            if(!is_dir($this->dirCache) || $this->dirCache == false) trigger_error("Kao: dirCache does not exist or is not directory (" . $this->dirCache . ").", E_USER_ERROR);
            if(empty($this->config["id_hashAlgo"])){
                trigger_error("Kao: config: id_hashAlgo is empty.", E_USER_WARNING);
                $this->config["id_hashAlgo"] = "md5";
            }
            in_array($dataType, ["www", "www_json", "string", "array"]) ? $this->dataType = $dataType : trigger_error("Kao: given dataType is invalid.", E_USER_ERROR);
            if($this->config["returnPathType"] !== "relative" && $this->config["returnPathType"] !== "absolute"){
                trigger_error("Kao: config: returnPathType is invalid. We'll use absolute paths.", E_USER_WARNING);
                $this->config["returnPathType"] = "absolute";
            }

            $this->id_hash = hash($this->config["id_hashAlgo"], base64_encode($id));
        }

        /**
         * Creates cache files
         *
         * @param string|array $input (www address or direct input)
         *
         * @return string path of created cache file
         *
         * @since 1.0
         */
        public function create($input){
            switch($this->dataType){
                case "www":
                    $content = file_get_contents($input);
                    return $this->save_file($content, "cache");
                    break;
                case "www_json":
                    $content = file_get_contents($input);
                    return $this->save_file(json_encode(json_decode($content, true)), "json.cache");
                    break;
                case "string":
                    return $this->save_file(strval($input), "cache");
                    break;
                case "array":
                    if(is_array($input)){
                        return $this->save_file(json_encode($input), "json.cache");
                    } else {
                        trigger_error("Kao: create(): input is not array.", E_USER_ERROR);
                    }
                    break;
            }
        }

        /**
         * Saves cache files (from create() function)
         *
         * @param string $content
         * @param string $extension
         *
         * @return string path of created cache file
         *
         * @since 1.0
         */
        private function save_file($content, $extension){
            $id_hash = $this->id_hash;
            $dirCache = $this->dirCache;

            $date = date("Ymd-His");
            $file_withExtension = $date . "." . $extension;
            $file_withDir = $id_hash . "/" . $file_withExtension;
            $dirOfFile_fullPath = $dirCache . "/files/" . $id_hash . "/";
            $fullFilePath = $dirCache . "/files/" . $file_withDir;

            if(!file_exists($dirOfFile_fullPath) || !is_dir($dirOfFile_fullPath)) mkdir($dirOfFile_fullPath, 0750, true);
            $cacheWrite = file_put_contents($fullFilePath, $content);
            if($cacheWrite !== false){
                file_put_contents($dirCache . "/" . $id_hash . "_current.json", '{"file":"' . $file_withExtension . '","path":"' . $file_withDir . '","dataType":"' . $this->dataType . '","extension":"' . $extension . '"}');
            } else {
                trigger_error("Kao: save_file(): saving not successful", E_USER_WARNING);
            }

            if($this->config["returnPathType"] == "relative"){
                return "/files/" . $file_withDir;
            } else {
                return $fullFilePath;
            }
        }

        /**
         * Reads cache files
         *
         * @return string|array latest available cache (according to dataType)
         *
         * @since 1.0
         */
        public function read(){
            $dirCache = $this->dirCache;

            $cacheCurrent_filePath = $dirCache . "/" . $this->id_hash . "_current.json";
            if(!is_file($cacheCurrent_filePath)) trigger_error("Kao: read(): File with informations not found", E_USER_ERROR);
            $cacheCurrent = json_decode(file_get_contents($cacheCurrent_filePath), true);

            $file = $dirCache . "/files/" . $cacheCurrent["path"];
            $content = file_get_contents($file);

            if($this->dataType == "www_json" || $this->dataType == "array"){
                return json_decode($content, true);
            } else {
                return $content;
            }
        }

        /**
         * Removes old cache
         *
         * @param int $age (in seconds)
         *
         * @return array list of removed files
         *
         * @since 1.0
         */
        public function remove_old($age){
            $list = array();
            $limit = time() - $age;
            $path = $this->dirCache . "/files/" . $this->id_hash . "/";

            if(!is_dir($path)) trigger_error("Kao: remove_old(): Directory with cache files does not exist (" . $path . ")", E_USER_ERROR);
            $dh = opendir($path);
            if($dh === false) trigger_error("Kao: remove_old(): Directory with cache files cannot be opened (" . $path . ")", E_USER_ERROR);

            while(($file = readdir($dh)) !== false){
                $file_fullPath = $path . $file;
                if(!is_file($file_fullPath)) continue;
                if(filemtime($file_fullPath) < $limit){
                    if($this->config["returnPathType"] == "relative"){
                        $list[] = "/files/" . $this->id_hash . "/" . $file;
                    } else {
                        $list[] = $file_fullPath;
                    }
                    unlink($file_fullPath);
                }
            }
            closedir($dh);

            return $list;
        }

        /**
         * Deletes everything by id - all cache files and file with data
         *
         * @return array list of deleted files
         *
         * @since 1.0
         */
        public function delete(){
            $path = $this->dirCache . "/files/" . $this->id_hash;
            $list_files = array($this->dirCache . "/" . $this->id_hash . "_current.json");
            $list_files_relative = array("/" . $this->id_hash . "_current.json");
            $list_directories = array($path);

            if(!is_file($path) && is_dir($path)){
                $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
                foreach($objects as $file => $object){
                    if(is_file($file)){
                        $list_files[] = $file;
                        $list_files_relative[] = str_replace($this->dirCache, "", $file);
                    } elseif(is_dir($file) && basename($file) !== "." && basename($file) !== ".."){
                        $list_directories[] = $file;
                    }
                }

                foreach($list_files as $file) unlink($file);
                foreach(array_reverse($list_directories) as $dir) rmdir($dir);

                if($this->config["returnPathType"] == "relative"){
                    return $list_files_relative;
                } else {
                    return $list_files;
                }
            } else {
                trigger_error("Kao: delete(): Path is file or isn't directory (" . $path . ")", E_USER_NOTICE);
                return array();
            }
        }
    }
?>
