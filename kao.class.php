<?php
    /**
     * Kao - simple to use and lightweight PHP caching class
     *
     * @author Dávid Benko (https://github.com/DavidB137)
     * @copyright Dávid Benko (https://github.com/DavidB137)
     * @version 1.1
     * @license MIT (https://opensource.org/licenses/MIT)
     */

    class Kao {
        /**
         * CONFIGURATION
         */
        protected $config = array (
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

            /**
             * (int) Directory permissions to use when creating new folders (in octal) on Linux.
             * These directories (and files in them) have to be readable, writable and executable by PHP!
             * - default: 0750
             * - values: 0700 - 0777
             */
            "dirCreatePermissions" => 0750
        );

        protected $id_hash, $dirCache, $dataType;

        /**
         * Default construct function
         *
         * @param string $id (identifier)
         * @param string $dataType (one of these: www, www_json, string, array, json)
         *
         * @return void
         *
         * @since 1.0
         */
        function __construct ($id, $dataType) {
            # Check config - dirCreatePermissions
            if ($this->config["dirCreatePermissions"] <= 0700 || $this->config["dirCreatePermissions"] >= 0777) trigger_error("Kao: config: dirCreatePermissions is invalid.", E_USER_WARNING);

            # Check config - dirCache, if directory doesn't exist make it
            $this->dirCache = realpath($this->config["dirCache"]);
            if (!is_dir($this->dirCache)) {
                if (!is_file($this->dirCache)) {
                    $dirCache_mkdir = mkdir($this->dirCache, $this->config["dirCreatePermissions"], true);
                    if ($dirCache_mkdir == false) trigger_error("Kao: dirCache cannot be created (" . $this->dirCache . ").", E_USER_ERROR);
                } else {
                    trigger_error("Kao: dirCache is file, not directory (" . $this->dirCache . ").", E_USER_ERROR);
                }
            }

            # Check config - id_hashAlgo
            if (empty($this->config["id_hashAlgo"])) {
                trigger_error("Kao: config: id_hashAlgo is empty.", E_USER_WARNING);
                $this->config["id_hashAlgo"] = "md5";
            }

            # Check $dataType in __construct()
            in_array($dataType, ["www", "www_json", "string", "array", "json"]) ? $this->dataType = $dataType : trigger_error("Kao: given dataType is invalid.", E_USER_ERROR);

            # Check config - returnPathType
            if ($this->config["returnPathType"] !== "relative" && $this->config["returnPathType"] !== "absolute") {
                trigger_error("Kao: config: returnPathType is invalid. We'll use absolute paths.", E_USER_WARNING);
                $this->config["returnPathType"] = "absolute";
            }

            # Create id_hash - $id hashed using id_hashAlgo from config
            $this->id_hash = hash($this->config["id_hashAlgo"], base64_encode($id));
        }

        /**
         * Creates cache files
         *
         * @param string|array $input (www address or direct input)
         * [@param array $filter (e.g.: [0, "a"] will do $input[0]["a"]; applies only for www_json, array or json dataType)]
         *
         * @return string path of created cache file
         *
         * @since 1.0
         */
        public function create ($input, $filter = array()) {
            switch ($this->dataType) {
                case "www":
                    $content = file_get_contents($input);
                    return $this->save_file($content, "cache");
                    break;

                case "www_json":
                    $content = file_get_contents($input);
                    $content_array = json_decode($content, true);
                    if (!empty($filter)) {
                        $new_content_array = $this->extract_array($content_array, $filter);
                        if (empty($new_content_array)) trigger_error("Kao: create(): extracting - index(es) does not exist. ", E_USER_WARNING);
                        return $this->save_file(json_encode($new_content_array), "json.cache");
                    } else {
                        return $this->save_file(json_encode($content_array), "json.cache");
                    }
                    break;

                case "string":
                    return $this->save_file(strval($input), "cache");
                    break;

                case "array":
                    if (!empty($filter)) {
                        $new_input = $this->extract_array($input, $filter);
                        if (empty($new_input)) trigger_error("Kao: create(): extracting - index(es) does not exist. ", E_USER_WARNING);
                        return $this->save_file(json_encode($new_input), "json.cache");
                    } else {
                        return $this->save_file(json_encode($input), "json.cache");
                    }
                    break;

                case "json":
                    $content_array = json_decode($input, true);
                    if (!empty($filter)) {
                        $new_content_array = $this->extract_array($content_array, $filter);
                        if (empty($new_content_array)) trigger_error("Kao: create(): extracting - index(es) does not exist. ", E_USER_WARNING);
                        return $this->save_file(json_encode($new_content_array), "json.cache");
                    } else {
                        return $this->save_file(json_encode($content_array), "json.cache");
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
        private function save_file ($content, $extension) {
            $id_hash = $this->id_hash;
            $dirCache = $this->dirCache;

            $date_timestamp = time();
            $date = date("Ymd-His", $date_timestamp);
            $file_withExtension = $date . "." . $extension;
            $file_withDir = $id_hash . "/" . $file_withExtension;
            $dirOfFile_fullPath = $dirCache . "/files/" . $id_hash . "/";
            $fullFilePath = $dirCache . "/files/" . $file_withDir;

            # Make directory for cache, if doesn't exist
            if (!is_file($dirOfFile_fullPath) && !is_dir($dirOfFile_fullPath)) mkdir($dirOfFile_fullPath, $this->config["dirCreatePermissions"], true);

            # Create file
            $cacheWrite = file_put_contents($fullFilePath, $content);
            if ($cacheWrite !== false) {
                file_put_contents($dirCache . "/" . $id_hash . "_current.json", '{"file":"' . $file_withExtension . '","path":"' . $file_withDir . '","dataType":"' . $this->dataType . '","extension":"' . $extension . '","timestamp":' . $date_timestamp . '}');
            } else {
                trigger_error("Kao: save_file(): saving not successful", E_USER_WARNING);
            }

            if ($this->config["returnPathType"] == "relative") {
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
        public function read () {
            $dirCache = $this->dirCache;

            # Read file with informations
            $cacheCurrent = $this->latest_cache_info();

            # Read cache file
            $file = $dirCache . "/files/" . $cacheCurrent["path"];
            $content = file_get_contents($file);

            if ($this->dataType == "www_json" || $this->dataType == "array" || $this->dataType == "json") {
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
        public function remove_old ($age) {
            $list = array ();
            $limit = time() - $age;
            $path = $this->dirCache . "/files/" . $this->id_hash . "/";

            # Error handling
            if (!is_dir($path)) trigger_error("Kao: remove_old(): Directory with cache files does not exist (" . $path . ")", E_USER_ERROR);
            $dh = opendir($path);
            if ($dh === false) trigger_error("Kao: remove_old(): Directory with cache files cannot be opened (" . $path . ")", E_USER_ERROR);

            # Remove old cache files
            while(($file = readdir($dh)) !== false) {
                $file_fullPath = $path . $file;
                if (!is_file($file_fullPath)) continue;
                if (filemtime($file_fullPath) < $limit) {
                    if ($this->config["returnPathType"] == "relative") {
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
        public function delete () {
            $path = $this->dirCache . "/files/" . $this->id_hash;
            $list_files = array ($this->dirCache . "/" . $this->id_hash . "_current.json");
            $list_files_relative = array ("/" . $this->id_hash . "_current.json");
            $list_directories = array ($path);

            if (!is_file($path) && is_dir($path)) {

                # Create array of files and directories
                $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($objects as $file => $object) {
                    if (is_file($file)) {
                        $list_files[] = $file;
                        $list_files_relative[] = str_replace($this->dirCache, "", $file);
                    } elseif (is_dir($file) && basename($file) !== "." && basename($file) !== "..") {
                        $list_directories[] = $file;
                    }
                }

                # Delete files and directories
                foreach ($list_files as $file) unlink($file);
                foreach (array_reverse($list_directories) as $dir) rmdir($dir);

                if ($this->config["returnPathType"] == "relative") {
                    return $list_files_relative;
                } else {
                    return $list_files;
                }
            } else {
                trigger_error("Kao: delete(): Path is file or isn't directory (" . $path . ")", E_USER_NOTICE);
                return array ();
            }
        }

        /**
         * Extracts items from array
         *
         * @param array $array
         * @param array $filter (e.g.: [0, "a"] will do $array[0]["a"])
         *
         * @return array extracted array
         *
         * @since 1.1
         */
        private function extract_array ($array, $filters) {
            foreach ($filters as $f) {
                if (isset($array[$f])) {
                    $array = $array[$f];
                } else {
                    return array ();
                }
            }
            return $array;
        }

        /**
         * Returns informations about latest cache file (in current id).
         *
         * @return array info: array (
         *     "file" => (filename),
         *     "path" => (relative path to the file, to get absolute just add dirCache + "/files/" to the beginning of this),
         *     "dataType" => (dataType),
         *     "extension" => (file extension),
         *     "timestamp" => (UNIX timestamp of cache file creation)
         * )
         *
         * @since 1.1
         */
        public function latest_cache_info () {
            $cacheCurrent_filePath = $this->dirCache . "/" . $this->id_hash . "_current.json";
            if (!is_file($cacheCurrent_filePath)) trigger_error("Kao: latest_cache_info(): File with informations not found", E_USER_ERROR);
            $cacheCurrent = json_decode(file_get_contents($cacheCurrent_filePath), true);

            return $cacheCurrent;
        }
    }
?>
